<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\City;
use App\Services\AnalyticsTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DiscoveryAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_opportunity_events_allow_only_discovery_metadata(): void
    {
        $tracker = app(AnalyticsTracker::class);
        $metadata = [
            'opportunity_type' => 'BENEFIT', 'source_id' => '42', 'city_id' => '7', 'city_slug' => 'cochabamba',
            'surface' => 'HOME', 'section' => 'HERO', 'position' => '0', 'category' => 'food',
        ];

        $tracker->record('opportunity_impression', [], $metadata);
        $tracker->record('opportunity_opened', [], $metadata);

        $this->assertDatabaseCount('analytics_events', 2);
        $this->assertEquals($metadata, AnalyticsEvent::where('event_name', 'opportunity_impression')->firstOrFail()->metadata);
        $this->expectException(InvalidArgumentException::class);
        $tracker->record('opportunity_opened', [], $metadata + ['email' => 'private@example.test']);
    }

    public function test_observation_endpoint_uses_canonical_selected_city_and_has_no_economic_side_effects(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $city->update(['status' => City::ACTIVE]);
        $otherCity = City::factory()->create(['slug' => 'other-city', 'status' => City::ACTIVE]);

        $this->withCookie('selected_city', $city->slug)->postJson('/analytics/opportunities', [
            'event' => 'opportunity_impression',
            'opportunity_type' => 'BENEFIT',
            'source_id' => '42',
            'surface' => 'HOME',
            'section' => 'FOR_YOU',
            'position' => 0,
            'category' => 'food',
            'city_id' => $otherCity->id,
            'email' => 'private@example.test',
        ])->assertNoContent();

        $event = AnalyticsEvent::sole();
        $this->assertEquals([
            'opportunity_type' => 'BENEFIT', 'source_id' => '42', 'city_id' => (string) $city->id,
            'city_slug' => $city->slug, 'surface' => 'HOME', 'section' => 'FOR_YOU', 'position' => '0', 'category' => 'food',
        ], $event->metadata);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertDatabaseCount('unlock_participations', 0);
    }

    public function test_endpoint_accepts_open_for_search_and_omits_unlock_category(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $city->update(['status' => City::ACTIVE]);

        $this->withCookie('selected_city', $city->slug)->postJson('/analytics/opportunities', [
            'event' => 'opportunity_opened', 'opportunity_type' => 'UNLOCK', 'source_id' => '9',
            'surface' => 'SEARCH', 'section' => 'RESULTS', 'position' => 3,
        ])->assertNoContent();

        $this->assertEquals([
            'opportunity_type' => 'UNLOCK', 'source_id' => '9', 'city_id' => (string) $city->id,
            'city_slug' => $city->slug, 'surface' => 'SEARCH', 'section' => 'RESULTS', 'position' => '3',
        ], AnalyticsEvent::sole()->metadata);

        $this->withCookie('selected_city', $city->slug)->postJson('/analytics/opportunities', [
            'event' => 'opportunity_opened', 'opportunity_type' => 'UNLOCK', 'source_id' => '9',
            'surface' => 'SEARCH', 'section' => 'RESULTS', 'position' => 3, 'category' => 'food',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('analytics_events', 1);
    }
}
