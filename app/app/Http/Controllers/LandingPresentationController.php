<?php

namespace App\Http\Controllers;

use App\Enums\LandingTemplate;
use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Unlock;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\BenefitMarketingLandingPresenter;
use App\Services\ChallengeMarketingLandingPresenter;
use App\Services\ChallengeRanking;
use App\Services\ExperienceMarketingLandingPresenter;
use App\Services\GrowthMeasurementService;
use App\Services\LandingPresentationEditor;
use App\Services\LandingVideoConfiguration;
use App\Services\MediaUrl;
use App\Services\ProductLandingResolver;
use App\Services\SocialEntryState;
use App\Services\UnlockMarketingLandingPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingPresentationController extends Controller
{
    public function show(Request $request, LandingPresentation $presentation, AttributionService $attribution, AnalyticsTracker $analytics, ChallengeRanking $ranking, SocialEntryState $state, ChallengeMarketingLandingPresenter $presenter, ProductLandingResolver $resolver)
    {
        abort_unless($presentation->status === 'PUBLISHED', 404);

        return $this->render($request, $presentation, $attribution, $analytics, $ranking, $state, $presenter, $resolver, false);
    }

    public function activation(Request $request, LandingPresentation $presentation)
    {
        abort_unless($presentation->status === 'PUBLISHED', 404);
        app(\App\Services\PublicJourneyContinuation::class)->setLanding($presentation);
        return $this->renderStage($request, $presentation, 'activation');
    }

    public function result(Request $request, LandingPresentation $presentation)
    {
        abort_unless($presentation->status === 'PUBLISHED', 404);
        $subject = $presentation->subject;
        // A result page requires an actual outcome owned by this viewer.
        if ($subject instanceof Experience) {
            abort_unless(\App\Models\ExperienceReservation::where('experience_id', $subject->id)->where('user_id', $request->user()->id)->whereIn('status', ['pending', 'confirmed'])->exists(), 404);
        } elseif ($subject instanceof Challenge) {
            abort_unless($subject->participations()->where('user_id', $request->user()->id)->exists(), 404);
        } elseif ($subject instanceof Unlock) {
            abort_unless($request->user()->unlockParticipations()->where('unlock_id', $subject->id)->whereIn('status', ['COMMITTED', 'UNLOCKED_PENDING_CONFIRMATION', 'CONFIRMED', 'FULFILLED', 'NO_SHOW'])->exists(), 404);
        } else {
            abort(404); // Benefit uses its existing authorized redemption confirmation route.
        }
        return $this->renderStage($request, $presentation, 'result');
    }

    private function renderStage(Request $request, LandingPresentation $presentation, string $stage)
    {
        return $this->render($request, $presentation, app(AttributionService::class), app(AnalyticsTracker::class), app(ChallengeRanking::class), app(SocialEntryState::class), app(ChallengeMarketingLandingPresenter::class), app(ProductLandingResolver::class), false, $stage);
    }

    public function acquisition(Request $request, LandingPresentation $presentation)
    {
        abort_unless($presentation->status === 'PUBLISHED', 404);
        $subject = $presentation->subject;
        $this->assertVisible($subject, false);
        if ($subject instanceof Benefit) {
            app(PublicController::class)->benefitPublicProps($subject, $request);
        }
        $method = $request->validate(['method' => ['required', \Illuminate\Validation\Rule::in(['register', 'login'])]])['method'];
        app(\App\Services\PublicJourneyContinuation::class)->setLanding($presentation);
        if (! ($subject instanceof Challenge)) {
            app(AnalyticsTracker::class)->journeyIntentStarted($subject);
            app(AnalyticsTracker::class)->journeyAuthStarted($subject, $method);
        }

        return response()->noContent();
    }

    private function assertVisible($subject, bool $preview): void
    {
        abort_unless(($subject instanceof Unlock && ($preview || in_array($subject->status, [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED], true))) || $subject instanceof Experience || $subject instanceof Benefit || ($subject instanceof Challenge && ($preview || $subject->isPublic())), 404);
    }

    public function preview(Request $request, string $subjectSlug, LandingPresentation $presentation, AttributionService $attribution, AnalyticsTracker $analytics, ChallengeRanking $ranking, SocialEntryState $state, ChallengeMarketingLandingPresenter $presenter, ProductLandingResolver $resolver)
    {
        $subject = $request->route()->hasParameter('unlock') ? Unlock::where('slug', $request->route('unlock'))->firstOrFail() : ($request->route()->hasParameter('experience') ? Experience::where('slug', $request->route('experience'))->firstOrFail() : ($request->route()->hasParameter('benefit') ? Benefit::where('slug', $request->route('benefit'))->firstOrFail() : Challenge::where('slug', $request->route('challenge'))->firstOrFail()));
        abort_unless($subject->id === $presentation->subject_id && $presentation->subject_type === $subject->getMorphClass(), 404);

        return $this->render($request, $presentation, $attribution, $analytics, $ranking, $state, $presenter, $resolver, true);
    }

    private function render(Request $request, LandingPresentation $presentation, AttributionService $attribution, AnalyticsTracker $analytics, ChallengeRanking $ranking, SocialEntryState $state, ChallengeMarketingLandingPresenter $presenter, ProductLandingResolver $resolver, bool $preview, ?string $stage = null)
    {
        $subject = $presentation->subject;
        $this->assertVisible($subject, $preview);
        $benefitProps = $subject instanceof Benefit ? app(PublicController::class)->benefitPublicProps($subject, $request, $preview) : null;
        if (! $stage && ! $preview && ! str_contains(strtolower($request->header('Purpose', '').$request->header('Sec-Purpose', '')), 'prefetch') && ! $request->header('X-Inertia-Partial-Component')) {
            $ref = $request->query('ref');
            $referrer = is_string($ref) ? $attribution->findReferrer($ref) : null;
            $touch = $attribution->recordLandingTouch($request, $referrer, $presentation->campaign_key);
            if ($touch) {
                $request->session()->push('attribution_touch_ids', $touch->id);
                $touch->update(['metadata' => ['landing_presentation_id' => $presentation->id, 'landing_slug' => $presentation->slug,
                    'subject_type' => $presentation->subject_type, 'subject_id' => $presentation->subject_id, 'default_scope' => $presentation->default_scope]]);
            }
            app(GrowthMeasurementService::class)->landingView($presentation, $touch);
        }
        $resumed = ! $preview && $request->user() && $request->session()->get('landing_acquisition_resumed') === $presentation->id;
        $shared = [
            'conversionStage' => $stage,
            'landingUrl' => route('landing-presentations.show', $presentation, false),
            'vsl' => ! ($subject instanceof Unlock) && in_array($presentation->media_mode?->value, ['IMAGE', 'NONE'], true) ? null : app(LandingVideoConfiguration::class)->forPresentation($presentation),
            'presentation' => $presentation->only(['id', 'name', 'slug', 'status', 'default_scope', 'campaign_key', 'hero_alt', 'template_key', 'marketing_theme', 'media_mode', 'hero_layout', 'density']),
            'selectedLocationId' => $resumed ? $request->session()->get('landing_continuation_context.location_id') : null,
            'acquisitionResumed' => (bool) $resumed,
            'nativeUrl' => $resolver->nativeUrl($subject), 'preview' => $preview,
            'canonical' => $presentation->default_scope === 'ALL' ? route('landing-presentations.show', $presentation->slug) : $resolver->nativeUrl($subject),
            'noindex' => $presentation->default_scope !== 'ALL' || $preview,
        ];
        if ($subject instanceof Unlock) {
            $copy = app(UnlockMarketingLandingPresenter::class)->present($subject, $presentation, $request);
            if (! $preview) {
                $analytics->resetJourneyIntent($subject);
                $analytics->unlock($subject, 'unlock_viewed');
                if ($copy['action']['kind'] === 'membership') {
                    $analytics->journeyMembershipGateViewed($subject);
                }
            }

            return Inertia::render($stage ? 'landing-presentations/conversion' : 'landing-presentations/unlock', [
                ...$shared,
                'unlock' => app(UnlockController::class)->data($subject, true),
                'landingDirectAction' => ! $stage && $request->user() && $copy['action']['kind'] === 'native' && $copy['action']['label'] === 'SUMARME' && $subject->jp_deposit == 0 ? ['endpoint' => route('unlocks.commit', $subject, false), 'data' => ['landing_presentation_id' => $presentation->id]] : null,
                'participation' => $request->user()?->unlockParticipations()->where('unlock_id', $subject->id)->first()?->only(['status']),
                'hasActiveMembership' => $request->user()?->hasActiveMembership() ?? false,
                'jpBalance' => $request->user() ? app(\App\Services\JpBalanceService::class)->for($request->user()) : null,
                'copy' => $copy, 'acquisition' => app(\App\Services\LandingAcquisition::class)->present($presentation, $copy),
            ]);
        }
        if ($subject instanceof Experience) {
            $experienceProps = app(PublicController::class)->experiencePublicProps($subject, $request);
            if ($stage === 'result') {
                $reservation = \App\Models\ExperienceReservation::where('user_id', $request->user()->id)->whereHas('session', fn ($q) => $q->where('experience_id', $subject->id))->whereIn('status', ['pending', 'confirmed'])->when($request->integer('session'), fn ($q) => $q->where('experience_session_id', $request->integer('session')))->latest()->firstOrFail();
                $session = $reservation->session()->with('location')->firstOrFail();
                $experienceProps['experience']['sessions'] = [$session->only(['id', 'starts_at', 'ends_at', 'venue_label', 'capacity', 'status']) + ['location' => $session->location?->only(['slug', 'name', 'address', 'maps_url']), 'reservation' => $reservation->only(['public_id', 'status', 'party_size'])]];
                $experienceProps['restoredSessionId'] = $reservation->experience_session_id;
            }
            return Inertia::render($stage ? 'landing-presentations/conversion' : 'landing-presentations/experience', [
                ...$experienceProps, ...$shared,
                'landingDirectAction' => ! $stage && $request->user() && $experienceProps['hasActiveMembership'] && $experienceProps['availability']['available'] && $subject->reservation_method === 'jakawi' && count($experienceProps['experience']['sessions']) === 1 && ! $experienceProps['experience']['sessions'][0]['reservation'] ? ['endpoint' => route('experiences.reservations.store', $subject, false), 'data' => ['experience_session_id' => $experienceProps['experience']['sessions'][0]['id'], 'landing_presentation_id' => $presentation->id]] : null,
                ...$this->offerCopy($presentation, app(ExperienceMarketingLandingPresenter::class)->present($subject, $presentation, $request)),
                'restoredSessionId' => $stage === 'result' ? $experienceProps['restoredSessionId'] : (($resumed ? $request->session()->get('landing_continuation_context.experience_session_id') : null) ?? $experienceProps['restoredSessionId']),
                'heroUrl' => $presentation->media_mode?->value === 'NONE' || $presentation->media_mode?->value === 'VSL' ? null : app(MediaUrl::class)->url($presentation->hero_path ?: $subject->cover_path ?: $subject->image_path, 'hero'),
            ]);
        }
        if ($subject instanceof Benefit) {
            return Inertia::render($stage ? 'landing-presentations/conversion' : 'landing-presentations/benefit', [
                ...$benefitProps, ...$shared,
                'landingDirectAction' => ! $stage && $request->user() && $benefitProps['hasActiveMembership'] && $benefitProps['availability']['available'] && count($benefitProps['locations']) === 1 ? ['endpoint' => route('redemptions.start', $subject, false), 'data' => ['location_id' => $benefitProps['locations'][0]['id']]] : null,
                ...$this->offerCopy($presentation, app(BenefitMarketingLandingPresenter::class)->present($subject, $presentation, $request, $benefitProps)),
                'heroUrl' => $presentation->media_mode?->value === 'NONE' || $presentation->media_mode?->value === 'VSL' ? null : app(MediaUrl::class)->url($presentation->hero_path ?: $subject->image_path, 'hero'),
            ]);
        }
        $props = app(ChallengeController::class)->publicProps($request, $subject, $ranking, $state);

        return Inertia::render($stage ? 'landing-presentations/conversion' : 'landing-presentations/challenge', [
            ...$props, ...$shared,
            'landingDirectAction' => ! $stage && $request->user() && $props['canSubmit'] && $props['canParticipate'] && ! $props['participation'] && $subject->evidence_type === 'MANUAL' ? ['endpoint' => route('social-challenges.intent', $subject, false), 'data' => ['landing_presentation_slug' => $presentation->slug]] : null,
            'presentation' => array_replace($presentation->only(['id', 'name', 'slug', 'status', 'default_scope', 'campaign_key', 'hero_alt', 'template_key', 'marketing_theme', 'media_mode', 'hero_layout', 'density']), ['hero_alt' => $presentation->hero_alt ?: $subject->hero_alt]),
            ...$this->offerCopy($presentation, $presenter->present($subject, $presentation)),
            'heroUrl' => $presentation->media_mode?->value === 'NONE' || $presentation->media_mode?->value === 'VSL' ? null : app(MediaUrl::class)->url($presentation->hero_path ?: $subject->hero_path, 'hero'),
        ]);
    }

    private function offerCopy(LandingPresentation $presentation, array $copy): array
    {
        // Null supports records created through legacy/domain code; V1 has one allowlisted renderer.
        abort_if($presentation->template_key && $presentation->template_key !== LandingTemplate::OFFER_CONVERSION, 404);

        $copy = app(LandingPresentationEditor::class)->effectiveCopy($presentation, $copy);

        return ['acquisition' => app(\App\Services\LandingAcquisition::class)->present($presentation, $copy), 'copy' => $copy, 'offerEditorial' => app(LandingPresentationEditor::class)->publicEditorial($presentation, $copy)];
    }
}
