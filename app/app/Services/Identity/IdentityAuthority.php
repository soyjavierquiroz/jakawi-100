<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdentityAuthority
{
    public const CONTRACT = 'jakawi-identity-handoff-v1';

    public function handoff(array $data, string $payloadHash): array
    {
        // The normalized email unique index also serializes against ordinary COM registration.
        // A unique race rolls back the whole transaction before resolving the existing account.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(fn () => $this->resolve($data, $payloadHash), 3);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? '') !== '23505' || $attempt === 2) {
                    throw $exception;
                }
            }
        }
        throw new \LogicException('Identity retry exhausted.');
    }

    private function resolve(array $data, string $payloadHash): array
    {
        $club = $data['club_user_id'];
        $id = $data['handoff_id'];
        $this->lock('club:'.$club);
        $this->lock('handoff:'.$id);
        $previous = DB::table('identity_handoffs')->where('handoff_id', $id)->first();
        if ($previous) {
            if (! hash_equals($previous->payload_hash, $payloadHash)) {
                $this->event($id, $club, 'IDEMPOTENCY_CONFLICT');
                // Return normally so the diagnostic record commits.
                return ['contract' => self::CONTRACT, 'handoff_id' => $id, 'status' => 'CONFLICT', 'error' => 'IDEMPOTENCY_CONFLICT'];
            }

            return json_decode($previous->result, true);
        }
        $expires = now()->addSeconds(config('identity.handoff_ttl_seconds'));
        $result = ['contract' => self::CONTRACT, 'issuer' => config('identity.issuer'),
            'handoff_id' => $id, 'club_user_id' => $club, 'status' => 'VERIFICATION_REQUIRED'];
        DB::table('identity_handoffs')->insert([
            'handoff_id' => $id, 'source_app' => 'club', 'club_user_id' => $club,
            'payload_hash' => $payloadHash, 'status' => $result['status'], 'result' => json_encode($result),
            'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $claims = app(EmailActivationProof::class)->verify($data['email_activation_proof'] ?? '', $club, $data['email'], $id);
        if ($claims === null) {
            return $this->saveResult($id, $club, $result);
        }
        DB::table('identity_handoffs')->where('handoff_id', $id)->update([
            'evidence' => Crypt::encryptString(json_encode(['assertion' => $data['email_activation_proof'],
                'key_id' => config('identity.club_key_id'), 'consent' => $data['consent']])),
        ]);
        $link = $this->link($club);
        if ($link) {
            $result['status'] = $link->status === 'LINKED' && $link->user_id !== null ? 'LINKED' : 'CONFLICT';
            if ($result['status'] === 'LINKED') {
                $result['link'] = $this->publicLink($link);
            }

            return $this->saveResult($id, $club, $result);
        }
        $this->lock('email:'.$data['email']);
        $existing = User::whereRaw('lower(btrim(email)) = ?', [$data['email']])->lockForUpdate()->first();
        if ($existing) {
            $result['status'] = 'AUTH_REQUIRED';
            $result['authorization_url'] = config('identity.issuer').'/identity/club/link/'.$id;
            $result['expires_at'] = $expires->toISOString();
            DB::table('identity_handoffs')->where('handoff_id', $id)->update(['target_user_id' => $existing->id]);

            return $this->saveResult($id, $club, $result);
        }
        // Deliberately separate from Fortify registration side effects: no login, attribution,
        // Partner, membership, marketing consent, commission or welcome email is granted here.
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::password(64)]);
        $user->refresh();
        // Email verification is supported by this specific cryptographic activation evidence.
        // This does not alter the verification policy of any pre-existing COM account.
        DB::table('users')->where('id', $user->id)->update(['email_verified_at' => \Carbon\CarbonImmutable::parse($claims['activated_at'])->utc()->format('Y-m-d H:i:s')]);
        if (! empty($data['whatsapp'])) {
            $user->profile()->create(['whatsapp' => $data['whatsapp']]);
        }
        DB::table('identity_handoffs')->where('handoff_id', $id)->update(['target_user_id' => $user->id]);
        $this->createLink($id, $club, $user, 'CLUB_EMAIL_ACTIVATION');
        $result['status'] = 'PROVISIONED';
        $result['link'] = $this->publicLink($this->link($club));

        return $this->saveResult($id, $club, $result);
    }

    public function authorize(string $id, User $user, int $authenticatedAt): array
    {
        $pending = $this->getHandoff($id);

        return DB::transaction(function () use ($pending, $id, $user, $authenticatedAt): array {
            $club = $pending->club_user_id;
            $this->lock('club:'.$club);
            $handoff = DB::table('identity_handoffs')->where('handoff_id', $id)->lockForUpdate()->first();
            abort_unless($handoff->target_user_id === $user->id, 403, 'La cuenta autenticada no puede autorizar este vínculo.');
            $result = json_decode($handoff->result, true);
            if ($handoff->status === 'LINKED') {
                return $result;
            }
            abort_unless($handoff->status === 'AUTH_REQUIRED', 409);
            abort_if(now()->greaterThan($handoff->expires_at), 410, 'La solicitud caducó. Iníciala de nuevo desde CLUB.');
            abort_if($authenticatedAt < time() - config('identity.fresh_auth_seconds') || $authenticatedAt > time() + 30, 403);
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $evidence = json_decode(Crypt::decryptString($handoff->evidence), true);
            $assertion = json_decode(base64_decode(strtr(explode('.', $evidence['assertion'])[0], '-_', '+/')), true);
            // A changed COM email requires a new handoff; identity itself remains unchanged.
            if (Str::lower(trim($lockedUser->email)) !== $assertion['email']
                || $this->link($club) !== null
                || DB::table('identity_links')->where('source_app', 'club')->where('global_subject_id', $lockedUser->global_subject_id)->exists()) {
                $result = ['contract' => self::CONTRACT, 'issuer' => config('identity.issuer'),
                    'handoff_id' => $id, 'club_user_id' => $club, 'status' => 'CONFLICT'];

                return $this->saveResult($id, $club, $result);
            }
            $evidence['com_authorization'] = ['authenticated_at' => $authenticatedAt, 'consent_at' => now()->toISOString(), 'method' => 'COM_FORTIFY_SESSION'];
            DB::table('identity_handoffs')->where('handoff_id', $id)->update(['evidence' => Crypt::encryptString(json_encode($evidence))]);
            $this->createLink($id, $club, $lockedUser, 'COM_AUTHENTICATED_CONSENT');
            $result = ['contract' => self::CONTRACT, 'issuer' => config('identity.issuer'),
                'handoff_id' => $id, 'club_user_id' => $club, 'status' => 'LINKED', 'link' => $this->publicLink($this->link($club))];

            return $this->saveResult($id, $club, $result);
        }, 3);
    }

    public function getHandoff(string $id): object
    {
        return DB::table('identity_handoffs')->where('handoff_id', $id)->first() ?? abort(404);
    }

    public function link(string $club): ?object
    {
        return DB::table('identity_links')->where('source_app', 'club')->where('club_user_id', $club)->first();
    }

    public function publicLink(object $link): array
    {
        return ['source_app' => 'club', 'club_user_id' => $link->club_user_id,
            'global_subject_id' => $link->global_subject_id, 'issuer' => config('identity.issuer'),
            'status' => $link->user_id === null ? 'REVOKED' : $link->status, 'method' => $link->method,
            'authorized_at' => $link->authorized_at, 'revoked_at' => $link->revoked_at];
    }

    public function revoke(string $club): array
    {
        return DB::transaction(function () use ($club): array {
            $this->lock('club:'.$club);
            $link = $this->link($club) ?? abort(404);
            if ($link->status !== 'REVOKED') {
                DB::table('identity_links')->where('id', $link->id)->update(['status' => 'REVOKED', 'revoked_at' => now()]);
                // Persist revocation in every completed handoff, so retries never report an active link.
                foreach (DB::table('identity_handoffs')->where('club_user_id', $club)->whereIn('status', ['LINKED', 'PROVISIONED'])->get() as $handoff) {
                    $result = json_decode($handoff->result, true);
                    $result['status'] = 'REVOKED';
                    $result['link'] = $this->publicLink($this->link($club));
                    $this->saveResult($handoff->handoff_id, $club, $result);
                }
                $this->event($link->handoff_id, $club, 'REVOKED');
            }

            return $this->publicLink($this->link($club));
        }, 3);
    }

    private function createLink(string $id, string $club, User $user, string $method): void
    {
        DB::table('identity_links')->insert(['source_app' => 'club', 'club_user_id' => $club,
            'global_subject_id' => $user->global_subject_id, 'user_id' => $user->id, 'status' => 'LINKED',
            'method' => $method, 'handoff_id' => $id, 'authorized_at' => now()]);
    }

    private function saveResult(string $id, string $club, array $result): array
    {
        DB::table('identity_handoffs')->where('handoff_id', $id)->update([
            'status' => $result['status'], 'result' => json_encode($result), 'updated_at' => now(),
        ]);
        $this->event($id, $club, $result['status']);

        return $result;
    }

    private function event(?string $id, string $club, string $event): void
    {
        DB::table('identity_link_events')->insert(['handoff_id' => $id, 'club_user_id' => $club, 'event' => $event, 'occurred_at' => now()]);
    }

    private function lock(string $value): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['identity:'.$value]);
    }
}
