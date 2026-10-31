<?php

namespace App\Services;

use App\Models\Conversion;
use App\Models\RewardRule;
use App\Models\User;

/** Resolves exactly one paid acquisition beneficiary, separately from rule pricing. */
class RewardBeneficiaryResolver
{
    /** @return array{beneficiary: User, participant_type: string, beneficiary_type: string}|null */
    public function forMembershipAcquisition(Conversion $conversion): ?array
    {
        $relationship = $conversion->relationship()->with('referrer')->first();
        // Partner membership acquisition belongs exclusively to CLUB, including
        // ambiguous legacy relationships that also reference an individual.
        if ($relationship?->acquisition_partner_id !== null) {
            return null;
        }
        $referrer = $relationship?->referrer;
        // A legacy commercial enrollment never falls back to a member reward.
        if ($relationship?->isValid() && $referrer?->programEnrollments()->active()->whereIn('program_type', ['PROMOTER', 'AFFILIATE', 'CREATOR'])->exists()) {
            return null;
        }
        if ($relationship?->isValid() && $referrer?->hasActiveMembership()) {
            return ['beneficiary' => $referrer, 'participant_type' => 'MEMBER', 'beneficiary_type' => RewardRule::BENEFICIARY_USER];
        }

        return null;
    }
}
