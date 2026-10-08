<?php
namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\LandingPresentation;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\ChallengeMarketingLandingPresenter;
use App\Services\ChallengeRanking;
use App\Services\MediaUrl;
use App\Services\ProductLandingResolver;
use App\Services\SocialEntryState;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingPresentationController extends Controller
{
    public function show(Request $request, LandingPresentation $presentation, AttributionService $attribution, AnalyticsTracker $analytics, ChallengeRanking $ranking, SocialEntryState $state, ChallengeMarketingLandingPresenter $presenter, ProductLandingResolver $resolver)
    {
        abort_unless($presentation->status === 'PUBLISHED', 404);
        return $this->render($request, $presentation, $attribution, $analytics, $ranking, $state, $presenter, $resolver, false);
    }

    public function preview(Request $request, Challenge $challenge, LandingPresentation $presentation, AttributionService $attribution, AnalyticsTracker $analytics, ChallengeRanking $ranking, SocialEntryState $state, ChallengeMarketingLandingPresenter $presenter, ProductLandingResolver $resolver)
    {
        abort_unless($challenge->id === $presentation->subject_id && $presentation->subject_type === $challenge->getMorphClass(), 404);
        return $this->render($request, $presentation, $attribution, $analytics, $ranking, $state, $presenter, $resolver, true);
    }

    private function render(Request $request, LandingPresentation $presentation, AttributionService $attribution, AnalyticsTracker $analytics, ChallengeRanking $ranking, SocialEntryState $state, ChallengeMarketingLandingPresenter $presenter, ProductLandingResolver $resolver, bool $preview)
    {
        $subject = $presentation->subject;
        abort_unless($subject instanceof Challenge && ($preview || $subject->isPublic()), 404);
        if (!$preview) {
            $ref = $request->query('ref');
            $referrer = is_string($ref) ? $attribution->findReferrer($ref) : null;
            $touch = $attribution->recordTouch($request, $referrer, $referrer?->referral_code_normalized, null, $presentation->campaign_key);
            $request->session()->push('attribution_touch_ids', $touch->id);
            $analytics->record('landing_view', ['user_id'=>null,'visitor_id'=>null], ['landing'=>$presentation->slug,'campaign_key'=>$presentation->campaign_key ?? '', 'landing_presentation_id'=>$presentation->id,'subject_type'=>$presentation->subject_type,'subject_id'=>$presentation->subject_id,'default_scope'=>$presentation->default_scope]);
        }
        $props = $preview ? ['challenge'=>$subject,'rewardLabel'=>$subject->benefit?->title,'heroUrl'=>app(MediaUrl::class)->url($subject->hero_path,'hero'),'participation'=>null,'entries'=>[],'grant'=>null,'canParticipate'=>false,'canSubmit'=>false,'isMember'=>false,'slots'=>null,'ranking'=>null,'verificationUrl'=>route('verification.notice')]
            : app(ChallengeController::class)->publicProps($request, $subject, $ranking, $state);
        return Inertia::render('landing-presentations/challenge', [
            ...$props, 'presentation'=>array_replace($presentation->only(['id','name','slug','status','default_scope','campaign_key','hero_alt']), ['hero_alt'=>$presentation->hero_alt ?: $subject->hero_alt]),
            'copy'=>$presenter->present($subject, $presentation),
            'heroUrl'=>app(MediaUrl::class)->url($presentation->hero_path ?: $subject->hero_path,'hero'),
            'nativeUrl'=>$resolver->nativeUrl($subject), 'preview'=>$preview,
            'canonical'=>$presentation->default_scope === 'ALL' ? route('landing-presentations.show',$presentation->slug) : $resolver->nativeUrl($subject),
            'noindex'=>$presentation->default_scope !== 'ALL' || $preview,
        ]);
    }
}
