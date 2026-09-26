<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedemptionHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_requires_auth_membership_and_an_explicit_valid_location(): void
    {
        [$member, $benefit, $location] = $this->redeemable();
        $this->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id])->assertSessionHasErrors('redemption');
        $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear')->assertSessionHasErrors('location_id');
        $this->assertDatabaseCount('redemptions', 0);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'redeem_started']);
    }

    public function test_start_creates_and_reuses_pending_redemption_without_double_tracking(): void
    {
        [$member, $benefit, $location] = $this->redeemable();
        $first = $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id])->assertRedirect();
        $redemption = Redemption::sole();
        $first->assertRedirect('/canjes/'.$redemption->public_id);
        $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id])->assertRedirect('/canjes/'.$redemption->public_id);
        $this->assertDatabaseCount('redemptions', 1);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'redeem_started')->count());
    }

    public function test_invalid_locations_and_missing_pin_are_rejected_without_a_redemption(): void
    {
        [$member, $benefit, $location] = $this->redeemable();
        $other = Location::factory()->published()->create();
        $independent = Location::factory()->withoutPartner()->published()->create();
        $draft = Location::factory()->withPartner($benefit->partner)->create(['status' => 'draft']);
        $noPin = Location::factory()->published()->withPartner($benefit->partner)->create();
        foreach ([$other, $independent, $draft, $noPin] as $invalid) {
            $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $invalid->id])->assertSessionHasErrors('redemption');
        }
        $this->assertDatabaseCount('redemptions', 0);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'redeem_started']);
        $this->assertNotNull($location);
    }

    public function test_member_redemption_page_enforces_ownership_and_never_exposes_pin(): void
    {
        [$member, $benefit, $location] = $this->redeemable();
        $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id]);
        $redemption = Redemption::sole();
        auth()->logout();
        $this->get('/canjes/'.$redemption->public_id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/canjes/'.$redemption->public_id)->assertForbidden();
        $this->actingAs($member)->get('/canjes/'.$redemption->public_id)->assertOk()->assertDontSee('123456');
        $redemption->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($member)->get('/canjes/'.$redemption->public_id)->assertOk();
        $this->assertSame('expired', $redemption->fresh()->status);
    }

    public function test_validation_confirms_once_and_handles_wrong_or_expired_codes_privately(): void
    {
        [$member, $benefit, $location] = $this->redeemable();
        $validator = $this->validatorFor($benefit->partner);
        $endpoint = '/partner/'.$benefit->partner->slug.'/validar';
        $this->get($endpoint)->assertRedirect('/login');
        $this->actingAs($validator)->get($endpoint)->assertOk();
        $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id]);
        $redemption = Redemption::sole();
        $this->actingAs($validator)->post($endpoint, ['code' => $redemption->code, 'pin' => '000000'])->assertOk();
        $this->assertSame('pending', $redemption->fresh()->status);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'redeem_confirmed']);
        $this->actingAs($validator)->post($endpoint, ['code' => $redemption->code, 'pin' => '123456'])->assertOk();
        $this->assertSame('confirmed', $redemption->fresh()->status);
        $this->assertNotNull($redemption->fresh()->confirmed_at);
        $this->actingAs($validator)->post($endpoint, ['code' => $redemption->code, 'pin' => '123456'])->assertOk();
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'redeem_confirmed')->count());
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'redeem_confirmed', 'metadata' => json_encode(['code' => $redemption->code])]);
    }

    public function test_expired_redemption_and_member_limits_reject_safely(): void
    {
        [$member, $benefit, $location] = $this->redeemable(['redemption_limit_per_member' => 1]);
        $validator = $this->validatorFor($benefit->partner);
        $endpoint = '/partner/'.$benefit->partner->slug.'/validar';
        $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id]);
        $redemption = Redemption::sole();
        $redemption->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($validator)->post($endpoint, ['code' => $redemption->code, 'pin' => '123456'])->assertOk();
        $this->assertSame('expired', $redemption->fresh()->status);
        $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id]);
        $fresh = Redemption::latest('id')->firstOrFail();
        $this->actingAs($validator)->post($endpoint, ['code' => $fresh->code, 'pin' => '123456'])->assertOk();
        $this->actingAs($member)->post('/beneficios/'.$benefit->slug.'/canjear', ['location_id' => $location->id])->assertSessionHasErrors('redemption');
        $this->assertSame(2, Redemption::count());
    }

    public function test_validation_endpoint_is_throttled_after_twenty_requests(): void
    {
        $partner = Partner::factory()->create();
        $validator = $this->validatorFor($partner);
        $endpoint = '/partner/'.$partner->slug.'/validar';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->actingAs($validator)->post($endpoint, ['code' => 'ABCDEF', 'pin' => '123456'])->assertForbidden();
        }

        $this->actingAs($validator)->post($endpoint, ['code' => 'ABCDEF', 'pin' => '123456'])->assertStatus(429);
    }

    /** @return array{User, Benefit, Location} */
    private function redeemable(array $benefitAttributes = []): array
    {
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(array_merge(['applies_to_all_locations' => true, 'estimated_savings' => 25], $benefitAttributes));
        $member = User::factory()->create();
        app(MembershipService::class)->activate($member, User::factory()->create());

        return [$member, $benefit, $location];
    }

    private function validatorFor(Partner $partner): User
    {
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => 'staff']);

        return $user;
    }
}
