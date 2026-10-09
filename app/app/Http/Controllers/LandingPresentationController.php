<?php
namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Unlock;
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

    public function preview(Request $request, string $subjectSlug, LandingPresentation $presentation, AttributionService $attribution, AnalyticsTracker $analytics, ChallengeRanking $ranking, SocialEntryState $state, ChallengeMarketingLandingPresenter $presenter, ProductLandingResolver $resolver)
    {
        $subject = $request->route()->hasParameter('unlock') ? Unlock::where('slug', $request->route('unlock'))->firstOrFail() : ($request->route()->hasParameter('experience') ? Experience::where('slug', $request->route('experience'))->firstOrFail() : ($request->route()->hasParameter('benefit') ? Benefit::where('slug', $request->route('benefit'))->firstOrFail() : Challenge::where('slug', $request->route('challenge'))->firstOrFail()));
        abort_unless($subject->id === $presentation->subject_id && $presentation->subject_type === $subject->getMorphClass(), 404);
        return $this->render($request, $presentation, $attribution, $analytics, $ranking, $state, $presenter, $resolver, true);
    }

    private function render(Request $request, LandingPresentation $presentation, AttributionService $attribution, AnalyticsTracker $analytics, ChallengeRanking $ranking, SocialEntryState $state, ChallengeMarketingLandingPresenter $presenter, ProductLandingResolver $resolver, bool $preview)
    {
        $subject = $presentation->subject;
        abort_unless(($subject instanceof Unlock && ($preview || in_array($subject->status, [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED], true))) || $subject instanceof Experience || $subject instanceof Benefit || ($subject instanceof Challenge && ($preview || $subject->isPublic())), 404);
        $benefitProps = $subject instanceof Benefit ? app(PublicController::class)->benefitPublicProps($subject, $request, $preview) : null;
        if (!$preview && !str_contains(strtolower($request->header('Purpose', '').$request->header('Sec-Purpose', '')), 'prefetch') && !$request->header('X-Inertia-Partial-Component')) {
            $ref = $request->query('ref');
            $referrer = is_string($ref) ? $attribution->findReferrer($ref) : null;
            $touch = $attribution->recordLandingTouch($request, $referrer, $presentation->campaign_key);
            if ($touch) {
                $request->session()->push('attribution_touch_ids', $touch->id);
                $touch->update(['metadata' => ['landing_presentation_id' => $presentation->id, 'landing_slug' => $presentation->slug,
                    'subject_type' => $presentation->subject_type, 'subject_id' => $presentation->subject_id, 'default_scope' => $presentation->default_scope]]);
            }
            app(\App\Services\GrowthMeasurementService::class)->landingView($presentation, $touch);
        }
        $shared = [
            'presentation'=>$presentation->only(['id','name','slug','status','default_scope','campaign_key','hero_alt']),
            'nativeUrl'=>$resolver->nativeUrl($subject), 'preview'=>$preview,
            'canonical'=>$presentation->default_scope === 'ALL' ? route('landing-presentations.show',$presentation->slug) : $resolver->nativeUrl($subject),
            'noindex'=>$presentation->default_scope !== 'ALL' || $preview,
        ];
        if ($subject instanceof Unlock) {
            $copy = app(\App\Services\UnlockMarketingLandingPresenter::class)->present($subject, $presentation, $request);
            if (!$preview) {
                $analytics->resetJourneyIntent($subject);
                $analytics->unlock($subject, 'unlock_viewed');
                if ($copy['action']['kind'] === 'membership') $analytics->journeyMembershipGateViewed($subject);
            }
            return Inertia::render('landing-presentations/unlock', [
                ...$shared,
                'copy'=>$copy,
            ]);
        }
        if ($subject instanceof Experience) {
            return Inertia::render('landing-presentations/experience', [
                ...$shared,
                'copy'=>app(\App\Services\ExperienceMarketingLandingPresenter::class)->present($subject, $presentation, $request),
                'heroUrl'=>app(MediaUrl::class)->url($presentation->hero_path ?: $subject->cover_path ?: $subject->image_path, 'hero'),
            ]);
        }
        if ($subject instanceof Benefit) {
            return Inertia::render('landing-presentations/benefit', [
                ...$benefitProps, ...$shared,
                'copy'=>app(\App\Services\BenefitMarketingLandingPresenter::class)->present($subject, $presentation, $request, $benefitProps),
                'heroUrl'=>app(MediaUrl::class)->url($presentation->hero_path ?: $subject->image_path, 'hero'),
            ]);
        }
        $props = $preview ? ['challenge'=>$subject,'rewardLabel'=>$subject->benefit?->title,'heroUrl'=>app(MediaUrl::class)->url($subject->hero_path,'hero'),'participation'=>null,'entries'=>[],'grant'=>null,'canParticipate'=>false,'canSubmit'=>false,'isMember'=>false,'slots'=>null,'ranking'=>null,'verificationUrl'=>route('verification.notice')]
            : app(ChallengeController::class)->publicProps($request, $subject, $ranking, $state);
        return Inertia::render('landing-presentations/challenge', [
            ...$props, ...$shared, 'presentation'=>array_replace($presentation->only(['id','name','slug','status','default_scope','campaign_key','hero_alt']), ['hero_alt'=>$presentation->hero_alt ?: $subject->hero_alt]),
            'copy'=>$presenter->present($subject, $presentation),
            'heroUrl'=>app(MediaUrl::class)->url($presentation->hero_path ?: $subject->hero_path,'hero'),
        ]);
    }
}
