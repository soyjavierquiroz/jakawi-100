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
use App\Services\Commercial\ClubReferralOrigin;
use App\Services\Commercial\CommercialDispatcher;
use App\Services\Commercial\CommercialOutbox;
use App\Services\ConversionRecorder;
use App\Services\RedemptionService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClubReferralTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['cities'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['commercial.enabled' => true, 'commercial.secret' => str_repeat('c', 64), 'crm.enabled' => false]);
        Http::preventStrayRequests();
    }

    private function pilot(): array
    {
        $partner = Partner::factory()->published()->create(['slug' => 'dossis43']);
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        $landing = $benefit->landingPresentations()->create(['name' => 'Pilot', 'slug' => config('commercial.pilot_landing'), 'status' => 'PUBLISHED']);
        $user = User::factory()->create();

        return [$user, $benefit, $location, $landing];
    }

    private function membership(User $user): void
    {
        Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'amount_paid' => 100]);
    }

    private function start(User $user, Benefit $benefit, Location $location): Redemption
    {
        $this->membership($user);
        $this->actingAs($user)->post(route('redemptions.start', $benefit), ['location_id' => $location->id])->assertRedirect();

        return Redemption::sole();
    }

    private function payload(Redemption $redemption): array
    {
        app(RedemptionService::class)->confirm($redemption->code, '123456');
        $this->assertSame(1, app(CommercialOutbox::class)->collect());

        return json_decode(DB::table('commercial_outbox')->sole()->raw_body, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_guest_login_membership_gate_and_operator_pin_preserve_the_customer_origin(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $this->get('/l/'.$landing->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $this->assertDatabaseCount('attribution_touches', 0);
        $this->assertDatabaseCount('referral_relationships', 0);
        $this->assertDatabaseCount('commercial_conversion_origins', 0);
        $this->post('/l/'.$landing->slug.'/acquisition', ['method' => 'login'])->assertNoContent();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/l/'.$landing->slug.'/activar');
        $this->post(route('redemptions.start', $benefit), ['location_id' => $location->id])->assertRedirectContains('/membresia?');
        $this->get('/membresia?journey=BENEFIT&action=REDEEM&resource_id='.$benefit->id.'&location_id='.$location->id)->assertOk();
        $redemption = $this->start($user, $benefit, $location);
        $frozen = DB::table('commercial_conversion_origins')->sole()->attribution;
        $this->assertSame(0, app(CommercialOutbox::class)->collect());
        $operator = User::factory()->create();
        $operator->partners()->attach($benefit->partner_id);
        // Different session; operator has a different CLUB code for this same landing.
        app('session')->flush();
        $this->actingAs($operator)->get('/l/'.$landing->slug.'?club_ref=ZZ99YY88XX77')->assertOk();
        $this->post('/partner/dossis43/validar', ['code' => $redemption->code, 'pin' => '999999'])->assertOk();
        $this->assertSame('pending', $redemption->fresh()->status);
        $this->assertSame(0, app(CommercialOutbox::class)->collect());
        $this->post('/partner/dossis43/validar', ['code' => $redemption->code, 'pin' => '123456'])->assertOk();
        $this->assertSame($frozen, DB::table('commercial_conversion_origins')->sole()->attribution);
        Http::fake(function ($request) use ($user, $redemption, $landing) {
            $payload = json_decode($request->body(), true);
            $this->assertSame('AB12CD34EF56', $payload['referral']['club_referral_code']);
            $this->assertSame($landing->slug, $payload['origin']['landing']);
            $this->assertSame($user->global_subject_id, $payload['global_subject_id']);
            $this->assertSame('confirmed', $payload['conversion_status']);
            $this->assertSame($redemption->public_id, $payload['com_reference']['id']);

            return Http::response(['status' => 'accepted', 'event_id' => $payload['event_id']], 201);
        });
        $this->assertSame(1, app(CommercialDispatcher::class)->run());
        $this->assertSame(0, app(CommercialDispatcher::class)->run());
        Http::assertSentCount(1);
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_signup_retains_the_visitor_origin_until_the_new_customer_operation(): void
    {
        [, $benefit, $location, $landing] = $this->pilot();
        $this->get('/l/'.$landing->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $this->post('/l/'.$landing->slug.'/acquisition', ['method' => 'register'])->assertNoContent();
        $this->post('/register', ['name' => 'Club visitor', 'email' => 'club@example.test', 'whatsapp' => '71234567'])->assertRedirect('/l/'.$landing->slug.'/activar');
        $user = User::where('email', 'club@example.test')->firstOrFail();
        $redemption = $this->start($user, $benefit, $location);
        $this->assertSame('AB12CD34EF56', $this->payload($redemption)['referral']['club_referral_code']);
        $this->assertDatabaseCount('referral_relationships', 0);
    }

    public function test_actual_alternate_landing_and_campaign_are_frozen_at_visit(): void
    {
        [$user, $benefit, $location] = $this->pilot();
        $landing = $benefit->landingPresentations()->create(['name' => 'Other', 'slug' => 'real-other-landing', 'status' => 'PUBLISHED', 'campaign_key' => 'original-campaign']);
        $this->get('/l/'.$landing->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $landing->update(['campaign_key' => 'changed-campaign']);
        $this->get('/l/'.$landing->slug)->assertOk();
        $redemption = $this->start($user, $benefit, $location);
        $payload = $this->payload($redemption);
        $this->assertSame(['landing' => 'real-other-landing', 'campaign' => 'original-campaign'], $payload['origin']);
        $this->assertSame('AB12CD34EF56', $payload['referral']['club_referral_code']);
    }

    public static function invalidCodes(): array
    {
        return array_map(fn ($code) => [$code], ['', 'ab12cd34ef56', 'AB12CD34EF5', 'AB12CD34EF567', 'AB12-CD34EF56', "AB12CD34EF56\n", ' AB12CD34EF56', ['AB12CD34EF56']]);
    }

    #[DataProvider('invalidCodes')]
    public function test_invalid_code_is_ignored_and_conversion_still_works(mixed $code): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $this->get('/l/'.$landing->slug.'?'.http_build_query(['club_ref' => $code]))->assertOk();
        $payload = $this->payload($this->start($user, $benefit, $location));
        $this->assertArrayNotHasKey('referral', $payload);
    }

    public function test_without_code_operator_code_cannot_supply_a_referrer(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $this->get('/l/'.$landing->slug)->assertOk();
        $redemption = $this->start($user, $benefit, $location);
        $this->actingAs(User::factory()->create())->get('/l/'.$landing->slug.'?club_ref=ZZ99YY88XX77')->assertOk();
        $this->assertArrayNotHasKey('referral', $this->payload($redemption));
    }

    public function test_com_code_and_existing_economic_campaign_remain_independent(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $referrer = User::factory()->create();
        $touch = AttributionTouch::create(['user_id' => $user->id, 'occurred_at' => now(), 'utm_campaign' => 'com-campaign', 'metadata' => [
            'landing_presentation_id' => $landing->id, 'landing_slug' => $landing->slug, 'subject_type' => $benefit->getMorphClass(), 'subject_id' => $benefit->id,
        ]]);
        $relationship = ReferralRelationship::create(['referrer_user_id' => $referrer->id, 'referred_user_id' => $user->id, 'attribution_touch_id' => $touch->id, 'referral_code' => 'COM-CODE', 'attributed_at' => now(), 'status' => 'active']);
        $this->actingAs($user)->get('/l/'.$landing->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $payload = $this->payload($this->start($user, $benefit, $location));
        $this->assertSame(['com_referral_id' => (string) $relationship->id, 'club_referral_code' => 'AB12CD34EF56'], $payload['referral']);
        $conversion = app(ConversionRecorder::class)->record($user, ['idempotency_key' => 'isolated', 'type' => 'membership_purchased', 'gross_amount' => 100]);
        $this->assertSame($touch->id, $conversion->attribution_touch_id);
        $this->assertSame('com-campaign', $conversion->attribution_snapshot['utm_campaign']);
        $this->assertSame($relationship->id, $conversion->referral_relationship_id);
        $this->assertDatabaseCount('attribution_touches', 1);
        $this->assertDatabaseCount('referral_relationships', 1);
    }

    public function test_code_on_other_product_or_account_is_not_used(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $other = Benefit::factory()->published()->forPartner($benefit->partner)->create();
        $otherLanding = $other->landingPresentations()->create(['name' => 'Other product', 'slug' => 'other-product', 'status' => 'PUBLISHED']);
        $this->actingAs($user)->get('/l/'.$otherLanding->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $this->actingAs(User::factory()->create())->get('/l/'.$landing->slug.'?club_ref=ZZ99YY88XX77')->assertOk();
        $this->assertArrayNotHasKey('referral', $this->payload($this->start($user, $benefit, $location)));
    }

    public function test_pending_operation_is_not_rewritten_by_a_later_club_visit(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $redemption = $this->start($user, $benefit, $location);
        $this->get('/l/'.$landing->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $this->post(route('redemptions.start', $benefit), ['location_id' => $location->id])->assertRedirect();
        $this->assertArrayNotHasKey('referral', $this->payload($redemption));
        $this->assertDatabaseCount('commercial_conversion_origins', 1);
    }

    public function test_prefetch_partial_unpublished_and_non_landing_do_not_capture(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $path = '/l/'.$landing->slug.'?club_ref=AB12CD34EF56';
        $this->get($path, ['Purpose' => 'prefetch'])->assertOk();
        $this->get($path, ['X-Inertia-Partial-Component' => 'landing-presentations/conversion'])->assertOk();
        $landing->update(['status' => 'DRAFT']);
        $this->get($path)->assertNotFound();
        $landing->update(['status' => 'PUBLISHED']);
        $this->get('/beneficios/'.$benefit->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $this->assertArrayNotHasKey('referral', $this->payload($this->start($user, $benefit, $location)));
    }

    public function test_expired_club_origin_is_not_attributed(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $this->get('/l/'.$landing->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $this->travel(31)->days();
        $this->assertArrayNotHasKey('referral', $this->payload($this->start($user, $benefit, $location)));
    }

    public function test_non_customer_start_does_not_read_an_operator_session(): void
    {
        [$user, $benefit, $location, $landing] = $this->pilot();
        $operator = User::factory()->create();
        $this->actingAs($operator)->get('/l/'.$landing->slug.'?club_ref=AB12CD34EF56')->assertOk();
        $this->membership($user);
        $redemption = app(RedemptionService::class)->start($user, $benefit, $location);
        $this->assertNull(app(ClubReferralOrigin::class)->forRedemption(app('request'), $redemption));
        $this->assertArrayNotHasKey('referral', $this->payload($redemption));
    }
}
