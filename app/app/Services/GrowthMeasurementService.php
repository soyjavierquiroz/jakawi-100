<?php

namespace App\Services;

use App\Enums\AcquisitionProvider;
use App\Http\Middleware\EnsureVisitorId;
use App\Models\AnalyticsEvent;
use App\Models\AttributionTouch;
use App\Models\LandingPresentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GrowthMeasurementService
{
    public const STAGES = [
        'landing_view' => 'ACQUISITION', 'landing_cta_click' => 'INTENT',
        'signup_completed' => 'ACCOUNT', 'membership_purchase_requested' => 'MEMBERSHIP_INTENT',
        'membership_activated' => 'MEMBERSHIP_CONVERSION', 'benefit_redeemed' => 'PRODUCT_CONVERSION',
        'experience_reserved' => 'PRODUCT_CONVERSION', 'challenge_joined' => 'PRODUCT_CONVERSION',
        'unlock_committed' => 'PRODUCT_CONVERSION',
    ];

    public function __construct(private readonly Request $request) {}

    public function landingView(LandingPresentation $landing, ?AttributionTouch $touch = null): ?AnalyticsEvent
    {
        return $this->record('landing_view', $this->context(null, $touch), [
            'landing' => $landing->slug, 'campaign_key' => $landing->campaign_key,
            'landing_presentation_id' => $landing->id, 'subject_type' => $landing->subject_type,
            'subject_id' => $landing->subject_id, 'default_scope' => $landing->default_scope,
        ], $landing);
    }

    public function cta(LandingPresentation $landing, string $kind, string $location, string $destination, ?string $eventId = null): ?AnalyticsEvent
    {
        return $this->record('landing_cta_click', $this->context(), [
            'cta_kind' => $kind, 'cta_location' => $location,
            'destination' => $this->sanitizeDestination($destination),
        ], $landing, eventId: $eventId);
    }

    public function signupCompleted(Model $user): void
    {
        $this->outcome('signup_completed', $user);
    }

    /** Called only at successful domain creation/state transitions. Capture before afterCommit. */
    public function outcome(string $event, Model $source): void
    {
        try {
            $this->scheduleOutcome($event, $source);
        } catch (Throwable $exception) {
            Log::warning('Growth outcome context could not be resolved.', ['event' => $event, 'exception_type' => get_class($exception)]);
        }
    }

    private function scheduleOutcome(string $event, Model $source): void
    {
        $userId = $event === 'signup_completed' ? $source->id : $source->user_id;
        $touch = null;
        if ($event === 'membership_activated') {
            $request = \App\Models\MembershipPurchaseRequest::where('user_id', $userId)
                ->where('status', \App\Models\MembershipPurchaseRequest::REQUESTED)->whereNotNull('attribution_touch_id')->latest('id')->first();
            $touch = $request ? AttributionTouch::find($request->attribution_touch_id) : null;
        } elseif ($source instanceof \App\Models\MembershipPurchaseRequest) {
            $touch = $source->attribution_touch_id ? AttributionTouch::find($source->attribution_touch_id) : null;
        }
        $context = $this->context($userId, $touch);
        if ($event === 'signup_completed') $context['visitor_id'] = $this->request->attributes->get(EnsureVisitorId::ATTRIBUTE) ?? $context['visitor_id'];
        $context += ['source_type' => $source->getMorphClass(), 'source_id' => $source->id];
        $metadata = match ($event) {
            'benefit_redeemed' => ['benefit_id' => $source->benefit_id, 'partner_id' => $source->partner_id, 'redemption_id' => $source->id],
            'experience_reserved' => ['experience_id' => $source->experience_id, 'session_id' => $source->experience_session_id, 'reservation_id' => $source->id],
            'challenge_joined' => ['challenge_id' => $source->challenge_id, 'participation_id' => $source->id],
            'unlock_committed' => ['unlock_id' => $source->unlock_id, 'participation_id' => $source->id],
            default => [],
        };
        $subject = match ($event) {
            'benefit_redeemed' => [\App\Models\Benefit::class, $source->benefit_id],
            'experience_reserved' => [\App\Models\Experience::class, $source->experience_id],
            'challenge_joined' => [\App\Models\Challenge::class, $source->challenge_id],
            'unlock_committed' => [\App\Models\Unlock::class, $source->unlock_id],
            default => [$source->getMorphClass(), $source->id],
        };
        // Acquisition subject is preserved separately in LandingPresentation; outcome subject is the actual domain.
        $context['subject_type'] = $subject[0];
        $context['subject_id'] = $subject[1];
        $occurredAt = now();
        DB::afterCommit(fn () => $this->record($event, $context, array_filter($metadata, fn ($value) => $value !== null), occurredAt: $occurredAt));
    }

    public function context(?int $userId = null, ?AttributionTouch $touch = null): array
    {
        $actor = $this->request->user()?->id;
        $userId ??= $actor;
        $visitor = $this->request->attributes->get(EnsureVisitorId::ATTRIBUTE);
        // Partner/admin requests must not substitute their visitor for the beneficiary's identity.
        if ($userId !== null && $actor !== $userId) $visitor = null;
        $touch = app(AttributionService::class)->latestApplicableTouch($userId, $visitor, $touch);
        $data = ['visitor_id' => $visitor ?? $touch?->anonymous_id, 'user_id' => $userId,
            'acquisition_provider' => $touch?->acquisition_provider ?? AcquisitionProvider::NONE,
            'attribution_touch_id' => $touch?->id, 'campaign_key' => $touch?->campaign_key,
            'source_route' => $this->request->route()?->getName()];
        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $key) $data['utm_'.$key] = $touch?->{'utm_'.$key};
        $landing = $touch?->metadata['landing_presentation_id'] ?? null;
        if ($landing) {
            $data += ['landing_presentation_id' => $landing, 'landing_slug' => $touch->metadata['landing_slug'] ?? null,
                'subject_type' => $touch->metadata['subject_type'] ?? null, 'subject_id' => $touch->metadata['subject_id'] ?? null, '_default_scope' => $touch->metadata['default_scope'] ?? null];
        }
        return $data;
    }

    public function record(string $event, array $context, array $metadata = [], ?LandingPresentation $landing = null, mixed $occurredAt = null, ?string $eventId = null): ?AnalyticsEvent
    {
        if (! config('jakawi.analytics.enabled')) return null;
        if (in_array($event, ['landing_view', 'landing_cta_click'], true) && app(AcquisitionProviderResolver::class)->suppressed($this->request)) return null;
        if (! isset(self::STAGES[$event])) throw new \InvalidArgumentException('Unknown growth event.');
        $context['campaign_key'] ??= $metadata['campaign_key'] ?? null;
        $context['landing_slug'] ??= $metadata['landing'] ?? null;
        if ($landing) $context = array_replace($context, ['landing_presentation_id' => $landing->id, 'landing_slug' => $landing->slug,
            'subject_type' => $landing->subject_type, 'subject_id' => $landing->subject_id, 'campaign_key' => $landing->campaign_key]);
        $context['campaign_key'] = $this->normalizeCampaignKey($context['campaign_key'] ?? null);
        if (array_key_exists('campaign_key', $metadata)) $metadata['campaign_key'] = $this->normalizeCampaignKey($metadata['campaign_key']);
        $allowed = match ($event) {
            'landing_view' => ['landing', 'campaign_key', 'landing_presentation_id', 'subject_type', 'subject_id', 'default_scope'],
            'landing_cta_click' => ['landing', 'campaign_key', 'destination_type', 'destination_kind', 'cta_kind', 'cta_location', 'destination'],
            'benefit_redeemed' => ['benefit_id', 'partner_id', 'redemption_id'],
            'experience_reserved' => ['experience_id', 'session_id', 'reservation_id'],
            'challenge_joined' => ['challenge_id', 'participation_id'],
            'unlock_committed' => ['unlock_id', 'participation_id'],
            default => [],
        };
        if (array_diff(array_keys($metadata), $allowed)) throw new \InvalidArgumentException('Growth metadata is not allowed.');
        if ($event === 'landing_cta_click') {
            $metadata += ['cta_kind' => ($metadata['destination_type'] ?? null) === 'external' ? 'external' : 'other',
                'cta_location' => 'other', 'destination' => 'other'];
            if (!in_array($metadata['cta_kind'], ['signup', 'membership', 'product_detail', 'redeem', 'reserve', 'participate', 'commit', 'external', 'other'], true)
                || !in_array($metadata['cta_location'], ['hero', 'body', 'final', 'other'], true)) throw new \InvalidArgumentException('Invalid growth CTA category.');
        }
        if (isset($metadata['destination'])) $metadata['destination'] = $this->sanitizeDestination($metadata['destination']);
        $scope = $landing?->default_scope ?? $context['_default_scope'] ?? null;
        $context = array_intersect_key($context, array_flip(['visitor_id', 'user_id', 'attribution_touch_id', 'campaign_key', 'acquisition_provider',
            'source_route', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            'landing_presentation_id', 'landing_slug', 'subject_type', 'subject_id', 'source_type', 'source_id']));
        if (isset($context['landing_presentation_id'])) {
            if ($scope) $metadata['default_scope'] = $scope;
        }
        if ($eventId !== null && ! Str::isUuid($eventId)) return null;
        $data = [...$context, 'event_id' => $eventId ?? (string) Str::uuid(), 'event_name' => $event,
            'metadata' => $metadata ?: null, 'occurred_at' => $occurredAt ?? now()];
        try {
            // Separate transaction/savepoint isolates PostgreSQL failures from valid business operations.
            $recorded = DB::transaction(function () use ($data, $eventId) {
                if ($eventId && ($existing = AnalyticsEvent::where('event_id', $eventId)->first())) {
                    // UUID is an idempotency key, never permission to reuse another actor's event.
                    return $existing->event_name === $data['event_name']
                        && $existing->user_id === ($data['user_id'] ?? null)
                        && $existing->visitor_id === ($data['visitor_id'] ?? null)
                        ? $existing : null;
                }
                $recorded = isset($data['source_id'])
                    ? AnalyticsEvent::firstOrCreate(array_intersect_key($data, array_flip(['event_name', 'source_type', 'source_id'])), $data)
                    : AnalyticsEvent::create($data);
                app(\App\Services\Meta\MetaDeliveryOutbox::class)->enqueueNew($recorded);
                return $recorded;
            });
            if ($recorded && $event === 'landing_view' && $recorded->wasRecentlyCreated) {
                $events = $this->request->attributes->get(\App\Services\Meta\MetaBrowserContext::EVENTS_ATTRIBUTE, []);
                $events[] = $recorded;
                $this->request->attributes->set(\App\Services\Meta\MetaBrowserContext::EVENTS_ATTRIBUTE, $events);
            }
            return $recorded;
        } catch (Throwable $exception) {
            Log::warning('Growth event could not be recorded.', ['event' => $event, 'exception_type' => get_class($exception)]);
            return null;
        }
    }

    private function normalizeCampaignKey(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }

    private function sanitizeDestination(string $destination): string
    {
        if (in_array($destination, ['other', 'external'], true)) return $destination;
        // External URLs can contain phone numbers or tokens in the path; retain category only.
        if (! str_starts_with($destination, '/') || str_starts_with($destination, '//')) return 'external';
        $path = explode('?', explode('#', $destination, 2)[0], 2)[0];
        foreach (['GET', 'POST'] as $method) {
            try {
                $route = app('router')->getRoutes()->match(Request::create($path, $method));
                return '/'.ltrim($route->uri(), '/');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException|\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {}
        }
        return 'other';
    }
}
