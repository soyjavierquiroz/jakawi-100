<?php

namespace Tests\Feature\Commercial;

use App\Models\AttributionTouch;
use App\Models\Benefit;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\ReferralRelationship;
use App\Models\User;
use App\Services\Commercial\CommercialDispatcher;
use App\Services\Commercial\CommercialOutbox;
use App\Services\PublicJourneyContinuation;
use App\Services\RedemptionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommercialTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['cities'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['commercial.enabled' => true, 'commercial.secret' => str_repeat('c', 64), 'crm.enabled' => false]);
        Http::preventStrayRequests();
    }

    private function pilot(bool $origin = true): array
    {
        $partner = Partner::factory()->published()->create(['slug' => 'dossis43']);
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        $landing = $benefit->landingPresentations()->create(['name' => 'Pilot', 'slug' => config('commercial.pilot_landing'), 'status' => 'PUBLISHED']);
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'amount_paid' => 100]);
        $this->actingAs($user);
        app('request')->setUserResolver(fn () => $user);
        if ($origin) {
            $request = app('request');
            $request->setLaravelSession(app('session')->driver());
            app(PublicJourneyContinuation::class)->setLanding($landing);
        }

        return [$user, $benefit, $location, $landing];
    }

    private function confirmed(bool $origin = true): Redemption
    {
        [$user, $benefit, $location] = $this->pilot($origin);
        $service = app(RedemptionService::class);
        $redemption = $service->start($user, $benefit, $location);

        return $service->confirm($redemption->code, '123456');
    }

    public function test_real_pin_confirmation_is_required_and_capture_rolls_back_with_domain(): void
    {
        [$user, $benefit, $location] = $this->pilot();
        DB::beginTransaction();
        $redemption = app(RedemptionService::class)->start($user, $benefit, $location);
        $this->assertDatabaseCount('commercial_conversion_origins', 1);
        DB::rollBack();
        $this->assertDatabaseCount('commercial_conversion_origins', 0);
        $redemption = app(RedemptionService::class)->start($user, $benefit, $location);
        $this->assertSame(0, app(CommercialDispatcher::class)->run());
        $this->assertDatabaseCount('commercial_outbox', 0);
        try {
            app(RedemptionService::class)->confirm($redemption->code, '999999');
            $this->fail('Invalid PIN must not confirm');
        } catch (\DomainException) {
            $this->assertSame('pending', $redemption->fresh()->status);
        }
        $this->assertSame(0, app(CommercialOutbox::class)->collect());
        Http::assertNothingSent();
    }

    public function test_confirmed_event_preserves_original_reference_and_optional_attribution(): void
    {
        $redemption = $this->confirmed();
        Http::assertNothingSent();
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->assertSame(1, app(CommercialOutbox::class)->collect());
        $this->assertSame(0, app(CommercialOutbox::class)->collect());
        $payload = json_decode(DB::table('commercial_outbox')->value('raw_body'), true);
        $this->assertSame('com:redemption:'.$redemption->public_id.':confirmed', $payload['event_id']);
        $this->assertSame(['type' => 'redemption', 'id' => $redemption->public_id], $payload['com_reference']);
        $this->assertSame($redemption->confirmed_at->toRfc3339String(), $payload['occurred_at']);
        $this->assertSame('confirmed', $payload['conversion_status']);
        $this->assertSame((string) $redemption->partner_id, $payload['partner']['id']);
        $this->assertSame(config('commercial.pilot_landing'), $payload['origin']['landing']);
        $this->assertSame($redemption->user->global_subject_id, $payload['global_subject_id']);
        $this->assertArrayNotHasKey('campaign', $payload['origin']);
        $this->assertArrayNotHasKey('referral', $payload);
        $this->assertArrayNotHasKey('email', $payload);
    }

    public function test_direct_product_has_no_invented_landing_campaign_or_referral(): void
    {
        $this->confirmed(false);
        app(CommercialOutbox::class)->collect();
        $payload = json_decode(DB::table('commercial_outbox')->value('raw_body'), true);
        $this->assertArrayNotHasKey('origin', $payload);
        $this->assertArrayNotHasKey('referral', $payload);
    }

    public function test_exact_touch_and_original_com_referral_are_frozen_before_operator_confirmation(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot(false);
        $referrer = User::factory()->create();
        $touch = AttributionTouch::create(['user_id' => $user->id, 'occurred_at' => now(), 'utm_campaign' => 'real-campaign',
            'metadata' => ['landing_presentation_id' => $landing->id, 'landing_slug' => $landing->slug,
                'subject_type' => $benefit->getMorphClass(), 'subject_id' => $benefit->id]]);
        $referral = ReferralRelationship::create(['referrer_user_id' => $referrer->id, 'referred_user_id' => $user->id,
            'attribution_touch_id' => $touch->id, 'referral_code' => 'COM-CODE', 'attributed_at' => now(), 'status' => 'active']);
        $service = app(RedemptionService::class);
        $redemption = $service->start($user, $benefit, $location);
        $touch->update(['utm_campaign' => 'changed-later']);
        $this->actingAs($referrer);
        $service->confirm($redemption->code, '123456');
        app(CommercialOutbox::class)->collect();
        $payload = json_decode(DB::table('commercial_outbox')->value('raw_body'), true);
        $this->assertSame('real-campaign', $payload['origin']['campaign']);
        $this->assertSame(['com_referral_id' => (string) $referral->id], $payload['referral']);
        $this->assertSame($user->global_subject_id, $payload['global_subject_id']);
    }

    public function test_other_partner_and_unrelated_touch_are_not_attributed_to_pilot(): void
    {
        [$user, $benefit, $location] = $this->pilot(false);
        AttributionTouch::create(['user_id' => $user->id, 'occurred_at' => now(), 'utm_campaign' => 'unrelated',
            'metadata' => ['landing_presentation_id' => 9999, 'subject_type' => $benefit->getMorphClass(), 'subject_id' => $benefit->id]]);
        $redemption = app(RedemptionService::class)->start($user, $benefit, $location);
        $attribution = json_decode(DB::table('commercial_conversion_origins')->value('attribution'), true);
        $this->assertArrayNotHasKey('origin', $attribution);
        $benefit->partner->update(['slug' => 'another-partner']);
        app(CommercialOutbox::class)->capture($redemption->fresh());
        $this->assertDatabaseCount('commercial_conversion_origins', 1);
    }

    public function test_outbox_failure_does_not_break_canje(): void
    {
        [$user, $benefit, $location] = $this->pilot();
        DB::statement('ALTER TABLE commercial_conversion_origins RENAME TO commercial_origins_unavailable');
        try {
            $redemption = app(RedemptionService::class)->start($user, $benefit, $location);
            $this->assertSame('pending', $redemption->fresh()->status);
        } finally {
            DB::statement('ALTER TABLE commercial_origins_unavailable RENAME TO commercial_conversion_origins');
        }
    }

    public function test_signs_exact_utf8_bytes_and_sends_no_bearer_cookie_or_redirect(): void
    {
        $redemption = $this->confirmed();
        Http::fake(function (Request $request) {
            $raw = $request->body();
            $timestamp = $request->header('X-Jakawi-Com-Timestamp')[0];
            $canonical = $timestamp."\nPOST\n/internal/commercial/events\n".hash('sha256', $raw);
            $this->assertSame('https://jakawi.club/internal/commercial/events', $request->url());
            $this->assertSame('sha256='.hash_hmac('sha256', $canonical, str_repeat('c', 64)), $request->header('X-Jakawi-Com-Signature')[0]);
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertFalse($request->hasHeader('Cookie'));
            $this->assertFalse($request->toPsrRequest()->getUri()->getQuery() !== '');

            return Http::response(['status' => 'accepted', 'event_id' => json_decode($raw, true)['event_id']], 201);
        });
        $this->assertSame(1, app(CommercialDispatcher::class)->run());
        $this->assertDatabaseHas('commercial_outbox', ['status' => 'SENT', 'attempts' => 1, 'last_http_status' => 201]);
        $this->assertSame(0, app(CommercialDispatcher::class)->run());
        Http::assertSentCount(1);
    }

    public function test_outage_and_lost_receipt_retry_same_bytes_with_backoff_then_duplicate_completes(): void
    {
        $this->confirmed();
        Http::fakeSequence()->push(['error' => 'receiver_unavailable'], 503);
        $this->assertSame(1, app(CommercialDispatcher::class)->run());
        $row = DB::table('commercial_outbox')->first();
        $this->assertSame('RETRY', $row->status);
        $this->assertTrue(CarbonImmutable::parse($row->next_attempt_at)->isFuture());
        $this->assertSame(0, app(CommercialDispatcher::class)->run());
        DB::table('commercial_outbox')->update(['next_attempt_at' => now()->subSecond()]);
        Http::swap(new Factory);
        Http::fake(fn (Request $request) => Http::response(['status' => 'duplicate', 'event_id' => $row->event_id], 200));
        $this->assertSame(1, app(CommercialDispatcher::class)->run());
        $this->assertSame($row->raw_body, DB::table('commercial_outbox')->value('raw_body'));
        $this->assertDatabaseHas('commercial_outbox', ['status' => 'SENT', 'attempts' => 2, 'last_http_status' => 200]);
    }

    public function test_permanent_errors_stop_and_never_rename_event(): void
    {
        $this->confirmed();
        Http::fake(['*' => Http::response(['error' => 'event_id_conflict'], 409)]);
        app(CommercialDispatcher::class)->run();
        $row = DB::table('commercial_outbox')->first();
        $this->assertSame('DEAD', $row->status);
        $this->assertSame('HTTP_409', $row->last_error_code);
        $this->assertSame(0, app(CommercialDispatcher::class)->run());
        $this->assertSame(0, app(CommercialOutbox::class)->collect());
        $this->assertSame($row->event_id, DB::table('commercial_outbox')->value('event_id'));
    }

    public function test_invalid_receipt_retries_and_disabled_integration_sends_nothing(): void
    {
        $this->confirmed();
        Http::fake(['*' => Http::response(['status' => 'accepted', 'event_id' => 'wrong-id'], 201)]);
        app(CommercialDispatcher::class)->run();
        $this->assertDatabaseHas('commercial_outbox', ['status' => 'RETRY', 'last_error_code' => 'INVALID_RECEIPT']);
        config(['commercial.enabled' => false]);
        $this->assertSame(0, app(CommercialDispatcher::class)->run());
        Http::assertSentCount(1);
    }

    public function test_dispatch_refuses_uncommitted_domain_transaction(): void
    {
        DB::beginTransaction();
        try {
            app(CommercialDispatcher::class)->run();
            $this->fail('Dispatch inside a domain transaction must fail');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('committed', $exception->getMessage());
        } finally {
            DB::rollBack();
        }
        Http::assertNothingSent();
    }
}
