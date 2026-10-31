<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\Identity\IdentityAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubPurchaseIdentity
{
    // All callers hold the purchase advisory lock. No declared email is an identity credential.
    public function lock(string $reference): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['club-purchase:'.$reference]);
    }

    public function resolve(MembershipBankPayment $p): ?User
    {
        if ($p->club_user_uuid) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['identity:club:'.$p->club_user_uuid]);
        }
        /** @var object{status: string, user_id: int|null}|null $link */
        $link = $p->club_user_uuid ? app(IdentityAuthority::class)->link($p->club_user_uuid) : null;
        if (! $link || $link->status !== 'LINKED' || ! $link->user_id) {
            return null;
        }

        return User::whereKey($link->user_id)->lockForUpdate()->firstOrFail();
    }

    public function attach(MembershipBankPayment $p): bool
    {
        $target = $this->resolve($p);
        if (! $target) {
            $p->update(['identity_status' => 'pending']);

            return false;
        }
        if ($p->user_id === $target->id) {
            $p->update(['identity_status' => 'linked']);

            return true;
        }
        // An active membership collision never destroys the paid purchase or its receipt.
        if ($target->hasActiveMembership()) {
            $p->update(['identity_status' => 'conflict']);

            return false;
        }
        if ($p->membership_id) {
            $membership = Membership::lockForUpdate()->findOrFail($p->membership_id);
            abort_unless($membership->user_id === $p->custody_user_id, 409, 'MEMBERSHIP_OWNER_CONFLICT');
            $membership->update(['user_id' => $target->id]);
        }
        $p->update(['user_id' => $p->membership_id ? $target->id : null, 'identity_status' => 'linked']);
        if ($p->membership_id) {
            $this->linkedEvent($p, $target);
        }

        return true;
    }

    public function activationOwner(MembershipBankPayment $p): User
    {
        $this->attach($p);
        if ($p->identity_status === 'linked') {
            $user = $this->resolve($p);

            return $user;
        }
        // Non-login custody record created only on approval. Declared email is never assigned.
        if (! $p->custody_user_id) {
            $id = DB::table('users')->insertGetId(['name' => 'Compra CLUB '.$p->club_reference,
                'email' => 'club-purchase-'.$p->reference.'@purchases.invalid', 'password' => bcrypt(Str::random(64)),
                'created_at' => now(), 'updated_at' => now()]);
            $p->update(['custody_user_id' => $id]);
        }

        return User::lockForUpdate()->findOrFail($p->custody_user_id);
    }

    private function linkedEvent(MembershipBankPayment $p, User $target): void
    {
        $event = 'com:membership:'.$p->reference.':identity-linked';
        DB::table('commercial_outbox')->insertOrIgnore(['event_id' => $event, 'raw_body' => json_encode([
            'schema_version' => 1, 'event_id' => $event, 'event_type' => 'membership.identity_linked',
            'payment_reference' => $p->reference, 'club_reference' => $p->club_reference, 'club_user_uuid' => $p->club_user_uuid,
            'global_subject_id' => $target->global_subject_id, 'membership_reference' => (string) $p->membership_id,
            'occurred_at' => now()->toRfc3339String()], JSON_THROW_ON_ERROR), 'status' => 'PENDING', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }
}
