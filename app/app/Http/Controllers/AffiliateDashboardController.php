<?php

namespace App\Http\Controllers;

use App\Models\Conversion;
use App\Models\RewardPayout;
use App\Models\RewardTransaction;
use App\Services\AffiliateMetrics;
use App\Services\AnalyticsTracker;
use App\Services\ReferralCodeService;
use App\Services\RewardPayoutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateDashboardController extends Controller
{
    public function shared(Request $request, AnalyticsTracker $analytics)
    {
        $analytics->record('referral_shared');

        return response()->noContent();
    }

    public function show(Request $request, AffiliateMetrics $metrics): Response
    {
        $affiliate = $request->user();
        $code = app(ReferralCodeService::class)->ensureFor($affiliate);
        $conversions = Conversion::query()->where('type', 'membership_purchased')->where('status', 'confirmed')
            ->whereHas('relationship', fn ($query) => $query->where('referrer_user_id', $affiliate->id))
            ->latest('occurred_at')->limit(8)->get(['id', 'order_reference', 'eligible_amount', 'currency', 'occurred_at']);
        $rewards = RewardTransaction::query()->where('beneficiary_user_id', $affiliate->id)->latest()->limit(8)
            ->get(['id', 'amount', 'currency', 'status', 'available_at', 'created_at']);
        $payouts = RewardPayout::query()->where('beneficiary_user_id', $affiliate->id)->latest('requested_at')->limit(8)
            ->get(['id', 'reference', 'requested_amount', 'currency', 'status', 'requested_at', 'paid_at']);

        return Inertia::render('affiliate/dashboard', [
            'referralCode' => $code,
            'metrics' => $metrics->for($affiliate),
            'conversions' => $conversions,
            'rewards' => $rewards,
            'payouts' => $payouts,
            'minimumPayout' => app(RewardPayoutService::class)->minimum(),
        ]);
    }

    public function requestPayout(Request $request, RewardPayoutService $payouts, AnalyticsTracker $analytics)
    {
        $payout = $payouts->request($request->user());
        $analytics->record('affiliate_payout_requested', ['user_id' => $payout->beneficiary_user_id]);

        return to_route('affiliate.dashboard')->with('success', 'Solicitud de pago enviada.');
    }
}
