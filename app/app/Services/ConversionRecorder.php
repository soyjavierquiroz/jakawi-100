<?php

namespace App\Services;

use App\Models\AttributionTouch;
use App\Models\Campaign;
use App\Models\Conversion;
use App\Models\ReferralRelationship;
use App\Models\User;

class ConversionRecorder
{
    public function record(User $user, array $data, ?AttributionTouch $explicitTouch = null, bool $useExplicitTouch = false): Conversion
    {
        $key = $data['idempotency_key'];
        $existing = Conversion::where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        } $relationship = ReferralRelationship::where('referred_user_id', $user->id)->where('status', 'active')->latest('attributed_at')->first();
        $touch = $useExplicitTouch ? $explicitTouch : ($explicitTouch ?? AttributionTouch::where('user_id', $user->id)->latest('occurred_at')->first());
        $occurredAt = $data['occurred_at'] ?? now();
        $campaign = $touch?->utm_campaign ? Campaign::query()->where('code', $touch->utm_campaign)->operationalAt($occurredAt)->first() : null;
        $snapshot = ['referrer_user_id' => $relationship?->referrer_user_id, 'acquisition_partner_id' => $relationship?->acquisition_partner_id, 'referral_code' => $relationship?->referral_code, 'utm_source' => $touch?->utm_source, 'utm_medium' => $touch?->utm_medium, 'utm_campaign' => $touch?->utm_campaign, 'utm_content' => $touch?->utm_content, 'campaign_code' => $campaign?->code];

        return Conversion::create([...$data, 'user_id' => $user->id, 'currency' => $data['currency'] ?? 'BOB', 'eligible_amount' => $data['eligible_amount'] ?? $data['gross_amount'], 'status' => $data['status'] ?? 'pending', 'occurred_at' => $occurredAt, 'referral_relationship_id' => $relationship?->id, 'attribution_touch_id' => $touch?->id, 'campaign_id' => $campaign?->id, 'attribution_snapshot' => [...$snapshot, ...($data['attribution_snapshot'] ?? [])]]);
    }
}
