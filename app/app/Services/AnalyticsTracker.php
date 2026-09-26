<?php

namespace App\Services;

use App\Http\Middleware\EnsureVisitorId;
use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
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

    /**
     * Low-level entry point retained for application code that needs a configured event.
     * Metadata is strictly whitelisted per event; request details are never captured.
     *
     * @param  array<string, int|string|null>  $context
     * @param  array<string, string>  $metadata
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
            'user_id', 'visitor_id', 'partner_id', 'location_id', 'benefit_id', 'experience_id', 'redemption_id',
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

    /** @param array<string, string> $metadata */
    private function validateMetadata(string $event, array $metadata): array
    {
        if ($metadata === []) {
            return [];
        }
        $allowed = match ($event) {
            'experience_reserve_click' => ['reservation_method'],
            'maps_click' => ['source'],
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

        return $metadata;
    }
}
