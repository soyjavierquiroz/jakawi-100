<?php

namespace App\Services;

use App\Models\Conversion;
use App\Models\ProgramEnrollment;
use App\Models\User;

/** Resolves exactly one paid acquisition beneficiary, separately from rule pricing. */
class RewardBeneficiaryResolver
{
    /** @return array{beneficiary: User, participant_type: string}|null */
    public function forMembershipAcquisition(Conversion $conversion): ?array
    {
        $creditedSellerId = $conversion->attribution_snapshot['credited_seller_user_id'] ?? null;
        if ($creditedSellerId) {
            $seller = User::find($creditedSellerId);
            if ($seller?->hasActiveProgram(ProgramEnrollment::TYPE_PROMOTER)) {
                return ['beneficiary' => $seller, 'participant_type' => ProgramEnrollment::TYPE_PROMOTER];
            }
        }

        $relationship = $conversion->relationship()->with('referrer')->first();
        $referrer = $relationship?->referrer;
        if ($relationship?->isValid() && $referrer?->hasActiveProgram(ProgramEnrollment::TYPE_CREATOR)) {
            return ['beneficiary' => $referrer, 'participant_type' => ProgramEnrollment::TYPE_CREATOR];
        }
        if ($relationship?->isValid() && $referrer?->hasActiveProgram(ProgramEnrollment::TYPE_AFFILIATE)) {
            return ['beneficiary' => $referrer, 'participant_type' => ProgramEnrollment::TYPE_AFFILIATE];
        }

        return null;
    }
}
