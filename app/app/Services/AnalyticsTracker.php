<?php

namespace App\Services;

use App\Http\Middleware\EnsureVisitorId;
use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\Unlock;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AnalyticsTracker
{
    public function __construct(private readonly Request $request) {}

    public function homeViewed(): ?AnalyticsEvent
    {
        return $this->record('home_view');
    }

    public function partnerViewed(Partner $partner): ?AnalyticsEvent
    {
        return $this->record('partner_view', ['partner_id' => $partner->id]);
    }

    public function locationViewed(Location $location): ?AnalyticsEvent
    {
        return $this->record('location_view', ['location_id' => $location->id, 'partner_id' => $location->partner_id]);
    }

    public function benefitViewed(Benefit $benefit): ?AnalyticsEvent
    {
        return $this->record('benefit_view', ['benefit_id' => $benefit->id, 'partner_id' => $benefit->partner_id]);
    }

    public function experienceViewed(Experience $experience): ?AnalyticsEvent
    {
        return $this->record('experience_view', ['experience_id' => $experience->id]);
    }

    public function unlock(Unlock $unlock, string $event): ?AnalyticsEvent
    {
        return $this->record($event, ['unlock_id' => $unlock->id, 'partner_id' => $unlock->partner_id]);
    }

    public function redemptionStarted(Redemption $redemption): ?AnalyticsEvent
    {
        return $this->recordRedemption('redeem_started', $redemption);
    }

    public function redemptionConfirmed(Redemption $redemption): ?AnalyticsEvent
    {
        return $this->recordRedemption('redeem_confirmed', $redemption);
    }

    public function experienceReserveClicked(Experience $experience, string $reservationMethod): ?AnalyticsEvent
    {
        return $this->record('experience_reserve_click', ['experience_id' => $experience->id], [
            'reservation_method' => $reservationMethod,
        ]);
    }

    public function mapsClicked(Location $location, ?string $source = null): ?AnalyticsEvent
    {
        return $this->record('maps_click', ['location_id' => $location->id, 'partner_id' => $location->partner_id],
            $source === null ? [] : ['source' => $source]);
    }

    public function whatsappClicked(Partner|Location $contact): ?AnalyticsEvent
    {
        return $contact instanceof Location
            ? $this->record('whatsapp_click', ['location_id' => $contact->id, 'partner_id' => $contact->partner_id])
            : $this->record('whatsapp_click', ['partner_id' => $contact->id]);
    }

    public function experienceWhatsappClicked(Experience $experience): ?AnalyticsEvent
    {
        return $this->record('whatsapp_click', ['experience_id' => $experience->id]);
    }

    public function journeyIntentStarted(Experience|Unlock|Benefit $resource): ?AnalyticsEvent
    {
        [$journey, $action, $context] = $this->journeyResource($resource);
        $key = "journey_intent.{$journey}.{$resource->id}.".($this->request->user()?->id ?? 'guest');
        if ($this->request->session()->get($key) === true) {
            return null;
        }
        $this->request->session()->put($key, true);
        $user = $this->request->user();
        $city = $this->journeyCity($resource);

        return $this->record('journey_intent_started', $context, [
            'journey' => $journey, 'action' => $action, 'resource_id' => $resource->id,
            'resource_slug' => $resource->slug,
            ...($city ? ['city' => $city] : []),
            'auth_state' => $user ? 'authenticated' : 'guest',
            ...($user ? ['membership_state' => $user->hasActiveMembership() ? 'active' : 'inactive'] : []),
        ]);
    }

    public function resetJourneyIntent(Experience|Unlock|Benefit $resource): void
    {
        [$journey] = $this->journeyResource($resource);
        $this->request->session()->forget("journey_intent.{$journey}.{$resource->id}.".($this->request->user()?->id ?? 'guest'));
    }

    public function journeyAuthStarted(Experience|Unlock|Benefit $resource, string $destination): ?AnalyticsEvent
    {
        [$journey, $action, $context] = $this->journeyResource($resource);

        return $this->record('journey_auth_started', $context, [
            'journey' => $journey, 'action' => $action, 'resource_id' => $resource->id,
            'auth_destination' => $destination,
        ]);
    }

    /** @param array{journey:string, action:string, resource_id:int|string, context?:array<string,mixed>} $intent */
    public function journeyAuthReturned(array $intent, string $method): ?AnalyticsEvent
    {
        if (! in_array($intent['journey'], ['EXPERIENCE', 'UNLOCK', 'BENEFIT'], true)) {
            return null;
        }

        return $this->record('journey_auth_returned', [$this->journeyContextKey($intent['journey']) => $intent['resource_id']], [
            'journey' => $intent['journey'], 'action' => $intent['action'], 'resource_id' => $intent['resource_id'],
            'auth_method' => $method,
            'has_session_context' => isset($intent['context']['experience_session_id']),
        ]);
    }

    public function journeyMembershipGateViewed(Experience|Unlock|Benefit $resource): ?AnalyticsEvent
    {
        [$journey, $action, $context] = $this->journeyResource($resource);

        return $this->record('journey_membership_gate_viewed', $context, [
            'journey' => $journey, 'action' => $action, 'resource_id' => $resource->id,
            'membership_state' => 'inactive',
        ]);
    }

    public function journeyExternalExit(Experience $experience, string $method): ?AnalyticsEvent
    {
        return $this->record('journey_external_exit', ['experience_id' => $experience->id], [
            'journey' => 'EXPERIENCE', 'action' => 'RESERVE', 'resource_id' => $experience->id,
            'destination_type' => $method === 'whatsapp' ? 'whatsapp' : ($method === 'phone' ? 'phone' : 'url'),
        ]);
    }

    /** @return array{string,string,array<string,int>} */
    private function journeyResource(Experience|Unlock|Benefit $resource): array
    {
        return match (true) {
            $resource instanceof Experience => ['EXPERIENCE', 'RESERVE', ['experience_id' => $resource->id]],
            $resource instanceof Unlock => ['UNLOCK', 'COMMIT', ['unlock_id' => $resource->id]],
            default => ['BENEFIT', 'REDEEM', ['benefit_id' => $resource->id]],
        };
    }

    private function journeyContextKey(string $journey): string
    {
        return match ($journey) {
            'EXPERIENCE' => 'experience_id', 'UNLOCK' => 'unlock_id', 'BENEFIT' => 'benefit_id',
        };
    }

    private function journeyCity(Experience|Unlock|Benefit $resource): ?string
    {
        $locations = match (true) {
            $resource instanceof Experience => $resource->upcomingSessions()->with('location.cityEntity')->get()->pluck('location')->filter(),
            $resource instanceof Unlock => $resource->locations()->with('cityEntity')->get(),
            default => $resource->availableLocations()->with('cityEntity')->get(),
        };
        $cities = $locations->map(fn ($location) => $location->cityEntity?->slug ?? $location->city)
            ->filter(fn ($city) => is_string($city) && filled($city))->unique()->values();

        return $cities->count() === 1 ? $cities->first() : null;
    }

    /**
     * Low-level entry point retained for application code that needs a configured event.
     * Metadata is strictly whitelisted per event; request details are never captured.
     *
     * @param  array<string, int|string|null>  $context
     * @param  array<string, bool|int|string>  $metadata
     */
    public function record(string $event, array $context = [], array $metadata = []): ?AnalyticsEvent
    {
        if (! config('jakawi.analytics.enabled')) {
            return null;
        }
        if (! in_array($event, config('jakawi.analytics.events'), true)) {
            throw new InvalidArgumentException('Analytics event is not configured.');
        }

        $data = array_intersect_key($context, array_flip([
            'user_id', 'visitor_id', 'partner_id', 'location_id', 'benefit_id', 'experience_id', 'unlock_id', 'redemption_id',
        ]));
        $data += $this->requestContext();
        $data['event_name'] = $event;
        $data['metadata'] = $this->validateMetadata($event, $metadata) ?: null;
        $data['occurred_at'] = now();

        try {
            return AnalyticsEvent::create($data);
        } catch (QueryException $exception) {
            Log::warning('Analytics event could not be recorded.', ['event' => $event, 'exception' => $exception->getMessage()]);

            return null;
        }
    }

    private function recordRedemption(string $event, Redemption $redemption): ?AnalyticsEvent
    {
        return $this->record($event, [
            'user_id' => $redemption->user_id,
            'partner_id' => $redemption->partner_id,
            'location_id' => $redemption->location_id,
            'benefit_id' => $redemption->benefit_id,
            'redemption_id' => $redemption->id,
            // Domain transactions deliberately do not read HTTP visitor context.
            'visitor_id' => null,
        ]);
    }

    /** @return array<string, int|string|null> */
    private function requestContext(): array
    {
        $user = $this->request->user();

        return [
            'user_id' => $user instanceof User ? $user->id : null,
            'visitor_id' => $this->request->attributes->get(EnsureVisitorId::ATTRIBUTE),
        ];
    }

    /** @param array<string, bool|int|string> $metadata */
    private function validateMetadata(string $event, array $metadata): array
    {
        if ($metadata === []) {
            return [];
        }
        $allowed = match ($event) {
            'experience_reserve_click' => ['reservation_method'],
            'journey_intent_started' => ['journey', 'action', 'resource_id', 'resource_slug', 'city', 'auth_state', 'membership_state'],
            'journey_auth_started' => ['journey', 'action', 'resource_id', 'auth_destination'],
            'journey_auth_returned' => ['journey', 'action', 'resource_id', 'auth_method', 'has_session_context'],
            'journey_membership_gate_viewed' => ['journey', 'action', 'resource_id', 'membership_state'],
            'journey_external_exit' => ['journey', 'action', 'resource_id', 'destination_type'],
            'maps_click' => ['source'],
            'city_viewed', 'city_interest_recorded' => ['city_id', 'city_slug', 'authenticated'],
            'partner_application_started', 'partner_application_submitted' => ['city_id', 'city_slug'],
            'program_application_submitted' => ['program', 'landing'],
            'membership_assistance_requested', 'membership_purchase_confirmed', 'membership_returned_to_intent' => ['journey', 'action', 'resource_id', 'campaign_key', 'request_status'],
            'landing_view' => ['landing', 'campaign_key'],
            'landing_cta_click' => ['landing', 'campaign_key', 'destination_type', 'destination_kind'],
            'external_redirect' => ['redirect_slug', 'campaign_key', 'destination_type', 'landing'],
            'opportunity_impression', 'opportunity_opened' => ['opportunity_type', 'source_id', 'city_id', 'city_slug', 'surface', 'section', 'position', 'category'],
            'social_challenge_view', 'social_challenge_intent_started', 'social_participation_submitted', 'social_participation_validation_result', 'social_challenge_qualified' => ['challenge_id', 'qualification_mode', 'platform', 'result', 'reward_type', 'metric'],
            'social_challenge_reward_granted' => ['challenge_id', 'qualification_mode', 'platform', 'result', 'reward_type', 'metric', 'jp_amount'],
            default => [],
        };
        if (array_diff(array_keys($metadata), $allowed) !== []) {
            throw new InvalidArgumentException('Analytics metadata is not allowed for this event.');
        }
        if (isset($metadata['reservation_method']) && ! in_array($metadata['reservation_method'], config('jakawi.reservation_methods'), true)) {
            throw new InvalidArgumentException('Reservation method is not allowed.');
        }
        if (isset($metadata['source']) && ! in_array($metadata['source'], config('jakawi.analytics.maps_sources'), true)) {
            throw new InvalidArgumentException('Analytics source is not allowed.');
        }
        foreach (['journey' => ['EXPERIENCE', 'UNLOCK', 'BENEFIT'], 'action' => ['RESERVE', 'COMMIT', 'REDEEM'],
            'auth_state' => ['guest', 'authenticated'], 'membership_state' => ['active', 'inactive'],
            'auth_destination' => ['register', 'login'], 'auth_method' => ['register', 'login'],
            'has_session_context' => [true, false], 'destination_type' => ['whatsapp', 'url', 'phone', 'internal', 'external'],
            'destination_kind' => ['experience']] as $key => $values) {
            if (isset($metadata[$key]) && ! in_array($metadata[$key], $values, true)) {
                throw new InvalidArgumentException('Analytics metadata value is not allowed.');
            }
        }
        $destinationTypes = match ($event) {
            'landing_cta_click' => ['internal', 'external'],
            'external_redirect', 'journey_external_exit' => ['whatsapp', 'url', 'phone'],
            default => null,
        };
        if ($destinationTypes !== null && isset($metadata['destination_type'])
            && ! in_array($metadata['destination_type'], $destinationTypes, true)) {
            throw new InvalidArgumentException('Analytics destination type is not allowed for this event.');
        }

        return $metadata;
    }
}
