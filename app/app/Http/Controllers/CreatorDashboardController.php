<?php

namespace App\Http\Controllers;

use App\Models\Conversion;
use App\Models\RewardPayout;
use App\Models\RewardTransaction;
use App\Services\AnalyticsTracker;
use App\Services\CreatorMetrics;
use App\Services\ReferralCodeService;
use App\Services\RewardPayoutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreatorDashboardController extends Controller
{
    public function shared(AnalyticsTracker $analytics) { $analytics->record('referral_shared'); return response()->noContent(); }

    public function show(Request $request, CreatorMetrics $metrics): Response
    {
        $creator = $request->user(); $code = app(ReferralCodeService::class)->ensureFor($creator);
        $conversions = Conversion::query()->where('type', 'membership_purchased')->where('status', 'confirmed')->whereHas('relationship', fn ($q) => $q->where('referrer_user_id', $creator->id))->latest('occurred_at')->limit(8)->get(['id', 'order_reference', 'eligible_amount', 'currency', 'occurred_at']);
        return Inertia::render('creator/dashboard', ['referralCode' => $code, 'metrics' => $metrics->for($creator), 'content' => $metrics->byContent($creator, $request->string('campaign')->toString() ?: null, $request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null), 'filters' => $request->only('campaign', 'from', 'to'), 'conversions' => $conversions, 'rewards' => RewardTransaction::where('beneficiary_user_id', $creator->id)->latest()->limit(8)->get(['id', 'amount', 'currency', 'status', 'available_at', 'created_at']), 'payouts' => RewardPayout::where('beneficiary_user_id', $creator->id)->latest('requested_at')->limit(8)->get(['id', 'reference', 'requested_amount', 'currency', 'status', 'requested_at', 'paid_at']), 'minimumPayout' => app(RewardPayoutService::class)->minimum()]);
    }

    public function requestPayout(Request $request, RewardPayoutService $payouts, AnalyticsTracker $analytics) { $payout = $payouts->request($request->user()); $analytics->record('affiliate_payout_requested', ['user_id' => $payout->beneficiary_user_id]); return to_route('creator.dashboard')->with('success', 'Solicitud de pago enviada.'); }
}
