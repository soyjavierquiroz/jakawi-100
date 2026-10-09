<?php

namespace Tests\Feature;

use App\Enums\AcquisitionProvider;
use App\Models\{AnalyticsEvent, AttributionTouch, Benefit, Challenge, ChallengeParticipation, GrowthProviderDelivery, LandingPresentation, User};
use App\Services\GrowthMeasurementService;
use App\Services\Meta\{MetaBrowserContext, MetaConversionsApiClient, MetaDeliveryDispatcher, MetaDeliveryOutbox, MetaEventMapper, MetaEventPayloadFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{DB, Http, Log, Schema};
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MetaProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['meta.enabled' => true, 'meta.browser_enabled' => true, 'meta.capi_enabled' => true,
            'meta.pixel_id' => '123456789', 'meta.access_token' => 'FAKE-SERVER-TOKEN',
            'meta.api_version' => 'v99.0', 'meta.test_event_code' => '', 'app.url' => 'https://jakawi.example']);
    }

    private function landing(): LandingPresentation
    {
        return Benefit::factory()->published()->create()->landingPresentations()->create([
            'name' => 'Meta', 'slug' => 'meta-test', 'status' => 'PUBLISHED', 'default_scope' => 'NONE',
        ]);
    }

    private function canonical(string $name = 'landing_view', AcquisitionProvider $provider = AcquisitionProvider::META): AnalyticsEvent
    {
        return app(GrowthMeasurementService::class)->record($name, ['acquisition_provider' => $provider,
            'visitor_id' => '11111111-1111-4111-8111-111111111111', 'landing_slug' => 'meta-test',
            'subject_type' => Benefit::class, 'subject_id' => 123]);
    }

    public function test_defaults_are_disabled_and_missing_configuration_fails_closed(): void
    {
        $defaults = require config_path('meta.php');
        $this->assertFalse($defaults['enabled']);
        $this->assertFalse($defaults['browser_enabled']);
        $this->assertFalse($defaults['capi_enabled']);
        $this->assertSame('', $defaults['access_token']);
        foreach ([['meta.enabled', false], ['meta.pixel_id', ''], ['meta.pixel_id', 'invalid']] as [$key, $value]) {
            config([$key => $value]);
            $event = $this->canonical();
            $this->assertNull(app(MetaBrowserContext::class)->descriptor($event));
            $this->assertDatabaseCount('growth_provider_deliveries', 0);
            config(['meta.enabled' => true, 'meta.pixel_id' => '123456789']);
        }
        foreach (['access_token', 'api_version'] as $field) {
            $previous = config('meta.'.$field);
            config(['meta.'.$field => '']);
            $this->canonical();
            $this->assertDatabaseCount('growth_provider_deliveries', 0);
            $this->assertSame(0, app(MetaDeliveryDispatcher::class)->run());
            config(['meta.'.$field => $previous]);
        }
        config(['meta.api_version' => 'not-a-version']);
        $this->canonical();
        $this->assertDatabaseCount('growth_provider_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_provider_gate_and_independent_channels(): void
    {
        foreach ([AcquisitionProvider::NONE, AcquisitionProvider::TIKTOK, AcquisitionProvider::GOOGLE] as $provider) {
            $event = $this->canonical(provider: $provider);
            $this->assertNull(app(MetaBrowserContext::class)->descriptor($event));
            $this->assertFalse(app(MetaConversionsApiClient::class)->send($event)['sent']);
        }
        $this->assertDatabaseCount('growth_provider_deliveries', 0);
        config(['meta.browser_enabled' => false]);
        $event = $this->canonical();
        $this->assertNull(app(MetaBrowserContext::class)->descriptor($event));
        $this->assertDatabaseCount('growth_provider_deliveries', 1);
        config(['meta.browser_enabled' => true, 'meta.capi_enabled' => false]);
        $event = $this->canonical();
        $this->assertNotNull(app(MetaBrowserContext::class)->descriptor($event));
        $this->assertDatabaseCount('growth_provider_deliveries', 1);
        config(['meta.capi_enabled' => true, 'jakawi.analytics.enabled' => false]);
        $this->assertNull(app(MetaBrowserContext::class)->descriptor($event));
        $this->assertSame(0, app(MetaDeliveryDispatcher::class)->run());
        Http::assertNothingSent();
    }

    public function test_all_nine_mappings_and_server_outcomes_preserve_canonical_id_and_time(): void
    {
        Http::fake(['https://graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $expected = ['landing_view' => 'ViewContent', 'landing_cta_click' => 'JakawiLandingCTA',
            'signup_completed' => 'CompleteRegistration', 'membership_purchase_requested' => 'Lead',
            'membership_activated' => 'JakawiMembershipActivated', 'benefit_redeemed' => 'JakawiBenefitRedeemed',
            'experience_reserved' => 'JakawiExperienceReserved', 'challenge_joined' => 'JakawiChallengeJoined',
            'unlock_committed' => 'JakawiUnlockCommitted'];
        foreach ($expected as $canonical => $meta) {
            $event = $this->canonical($canonical);
            $this->assertSame($meta, app(MetaEventMapper::class)->map($canonical)['name']);
            $this->assertNotSame('Purchase', $meta);
            $this->assertDatabaseHas('growth_provider_deliveries', ['analytics_event_id' => $event->id, 'status' => 'PENDING']);
            $this->assertSame(1, app(MetaDeliveryDispatcher::class)->run());
            Http::assertSent(fn ($request) => $request['data'][0]['event_name'] === $meta
                && $request['data'][0]['event_id'] === $event->event_id
                && $request['data'][0]['event_time'] === $event->occurred_at->getTimestamp()
                && $request['data'][0]['action_source'] === 'website');
        }
        $this->assertSame(9, GrowthProviderDelivery::where('status', 'SENT')->count());
        $this->assertNull(app(MetaEventMapper::class)->map('Purchase'));
        Http::assertSentCount(9);
    }

    public function test_landing_browser_descriptor_and_capi_share_name_id_and_safe_data(): void
    {
        $this->landing();
        $this->get('/l/meta-test?acq=meta&email=private@example.test&token=PRIVATE')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('metaBrowser.provider', 'META')
                ->where('metaBrowser.pixel_id', '123456789')
                ->where('metaBrowser.events.0.event_name', 'ViewContent')
                ->where('metaBrowser.events.0.event_id', AnalyticsEvent::where('event_name', 'landing_view')->sole()->event_id));
        $event = AnalyticsEvent::where('event_name', 'landing_view')->sole();
        $browser = app(MetaBrowserContext::class)->descriptor($event);
        $server = app(MetaEventPayloadFactory::class)->server($event);
        $this->assertSame($browser['event_id'], $server['event_id']);
        $this->assertSame($browser['event_name'], $server['event_name']);
        $this->assertSame((array) $browser['custom_data'], $server['custom_data']);
        $this->assertStringNotContainsString('FAKE-SERVER-TOKEN', json_encode($browser));
        $this->assertDatabaseCount('growth_provider_deliveries', 1);
        Http::assertNothingSent();
    }

    public function test_cta_uuid_is_idempotent_and_provider_is_server_authoritative(): void
    {
        $this->landing();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/l/meta-test?acq=meta')->assertOk();
        $id = (string) Str::uuid();
        $payload = ['event_id' => $id, 'cta_kind' => 'signup', 'cta_location' => 'hero', 'destination' => '/register?email=PRIVATE'];
        $this->postJson('/analytics/landing-presentations/meta-test/cta', $payload)->assertNoContent();
        $this->postJson('/analytics/landing-presentations/meta-test/cta', $payload)->assertNoContent();
        $event = AnalyticsEvent::where('event_name', 'landing_cta_click')->sole();
        $this->assertSame($id, $event->event_id);
        $this->assertSame(AcquisitionProvider::META, $event->acquisition_provider);
        $this->assertSame($id, app(MetaBrowserContext::class)->descriptor($event)['event_id']);
        $server = app(MetaEventPayloadFactory::class)->server($event);
        $this->assertSame('JakawiLandingCTA', $server['event_name']);
        $this->assertSame($id, $server['event_id']);
        $this->assertSame('signup', $server['custom_data']['cta_kind']);
        $this->assertSame('hero', $server['custom_data']['cta_location']);
        $this->assertDatabaseCount('growth_provider_deliveries', 2);
        $this->postJson('/analytics/landing-presentations/meta-test/cta', ['event_id' => 'invalid'] + array_diff_key($payload, ['event_id' => true]))->assertUnprocessable();
        $this->get('/l/meta-test?utm_source=newsletter')->assertOk();
        $this->postJson('/analytics/landing-presentations/meta-test/cta', ['event_id' => (string) Str::uuid(), 'acquisition_provider' => 'META'] + array_diff_key($payload, ['event_id' => true]))->assertNoContent();
        $new = AnalyticsEvent::where('event_name', 'landing_cta_click')->latest('id')->firstOrFail();
        $this->assertSame(AcquisitionProvider::NONE, $new->acquisition_provider);
        $this->assertNull(app(MetaBrowserContext::class)->descriptor($new));
        $this->assertDatabaseCount('growth_provider_deliveries', 2);
        Http::assertNothingSent();
    }

    public function test_disabled_events_do_not_become_backlog_on_idempotent_replay(): void
    {
        config(['meta.capi_enabled' => false]);
        $user = User::factory()->create();
        $event = app(GrowthMeasurementService::class)->record('signup_completed', ['acquisition_provider' => AcquisitionProvider::META,
            'visitor_id' => (string) Str::uuid(), 'source_type' => User::class, 'source_id' => $user->id]);
        config(['meta.capi_enabled' => true]);
        $again = app(GrowthMeasurementService::class)->record('signup_completed', ['acquisition_provider' => AcquisitionProvider::META,
            'source_type' => User::class, 'source_id' => $user->id]);
        $this->assertSame($event->id, $again->id);
        $this->assertDatabaseCount('growth_provider_deliveries', 0);
    }

    public function test_outbox_unique_and_transaction_rollback(): void
    {
        $event = $this->canonical();
        app(MetaDeliveryOutbox::class)->enqueueNew($event);
        $this->assertDatabaseCount('growth_provider_deliveries', 1);
        try {
            DB::transaction(fn () => GrowthProviderDelivery::create(['analytics_event_id' => $event->id, 'provider' => AcquisitionProvider::META, 'channel' => 'SERVER']));
            $this->fail('Duplicate accepted');
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {}
        try {
            DB::transaction(function () {
                $this->canonical('signup_completed');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {}
        $this->assertDatabaseCount('growth_provider_deliveries', 1);
        $this->assertDatabaseCount('analytics_events', 1);
    }

    public function test_retries_retain_event_id_and_use_finite_backoff(): void
    {
        $this->freezeTime();
        $event = $this->canonical();
        Http::fake(['https://graph.facebook.com/*' => Http::sequence()->push(['error' => ['message' => 'FAKE-SERVER-TOKEN PRIVATE']], 429)
            ->push([], 500)->push(['events_received' => 1], 200)]);
        $dispatcher = app(MetaDeliveryDispatcher::class);
        $this->assertSame(1, $dispatcher->run());
        $delivery = GrowthProviderDelivery::sole();
        $this->assertSame('RETRY', $delivery->status);
        $this->assertSame(now()->addMinute()->getTimestamp(), $delivery->next_attempt_at->getTimestamp());
        $this->assertSame(0, $dispatcher->run());
        $this->travel(1)->minutes(); $dispatcher->run();
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertSame(now()->addMinutes(5)->getTimestamp(), $delivery->fresh()->next_attempt_at->getTimestamp());
        $this->travel(5)->minutes(); $dispatcher->run();
        $this->assertSame('SENT', $delivery->fresh()->status);
        $this->assertSame(0, $dispatcher->run());
        foreach (Http::recorded() as [$request]) $this->assertSame($event->event_id, $request['data'][0]['event_id']);
        $this->assertStringNotContainsString('FAKE-SERVER-TOKEN', $delivery->fresh()->toJson());
        $this->assertDatabaseCount('growth_provider_deliveries', 1);
    }

    public function test_timeouts_retry_permanent_errors_die_and_attempt_limit_is_finite(): void
    {
        $this->canonical();
        $mode = 'timeout';
        Http::fake(function () use (&$mode) {
            if ($mode === 'timeout') throw new ConnectionException('SECRET TOKEN fake connection failure');
            return Http::response(['error' => ['message' => 'SECRET TOKEN']], $mode);
        });
        app(MetaDeliveryDispatcher::class)->run();
        $delivery = GrowthProviderDelivery::sole();
        $this->assertSame('RETRY', $delivery->status);
        $this->assertSame('connection', $delivery->last_error_code);
        $delivery->update(['attempts' => 5, 'next_attempt_at' => now()]);
        app(MetaDeliveryDispatcher::class)->run();
        $this->assertSame('DEAD', $delivery->fresh()->status);
        $this->assertSame(6, $delivery->fresh()->attempts);
        foreach ([400, 401, 403, 422] as $status) {
            $event = $this->canonical();
            $mode = $status;
            app(MetaDeliveryDispatcher::class)->run();
            $dead = GrowthProviderDelivery::where('analytics_event_id', $event->id)->sole();
            $this->assertSame('DEAD', $dead->status);
            $this->assertSame($status, $dead->last_http_status);
            $this->assertSame('permanent_http', $dead->last_error_code);
        }
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_payload_is_minimal_private_and_test_code_stays_server_side(): void
    {
        $event = $this->canonical('landing_cta_click');
        $event->update(['metadata' => ['cta_kind' => 'signup', 'cta_location' => 'hero', 'email' => 'PRIVATE@example.test',
            'phone' => '71234567', 'name' => 'Private Name', 'description' => 'Private description', 'url' => 'https://social.example/private?token=SECRET']]);
        $payloads = app(MetaEventPayloadFactory::class);
        $data = $payloads->server($event);
        $this->assertSame('https://jakawi.example/l/meta-test', $data['event_source_url']);
        $this->assertSame(['external_id' => [hash('sha256', 'jakawi:meta:v1:visitor:11111111-1111-4111-8111-111111111111')]], $data['user_data']);
        $this->assertSame($data['user_data'], $payloads->server($event)['user_data']);
        foreach (['PRIVATE', '71234567', 'Private Name', 'Private description', 'SECRET', '11111111-1111-4111-8111-111111111111'] as $private) $this->assertStringNotContainsString($private, json_encode($data));
        $this->assertArrayNotHasKey('value', $data['custom_data']);
        $this->assertArrayNotHasKey('currency', $data['custom_data']);
        $this->assertArrayNotHasKey('fbp', $data['user_data']);
        $this->assertArrayNotHasKey('fbc', $data['user_data']);
        $event->update(['landing_slug' => 'bad?email=PRIVATE', 'visitor_id' => null, 'user_id' => User::factory()->create()->id]);
        $this->assertSame('https://jakawi.example/', $payloads->server($event)['event_source_url']);
        $this->assertSame(hash('sha256', 'jakawi:meta:v1:user:'.$event->user_id), $payloads->server($event)['user_data']['external_id'][0]);
        config(['meta.test_event_code' => 'FAKE_TEST_CODE']);
        Http::fake(['https://graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        app(MetaConversionsApiClient::class)->send($event);
        Http::assertSent(fn ($request) => $request['test_event_code'] === 'FAKE_TEST_CODE'
            && $request->hasHeader('Authorization', 'Bearer FAKE-SERVER-TOKEN')
            && ! str_contains($request->url(), 'FAKE-SERVER-TOKEN'));
        $this->assertStringNotContainsString('FAKE_TEST_CODE', json_encode(app(MetaBrowserContext::class)->descriptor($event)));
    }

    public function test_continuity_switching_prefetch_preview_and_partial_traffic(): void
    {
        $landing = $this->landing();
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        $this->get('/l/meta-test')->assertOk()->assertInertia(fn (Assert $page) => $page->where('metaBrowser', null));
        $this->assertDatabaseCount('growth_provider_deliveries', 0);
        $this->get('/l/meta-test?acq=meta')->assertOk();
        $this->get('/l/meta-test')->assertOk()->assertInertia(fn (Assert $page) => $page->where('metaBrowser.provider', 'META'));
        $this->assertDatabaseCount('attribution_touches', 1);
        $this->assertDatabaseCount('growth_provider_deliveries', 2);
        foreach (['Purpose', 'Sec-Purpose'] as $header) {
            $this->withHeader($header, 'prefetch')->get('/l/meta-test?acq=meta')->assertOk()->assertInertia(fn (Assert $page) => $page->where('metaBrowser', null));
            $this->flushHeaders();
        }
        $this->get('/admin/beneficios/'.$landing->subject->slug.'/landings/meta-test/preview?acq=meta')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('metaBrowser', null));
        $this->assertDatabaseCount('growth_provider_deliveries', 2);
        $this->get('/l/meta-test?utm_source=newsletter')->assertOk()->assertInertia(fn (Assert $page) => $page->where('metaBrowser', null));
        $this->get('/l/meta-test?acq=meta')->assertOk();
        $this->get('/l/meta-test?acq=tiktok')->assertOk()->assertInertia(fn (Assert $page) => $page->where('metaBrowser', null));
        $this->get('/l/meta-test')->assertOk()->assertInertia(fn (Assert $page) => $page->where('metaBrowser', null));
        $this->assertDatabaseCount('growth_provider_deliveries', 3);
        Http::assertNothingSent();
    }

    public function test_delivery_failure_does_not_rollback_domain_or_change_economics(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $challenge = Challenge::create(['title' => 'Meta challenge', 'slug' => 'meta-challenge', 'status' => 'open', 'review_status' => 'APPROVED',
            'description' => 'Test', 'instructions' => 'Test', 'evidence_type' => 'MANUAL', 'reward_type' => 'JP', 'reward_jp_amount' => 25]);
        AttributionTouch::create(['user_id' => $user->id, 'anonymous_id' => (string) Str::uuid(), 'occurred_at' => now(), 'acquisition_provider' => AcquisitionProvider::META]);
        Schema::rename('growth_provider_deliveries', 'growth_provider_deliveries_unavailable');
        try {
            DB::transaction(fn () => ChallengeParticipation::create(['user_id' => $user->id, 'challenge_id' => $challenge->id]));
        } finally {
            Schema::rename('growth_provider_deliveries_unavailable', 'growth_provider_deliveries');
        }
        $this->assertDatabaseCount('challenge_participations', 1);
        $this->assertSame(AcquisitionProvider::META, AnalyticsEvent::where('event_name', 'challenge_joined')->sole()->acquisition_provider);
        $second = $challenge->replicate();
        $second->slug = 'meta-challenge-http';
        $second->save();
        $participation = DB::transaction(fn () => ChallengeParticipation::create(['user_id' => $user->id, 'challenge_id' => $second->id]));
        Http::fake(['https://graph.facebook.com/*' => Http::response([], 503)]);
        $this->assertSame(1, app(MetaDeliveryDispatcher::class)->run());
        $this->assertDatabaseCount('challenge_participations', 2);
        $this->assertDatabaseHas('challenge_participations', ['id' => $participation->id]);
        $this->assertSame('RETRY', GrowthProviderDelivery::sole()->status);
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('conversions', 0);
        $this->assertDatabaseCount('jp_holds', 0);
        $this->assertDatabaseCount('reward_transactions', 0);
        Http::assertSentCount(1);
    }

    public function test_delivery_logging_never_exposes_response_or_token(): void
    {
        Log::spy();
        $event = $this->canonical();
        Http::fake(['https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'FAKE-SERVER-TOKEN PRIVATE@example.test']], 400)]);
        app(MetaDeliveryDispatcher::class)->run();
        Log::shouldHaveReceived('info')->once()->with('Meta delivery processed.', \Mockery::on(fn ($context) => $context === [
            'event_id' => $event->event_id, 'delivery_id' => GrowthProviderDelivery::sole()->id, 'status' => 'DEAD', 'http_status' => 400,
        ]));
        $this->assertStringNotContainsString('FAKE-SERVER-TOKEN', GrowthProviderDelivery::sole()->toJson());
        $this->assertStringNotContainsString('PRIVATE@example.test', GrowthProviderDelivery::sole()->toJson());
    }
    public function test_dispatcher_kill_switch_does_not_send_pending_and_no_history_is_scanned(): void
    {
        $event = $this->canonical();
        config(['meta.enabled' => false]);
        $this->assertSame(0, app(MetaDeliveryDispatcher::class)->run());
        $this->assertSame('PENDING', GrowthProviderDelivery::sole()->status);
        config(['meta.enabled' => true, 'meta.access_token' => '']);
        $this->assertSame(0, app(MetaDeliveryDispatcher::class)->run());
        $this->assertSame(0, GrowthProviderDelivery::sole()->attempts);
        Http::assertNothingSent();
    }

    public function test_missing_identity_or_invalid_success_response_fails_closed(): void
    {
        $event = $this->canonical();
        $event->update(['visitor_id' => null, 'user_id' => null]);
        app(MetaDeliveryDispatcher::class)->run();
        $this->assertSame('DEAD', GrowthProviderDelivery::sole()->status);
        Http::assertNothingSent();
        $event = $this->canonical();
        Http::fake(['https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'PRIVATE']], 200)]);
        app(MetaDeliveryDispatcher::class)->run();
        $this->assertDatabaseHas('growth_provider_deliveries', ['analytics_event_id' => $event->id,
            'status' => 'DEAD', 'last_error_code' => 'invalid_response']);
    }

    public function test_outbox_storage_is_minimal_and_dispatcher_is_scheduled_every_minute(): void
    {
        $columns = Schema::getColumnListing('growth_provider_deliveries');
        foreach (['payload', 'access_token', 'response', 'email', 'phone', 'user_data'] as $forbidden) $this->assertNotContains($forbidden, $columns);
        $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'growth_provider_deliveries'");
        $this->assertCount(4, $indexes); // Primary key plus the three contractual indexes.
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $event = collect($schedule->events())->first(fn ($event) => str_contains($event->command, 'growth:dispatch-meta'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_real_signup_uses_the_guest_canonical_identity_and_server_only_delivery(): void
    {
        $this->landing();
        $this->get('/l/meta-test?acq=meta')->assertOk();
        $view = AnalyticsEvent::where('event_name', 'landing_view')->sole();
        $this->withUnencryptedCookie(config('jakawi.analytics.visitor_cookie'), $view->visitor_id);
        $this->post('/register', ['name' => 'Private Name', 'email' => 'meta-signup@example.test', 'whatsapp' => '71234567'])->assertRedirect();
        $signup = AnalyticsEvent::where('event_name', 'signup_completed')->sole();
        $this->assertSame(AcquisitionProvider::META, $signup->acquisition_provider);
        $this->assertSame($view->visitor_id, $signup->visitor_id);
        $factory = app(MetaEventPayloadFactory::class);
        $this->assertSame($factory->server($view)['user_data'], $factory->server($signup)['user_data']);
        Http::fake(['https://graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $this->assertSame(2, app(MetaDeliveryDispatcher::class)->run());
        Http::assertSent(fn ($request) => $request['data'][0]['event_name'] === 'CompleteRegistration'
            && $request['data'][0]['event_id'] === $signup->event_id);
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('conversions', 0);
        $this->assertStringNotContainsString('meta-signup@example.test', json_encode($factory->server($signup)));
    }

}
