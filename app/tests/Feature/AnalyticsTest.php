<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureVisitorId;
use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use App\Services\AnalyticsTracker;
use App\Services\MembershipService;
use App\Services\RedemptionService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_has_json_metadata_occurred_at_and_nullable_foreign_keys(): void
    {
        $schema = DB::getSchemaBuilder();
        $this->assertTrue($schema->hasTable('analytics_events'));
        foreach (['event_name', 'user_id', 'visitor_id', 'partner_id', 'location_id', 'benefit_id', 'experience_id', 'redemption_id', 'metadata', 'occurred_at'] as $column) {
            $this->assertTrue($schema->hasColumn('analytics_events', $column));
        }

        $event = AnalyticsEvent::create(['event_name' => 'home_view', 'metadata' => ['source' => 'location_detail'], 'occurred_at' => now()]);
        $this->assertSame(['source' => 'location_detail'], $event->metadata);
        $this->assertInstanceOf(CarbonInterface::class, $event->occurred_at);
    }

    public function test_domain_deletion_nulls_analytics_foreign_keys_without_deleting_event(): void
    {
        [$partner, $location, $benefit, $experience] = $this->context();
        $user = User::factory()->create();
        $event = AnalyticsEvent::create([
            'event_name' => 'benefit_view', 'user_id' => $user->id, 'partner_id' => $partner->id,
            'location_id' => $location->id, 'benefit_id' => $benefit->id, 'experience_id' => $experience->id,
            'occurred_at' => now(),
        ]);
        $benefit->delete();
        $location->delete();
        $experience->delete();
        $partner->delete();
        $user->delete();
        $event->refresh();
        $this->assertNull($event->user_id);
        $this->assertNull($event->partner_id);
        $this->assertNull($event->location_id);
        $this->assertNull($event->benefit_id);
        $this->assertNull($event->experience_id);
    }

    public function test_tracker_records_view_contexts_and_never_copies_domain_data_into_metadata(): void
    {
        [$partner, $location, $benefit, $experience] = $this->context();
        $tracker = $this->trackerWithVisitor('11111111-1111-4111-8111-111111111111');
        $tracker->homeViewed();
        $tracker->partnerViewed($partner);
        $tracker->locationViewed($location);
        $tracker->benefitViewed($benefit);
        $tracker->experienceViewed($experience);

        $this->assertDatabaseCount('analytics_events', 5);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'location_view', 'location_id' => $location->id, 'partner_id' => $partner->id]);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'benefit_view', 'benefit_id' => $benefit->id, 'partner_id' => $partner->id]);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'experience_view', 'experience_id' => $experience->id, 'partner_id' => null]);
        $this->assertNull(AnalyticsEvent::where('event_name', 'benefit_view')->firstOrFail()->metadata);
    }

    public function test_tracker_validates_events_metadata_and_can_be_disabled(): void
    {
        $tracker = $this->trackerWithVisitor('11111111-1111-4111-8111-111111111111');
        $this->expectException(InvalidArgumentException::class);
        $tracker->record('unknown_event');
    }

    public function test_disabled_tracker_and_metadata_whitelist_do_not_accept_arbitrary_data(): void
    {
        $tracker = $this->trackerWithVisitor('11111111-1111-4111-8111-111111111111');
        try {
            $tracker->record('home_view', [], ['email' => 'private@example.test']);
            $this->fail('Arbitrary analytics metadata was accepted.');
        } catch (InvalidArgumentException) {
        }
        config(['jakawi.analytics.enabled' => false]);
        $this->assertNull($tracker->homeViewed());
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_visitor_middleware_generates_preserves_and_replaces_ids(): void
    {
        config(['session.secure' => true]);
        $middleware = app(EnsureVisitorId::class);
        $request = Request::create('/');
        $response = $middleware->handle($request, function (Request $request): Response {
            $this->assertTrue(Str::isUuid($request->attributes->get(EnsureVisitorId::ATTRIBUTE)));

            return new Response;
        });
        $cookie = $response->headers->getCookies()[0];
        $this->assertTrue(Str::isUuid($cookie->getValue()));
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));

        $existing = '22222222-2222-4222-8222-222222222222';
        $request = Request::create('/', 'GET', [], [config('jakawi.analytics.visitor_cookie') => $existing]);
        $response = $middleware->handle($request, fn () => new Response);
        $this->assertSame($existing, $request->attributes->get(EnsureVisitorId::ATTRIBUTE));
        $this->assertCount(0, $response->headers->getCookies());

        $request = Request::create('/', 'GET', [], [config('jakawi.analytics.visitor_cookie') => 'not-a-uuid']);
        $middleware->handle($request, fn () => new Response);
        $this->assertTrue(Str::isUuid($request->attributes->get(EnsureVisitorId::ATTRIBUTE)));
    }

    public function test_authenticated_request_records_both_user_and_visitor(): void
    {
        $user = User::factory()->create();
        $tracker = $this->trackerWithVisitor('33333333-3333-4333-8333-333333333333', $user);
        $tracker->homeViewed();
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'home_view', 'user_id' => $user->id, 'visitor_id' => '33333333-3333-4333-8333-333333333333']);
    }

    public function test_click_events_only_allow_safe_small_metadata(): void
    {
        [, $location,, $experience] = $this->context();
        $tracker = $this->trackerWithVisitor('44444444-4444-4444-8444-444444444444');
        $tracker->experienceReserveClicked($experience, 'whatsapp');
        $tracker->mapsClicked($location, 'experience_detail');
        $tracker->whatsappClicked($location);
        $this->assertSame(['reservation_method' => 'whatsapp'], AnalyticsEvent::where('event_name', 'experience_reserve_click')->firstOrFail()->metadata);
        $this->assertSame(['source' => 'experience_detail'], AnalyticsEvent::where('event_name', 'maps_click')->firstOrFail()->metadata);
        $this->assertNull(AnalyticsEvent::where('event_name', 'whatsapp_click')->firstOrFail()->metadata);
    }

    public function test_redemption_events_are_transactional_and_idempotent(): void
    {
        [$user, $benefit, $location] = $this->redeemable();
        $service = app(RedemptionService::class);
        $pending = $service->start($user, $benefit, $location);
        $again = $service->start($user, $benefit, $location);
        $this->assertSame($pending->id, $again->id);
        $this->assertDatabaseCount('analytics_events', 1);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'redeem_started', 'user_id' => $user->id, 'partner_id' => $benefit->partner_id, 'location_id' => $location->id, 'benefit_id' => $benefit->id, 'redemption_id' => $pending->id]);

        try {
            $service->confirm($pending->code, '000000');
            $this->fail('Wrong PIN accepted.');
        } catch (DomainException) {
        }
        $this->assertDatabaseCount('analytics_events', 1);
        $service->confirm($pending->code, '123456');
        $service->confirm($pending->code, '999999');
        $this->assertDatabaseCount('analytics_events', 2);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'redeem_confirmed', 'redemption_id' => $pending->id]);

        [, $invalidBenefit, $invalidLocation] = $this->redeemable();
        $invalidLocation->update(['status' => 'draft']);
        try {
            $service->start($user, $invalidBenefit, $invalidLocation);
            $this->fail('Invalid location accepted.');
        } catch (DomainException) {
        }
        $this->assertDatabaseCount('analytics_events', 2);
    }

    /** @return array{Partner, Location, Benefit, Experience} */
    private function context(): array
    {
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create();
        $experience = Experience::factory()->published()->create();
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);

        return [$partner, $location, $benefit, $experience];
    }

    private function trackerWithVisitor(string $visitorId, ?User $user = null): AnalyticsTracker
    {
        $request = Request::create('/');
        $request->attributes->set(EnsureVisitorId::ATTRIBUTE, $visitorId);
        $request->setUserResolver(fn () => $user);
        $this->app->instance(Request::class, $request);

        return app(AnalyticsTracker::class);
    }

    /** @return array{User, Benefit, Location} */
    private function redeemable(): array
    {
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        $user = User::factory()->create();
        app(MembershipService::class)->activate($user, User::factory()->create());

        return [$user, $benefit, $location];
    }
}
