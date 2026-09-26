<?php

namespace App\Services;

use App\Models\AttributionTouch;
use App\Models\Conversion;
use App\Models\ReferralRelationship;
use App\Models\RewardTransaction;
use App\Models\User;

class AffiliateMetrics
{
    /** @return array{clicks:int,registrations:int,purchases:int,revenue:string,pending:string,available:string,paid:string} */
    public function for(User $affiliate): array
    {
        $clicks = AttributionTouch::query()->where('referrer_user_id', $affiliate->id)->whereNotNull('referral_code')->count();
        $registrations = ReferralRelationship::query()->where('referrer_user_id', $affiliate->id)->where('status', ReferralRelationship::STATUS_ACTIVE)->count();
        $conversions = Conversion::query()->where('type', 'membership_purchased')->where('status', 'confirmed')
            ->whereHas('relationship', fn ($query) => $query->where('referrer_user_id', $affiliate->id));
        $rewards = RewardTransaction::query()->where('beneficiary_user_id', $affiliate->id)->where('reward_type', 'CASH');

        return [
            'clicks' => $clicks,
            'registrations' => $registrations,
            'purchases' => (clone $conversions)->count(),
            'revenue' => (string) (clone $conversions)->sum('eligible_amount'),
            'pending' => (string) (clone $rewards)->where('status', RewardTransaction::STATUS_PENDING)->sum('amount'),
            'available' => (string) (clone $rewards)->where('status', RewardTransaction::STATUS_AVAILABLE)->sum('amount'),
            'paid' => (string) (clone $rewards)->where('status', RewardTransaction::STATUS_PAID)->sum('amount'),
        ];
    }
}
