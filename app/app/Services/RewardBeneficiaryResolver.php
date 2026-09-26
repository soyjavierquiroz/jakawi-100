<?php

namespace App\Services;

use App\Models\Conversion;
use App\Models\Partner;
use App\Models\ProgramEnrollment;
use App\Models\RewardRule;
use App\Models\User;

/** Resolves exactly one paid acquisition beneficiary, separately from rule pricing. */
class RewardBeneficiaryResolver
{
    /** @return array{beneficiary: User|Partner, participant_type: string, beneficiary_type: string}|null */
    public function forMembershipAcquisition(Conversion $conversion): ?array
    {
        $creditedSellerId = $conversion->attribution_snapshot['credited_seller_user_id'] ?? null;
        if ($creditedSellerId) {
            $seller = User::find($creditedSellerId);
            if ($seller?->hasActiveProgram(ProgramEnrollment::TYPE_PROMOTER)) {
                return ['beneficiary' => $seller, 'participant_type' => ProgramEnrollment::TYPE_PROMOTER, 'beneficiary_type' => RewardRule::BENEFICIARY_USER];
            }
        }

        $relationship = $conversion->relationship()->with('referrer')->first();
        $referrer = $relationship?->referrer;
        if ($relationship?->isValid() && $referrer?->hasActiveProgram(ProgramEnrollment::TYPE_CREATOR)) {
            return ['beneficiary' => $referrer, 'participant_type' => ProgramEnrollment::TYPE_CREATOR, 'beneficiary_type' => RewardRule::BENEFICIARY_USER];
        }
        if ($relationship?->isValid() && $referrer?->hasActiveProgram(ProgramEnrollment::TYPE_AFFILIATE)) {
            return ['beneficiary' => $referrer, 'participant_type' => ProgramEnrollment::TYPE_AFFILIATE, 'beneficiary_type' => RewardRule::BENEFICIARY_USER];
        }
        if ($relationship?->isValid() && $referrer?->hasActiveMembership()) {
            return ['beneficiary' => $referrer, 'participant_type' => 'MEMBER', 'beneficiary_type' => RewardRule::BENEFICIARY_USER];
        }

        $partner = $relationship?->acquisitionPartner;
        if ($relationship?->isValid() && $partner?->isPublished()) {
            return ['beneficiary' => $partner, 'participant_type' => 'PARTNER', 'beneficiary_type' => RewardRule::BENEFICIARY_PARTNER];
        }

        return null;
    }
}
