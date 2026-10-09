<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AttributionTouch;
use App\Models\Campaign;
use App\Models\CampaignParticipant;
use App\Models\RewardRule;
use App\Models\User;
use App\Services\ConversionRecorder;
use App\Services\RewardResolver;
use App\Support\PublicJourneyConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class PublicCampaignJourneysTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_landing_captures_attribution_and_keeps_campaign_key_server_side(): void
    {
        $referrer = User::factory()->create(['referral_code_normalized' => 'REF123']);
        $visitor = 'cecc2e8f-2a74-4c33-b92a-4ce5a913c4bc';
        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)
            ->get('/test-public-journey?campaign_key=forged&utm_source=meta&utm_medium=paid&utm_campaign=meta-october&utm_content=ad1&utm_term=seminar&fbclid=%20FB123%20&ttclid=TT123&gclid=GG123&ref=REF123')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('public-journeys/show')
                ->where('landing.primary_cta.href', '/register')->where('landing.key', 'test-public-journey'));

        $touch = AttributionTouch::sole();
        $this->assertSame('test-journey', $touch->campaign_key);
        $this->assertSame('FB123', $touch->fbclid);
        $this->assertSame('TT123', $touch->ttclid);
        $this->assertSame('GG123', $touch->gclid);
        $this->assertSame('meta', $touch->utm_source);
        $this->assertSame('paid', $touch->utm_medium);
        $this->assertSame('meta-october', $touch->utm_campaign);
        $this->assertSame('ad1', $touch->utm_content);
        $this->assertSame('seminar', $touch->utm_term);
        $this->assertSame('REF123', $touch->referral_code);
        $this->assertSame($referrer->id, $touch->referrer_user_id);
        $this->assertSame($visitor, $touch->anonymous_id);
        $this->assertSame('/test-public-journey', $touch->landing_page);

        $view = AnalyticsEvent::where('event_name', 'landing_view')->sole();
        $this->assertSame($visitor, $view->visitor_id);
        $this->assertSame(['landing' => 'test-public-journey', 'campaign_key' => 'test-journey'], $view->metadata);
    }

    public function test_inactive_and_unknown_landings_are_not_public_and_existing_routes_are_untouched(): void
    {
        $this->get('/test-inactive-journey')->assertNotFound();
        $this->get('/unknown-public-journey')->assertNotFound();
        $this->get('/partners')->assertOk();
        $this->get('/beneficios')->assertOk();
        $this->assertSame(0, AttributionTouch::where('campaign_key', 'test-inactive')->count());
    }

    public function test_landing_cta_tracks_safe_metadata_and_internal_target_is_validated(): void
    {
        $this->post('/analytics/public-landings/test-public-journey/cta')->assertNoContent();
        $event = AnalyticsEvent::where('event_name', 'landing_cta_click')->sole();
        $this->assertEquals(['landing' => 'test-public-journey', 'campaign_key' => 'test-journey', 'destination_type' => 'internal', 'cta_kind' => 'signup', 'cta_location' => 'other', 'destination' => '/register'], $event->metadata);

        $definition = config('public_journeys.landings.test-public-journey');
        $definition['primary_cta']['path'] = '//evil.example';
        $this->expectException(InvalidArgumentException::class);
        app(PublicJourneyConfig::class)->cta($definition);
    }

    public function test_explicit_route_collision_fails_clearly(): void
    {
        config()->set('public_journeys.landings', ['collision' => [
            ...config('public_journeys.landings.test-public-journey'), 'key' => 'collision', 'path' => '/partners',
        ]]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('/partners');
        app(PublicJourneyConfig::class)->registerRoutes();
    }

    public function test_campaign_key_alone_never_selects_economic_campaign_or_reward_rule(): void
    {
        // Both visits can arrive within one persisted second, even without test-order contamination.
        $this->freezeTime();
        // Exercise a sequential plan rather than relying on incidental index ordering for ties.
        DB::statement('SET LOCAL enable_indexscan = off');
        DB::statement('SET LOCAL enable_bitmapscan = off');
        $user = User::factory()->create();
        $campaign = Campaign::create(['name' => 'Economic', 'code' => 'test-journey', 'status' => 'active', 'event' => 'membership_purchased']);
        CampaignParticipant::create(['campaign_id' => $campaign->id, 'participant_type' => 'CREATOR']);
        $base = ['participant_type' => 'CREATOR', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'currency' => 'BOB', 'status' => 'active'];
        $global = RewardRule::create([...$base, 'name' => 'global', 'value' => 10]);
        $specific = RewardRule::create([...$base, 'name' => 'campaign', 'campaign_id' => $campaign->id, 'value' => 20]);

        $this->actingAs($user)->get('/test-public-journey?utm_campaign=meta-seminar')->assertOk();
        $first = app(ConversionRecorder::class)->record($user, [
            'idempotency_key' => 'public-journey-isolated', 'type' => 'membership_purchased', 'product_key' => 'jakawi_annual',
            'gross_amount' => 100, 'status' => 'confirmed',
        ]);
        $this->assertNull($first->campaign_id);
        $firstTouch = AttributionTouch::findOrFail($first->attribution_touch_id);
        $this->assertSame('test-journey', $firstTouch->campaign_key);
        $this->assertSame('meta-seminar', $firstTouch->utm_campaign);
        $this->assertSame($global->id, app(RewardResolver::class)->ruleFor($user, 'USER', 'CREATOR', 'membership_purchased', 'jakawi_annual')?->id);

        $this->get('/test-public-journey?utm_campaign=test-journey')->assertOk();
        $secondTouch = AttributionTouch::where('user_id', $user->id)->orderByDesc('id')->firstOrFail();
        $this->assertNotSame($firstTouch->id, $secondTouch->id);
        $this->assertTrue($firstTouch->occurred_at->equalTo($secondTouch->occurred_at));
        $this->assertSame('test-journey', $secondTouch->campaign_key);
        $this->assertSame('test-journey', $secondTouch->utm_campaign);
        // A higher ID with an older timestamp must not override chronological priority.
        AttributionTouch::create(['user_id' => $user->id, 'campaign_key' => 'test-journey',
            'utm_campaign' => 'meta-seminar', 'occurred_at' => now()->subMinute()]);
        $second = app(ConversionRecorder::class)->record($user, [
            'idempotency_key' => 'public-journey-intentional', 'type' => 'membership_purchased', 'product_key' => 'jakawi_annual',
            'gross_amount' => 100, 'status' => 'confirmed',
        ]);
        $this->assertSame($secondTouch->id, $second->attribution_touch_id);
        $this->assertSame('test-journey', $second->attribution_snapshot['utm_campaign']);
        $this->assertSame($campaign->id, $second->campaign_id);
        $this->assertSame($specific->id, app(RewardResolver::class)->ruleFor($user, 'USER', 'CREATOR', 'membership_purchased', 'jakawi_annual', 'CASH', $second->campaign_id)?->id);
    }

    public function test_go_uses_only_configured_destination_and_emits_no_sensitive_metadata(): void
    {
        $this->get('/test-external-journey')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landing.primary_cta.href', '/go/test-website'));
        $this->post('/analytics/public-landings/test-external-journey/cta')->assertNoContent();
        $this->get('/go/test-website?url=https://evil.example&to=https://evil.example&redirect=https://evil.example')
            ->assertRedirect('https://example.org/tickets');
        $event = AnalyticsEvent::where('event_name', 'external_redirect')->sole();
        $this->assertEqualsCanonicalizing(['redirect_slug' => 'test-website', 'destination_type' => 'url', 'campaign_key' => 'test-external', 'landing' => 'test-external-journey'], $event->metadata);
        $this->assertStringNotContainsString('example.org', json_encode($event->metadata));
        $this->assertSame('external', AnalyticsEvent::where('event_name', 'landing_cta_click')->sole()->metadata['destination_type']);
    }

    public function test_go_rejects_unknown_inactive_expired_and_unsafe_destinations(): void
    {
        $this->get('/go/unknown')->assertNotFound();
        $base = config('public_journeys.redirects.test-website');
        foreach ([
            ['status' => 'inactive'], ['expires_at' => now()->subMinute()->toDateTimeString()],
            ['destination' => '//evil.example'], ['destination' => 'javascript:alert(1)'],
            ['destination' => 'data:text/plain,evil'], ['destination' => 'http://example.org'],
            ['destination' => 'https://user:secret@example.org/path'],
        ] as $override) {
            config()->set('public_journeys.redirects.test-website', [...$base, ...$override]);
            $this->get('/go/test-website')->assertNotFound();
        }
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'external_redirect')->count());
    }
}
