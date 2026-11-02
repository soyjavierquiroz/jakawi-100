<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\User;

/** Current COM entitlements, shared by the personal profile and the authorized CLUB query. */
class MembershipStatus
{
    public function membershipFor(User $user): ?Membership
    {
        return $user->activeMembership()->first()
            ?? $user->memberships()->orderByDesc('starts_at')->orderByDesc('id')->first();
    }

    public function for(User $user): array
    {
        $membership = $this->membershipFor($user);
        $active = $membership?->isActive() ?? false;
        $status = match (true) {
            $membership === null => 'free',
            $membership->status === Membership::STATUS_CANCELLED => 'cancelled',
            $membership->starts_at->isFuture() => 'scheduled',
            $active => 'active',
            default => 'expired',
        };
        $end = $membership?->effectiveEndsAt();

        return [
            'type' => $membership?->type ?? 'free',
            'status' => $status,
            'is_active' => $active,
            'starts_at' => $membership?->starts_at?->toISOString(),
            'effective_ends_at' => $end?->toISOString(),
            'expiry_policy' => $membership?->type === Membership::TYPE_PIONEER ? 'official_launch_plus_15_calendar_months' : ($membership ? 'fixed' : null),
            'official_launch_date' => $membership?->type === Membership::TYPE_PIONEER ? (config('jakawi.membership.official_launch_date') ?: null) : null,
            'benefits' => ['public_catalog' => true, 'club_access' => true, 'exclusive_member_benefits' => $active],
        ];
    }
}
