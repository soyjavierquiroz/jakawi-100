<?php

namespace App\Services;

use App\Models\AttributionTouch;
use App\Models\Conversion;
use App\Models\Partner;
use App\Models\ReferralRelationship;

class PartnerAcquisitionMetrics
{
    /** @return array{clicks:int,registrations:int,purchases:int,revenue:string} */
    public function forPartner(Partner $partner): array
    {
        $relationships = ReferralRelationship::query()->where('acquisition_partner_id', $partner->id);
        $conversions = Conversion::query()->where('type', 'membership_purchased')->where('status', 'confirmed')
            ->whereIn('referral_relationship_id', (clone $relationships)->select('id'));

        return [
            'clicks' => AttributionTouch::query()->where('acquisition_partner_id', $partner->id)->whereNotNull('referral_code')->count(),
            'registrations' => $relationships->count(),
            'purchases' => $conversions->count(),
            'revenue' => number_format((float) $conversions->sum('gross_amount'), 2, '.', ''),
        ];
    }
}
