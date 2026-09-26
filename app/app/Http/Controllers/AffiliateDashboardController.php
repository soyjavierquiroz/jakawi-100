<?php

namespace App\Http\Controllers;

use App\Models\Conversion;
use App\Models\RewardTransaction;
use App\Services\AffiliateMetrics;
use App\Services\AnalyticsTracker;
use App\Services\ReferralCodeService;
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

        return Inertia::render('affiliate/dashboard', [
            'referralCode' => $code,
            'metrics' => $metrics->for($affiliate),
            'conversions' => $conversions,
            'rewards' => $rewards,
        ]);
    }
}
