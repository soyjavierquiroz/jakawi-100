<?php
namespace App\Http\Controllers;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Unlock;
class ReferralController extends Controller {
 public function open(Request $request, string $code, AttributionService $attribution, AnalyticsTracker $analytics): RedirectResponse { $referrer = $attribution->findReferrer($code); if (! $referrer) return to_route('home'); $touch = $attribution->recordTouch($request, $referrer, $referrer->referral_code_normalized); $request->session()->push('attribution_touch_ids', $touch->id); $analytics->record('anonymous_session_created'); $analytics->record('referral_link_opened'); return redirect()->route('home', $request->only(['utm_source','utm_medium','utm_campaign','utm_content','utm_term'])); }
 public function unlock(Request $request, string $code, Unlock $unlock, AttributionService $attribution, AnalyticsTracker $analytics): RedirectResponse { $referrer = $attribution->findReferrer($code); if (! $referrer || ! in_array($unlock->status, [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED], true)) return to_route('home'); if ($request->user()?->id === $referrer->id) return to_route('unlocks.show', $unlock); $touch = $attribution->recordTouch($request, $referrer, $referrer->referral_code_normalized, $unlock); $request->session()->push('attribution_touch_ids', $touch->id); $analytics->unlock($unlock, 'unlock_referral_opened'); return to_route('unlocks.show', $unlock, $request->only(['utm_source','utm_medium','utm_campaign','utm_content','utm_term'])); }
}
