<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\Identity\IdentityAuthority;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ClubMemberAccess
{
    public const PURPOSE = 'club_member_benefits';

    public const MESSAGE = 'No pudimos autorizar el acceso. Vuelve a CLUB para continuar de forma segura. No necesitas volver a pagar.';

    public function issue(string $reference, string $club, string $claim): array
    {
        return DB::transaction(function () use ($reference, $club, $claim) {
            $identity = app(ClubPurchaseIdentity::class);
            $identity->lock($reference);
            $p = MembershipBankPayment::where('origin', 'CLUB')->where('club_reference', $reference)->first();
            abort_unless($p && is_string($p->claim_hash) && hash_equals($p->claim_hash, hash('sha256', $claim)), 403, 'INVALID_PURCHASE_CLAIM');
            abort_if($p->club_user_uuid && $p->club_user_uuid !== $club, 409, 'PURCHASE_OWNER_CONFLICT');
            abort_unless($p->status === 'approved' && $p->activated_at && $p->membership_id, 409, 'MEMBERSHIP_NOT_ACTIVE');
            // Reuse authorized identity authority; never provision or resolve by declared email.
            $link = app(IdentityAuthority::class)->link($club);
            abort_unless($link && $link->status === 'LINKED' && $link->authorized_at && $link->user_id, 409, 'IDENTITY_LINK_REQUIRED');
            $p->update(['club_user_uuid' => $club]);
            abort_unless($identity->attach($p), 409, 'IDENTITY_CONFLICT');
            $user = $this->holder($p, $club);
            $token = bin2hex(random_bytes(32));
            $expires = now()->addSeconds(90);
            DB::table('club_access_tickets')->insert(['token_hash' => hash('sha256', $token), 'payment_id' => $p->id,
                'user_id' => $user->id, 'club_user_uuid' => $club, 'global_subject_id' => $user->global_subject_id,
                'purpose' => self::PURPOSE, 'expires_at' => $expires, 'created_at' => now()]);

            return ['contract' => 'jakawi-club-member-access-v1', 'ticket' => $token, 'expires_at' => $expires->toRfc3339String(),
                'entry_url' => config('app.url').'/club/access', 'destination' => '/beneficios'];
        }, 3);
    }

    public function holder(MembershipBankPayment $p, string $club): User
    {
        $user = app(ClubPurchaseIdentity::class)->resolve($p);
        $link = app(IdentityAuthority::class)->link($club);
        $membership = Membership::whereKey($p->membership_id)->lockForUpdate()->first();
        abort_unless($p->origin === 'CLUB' && $p->status === 'approved' && $p->activated_at
            && $p->identity_status === 'linked' && $p->club_user_uuid === $club
            && $user && $link && $link->authorized_at && $link->global_subject_id === $user->global_subject_id
            && $p->user_id === $user->id && $membership && $membership->user_id === $user->id && $membership->isActive(), 409, self::MESSAGE);

        return $user;
    }

    public function ticket(string $hash): object
    {
        $ticket = DB::table('club_access_tickets')->where('token_hash', $hash)->first();
        abort_unless($ticket && $ticket->purpose === self::PURPOSE && ! $ticket->consumed_at
            && CarbonImmutable::parse($ticket->expires_at)->isFuture(), 410,
            'Este acceso venció o ya fue utilizado. Solicita otro desde CLUB; no necesitas pagar ni registrarte otra vez.');

        return $ticket;
    }

    public function consume(string $hash, callable $authorize): User
    {
        return DB::transaction(function () use ($hash, $authorize) {
            $ticket = $this->ticket($hash);
            $p = MembershipBankPayment::findOrFail($ticket->payment_id);
            app(ClubPurchaseIdentity::class)->lock($p->club_reference);
            $p->refresh();
            $user = $this->holder($p, $ticket->club_user_uuid);
            abort_unless($user->id === $ticket->user_id && $user->global_subject_id === $ticket->global_subject_id, 409, self::MESSAGE);
            $authorize($user);
            // Conditional update is the single atomic winner, also across simultaneous sessions.
            $won = DB::table('club_access_tickets')->where('id', $ticket->id)->whereNull('consumed_at')
                ->where('expires_at', '>', now())->where('purpose', self::PURPOSE)->update(['consumed_at' => now()]);
            abort_unless($won === 1, 410, 'Este acceso ya fue utilizado. Solicita otro desde CLUB.');

            return $user;
        });
    }
}
