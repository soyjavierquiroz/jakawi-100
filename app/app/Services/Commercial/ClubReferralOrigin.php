<?php

namespace App\Services\Commercial;

use App\Models\LandingPresentation;
use App\Models\Redemption;
use App\Services\AttributionService;
use Illuminate\Http\Request;

final class ClubReferralOrigin
{
    private const KEY = 'commercial.club_origins';

    public function capture(Request $request, LandingPresentation $landing): void
    {
        $code = $request->query('club_ref');
        $partnerId = $landing->subject?->partner_id;
        if (! config('commercial.enabled') || ! $request->hasSession() || ! $partnerId
            || ! is_string($code) || ! preg_match('/\A[A-Z0-9]{12}\z/', $code)) {
            return;
        }

        // Separate from COM acquisition touches: a CLUB visit must not displace
        // the touch used by existing commissions, campaigns or referral ownership.
        $origins = $request->session()->get(self::KEY, []);
        $key = $landing->subject_type.':'.$landing->subject_id;
        unset($origins[$key]);
        $origins[$key] = [
            'partner_id' => (int) $partnerId,
            'user_id' => $request->user()?->id,
            'landing' => $landing->slug,
            'campaign' => $landing->campaign_key,
            'club_referral_code' => $code,
            'captured_at' => now()->timestamp,
        ];
        $request->session()->put(self::KEY, array_slice($origins, -20, null, true));
    }

    public function forRedemption(Request $request, Redemption $redemption): ?array
    {
        // Read only when the customer creates their operation. Confirmation by
        // an operator never reads, replaces or reconstructs this provenance.
        if (! $request->hasSession() || $request->user()?->id !== $redemption->user_id) {
            return null;
        }
        $key = $redemption->benefit->getMorphClass().':'.$redemption->benefit_id;
        $origin = $request->session()->get(self::KEY.'.'.$key);
        if (! is_array($origin) || ($origin['partner_id'] ?? null) !== $redemption->partner_id
            || (isset($origin['user_id']) && $origin['user_id'] !== $redemption->user_id)
            || ($origin['captured_at'] ?? 0) < now()->subDays(app(AttributionService::class)->windowDays())->timestamp) {
            return null;
        }

        $request->session()->put(self::KEY.'.'.$key.'.user_id', $redemption->user_id);

        return $origin;
    }
}
