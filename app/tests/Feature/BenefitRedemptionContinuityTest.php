<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\PublicJourneyContinuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BenefitRedemptionContinuityTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(int $count = 2): array
    {
        $partner = Partner::factory()->published()->create();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        $locations = collect();
        for ($i = 0; $i < $count; $i++) {
            $location = Location::factory()->published()->withPartner($partner)->create();
            $location->setRedemptionPin('123456');
            $location->save();
            $locations->push($location);
        }
        $user = User::factory()->create();
        app(MembershipService::class)->activate($user, User::factory()->create());
        return [$user, $benefit, $locations];
    }

    public function test_guest_login_restores_benefit_intent_and_location_without_redeeming(): void
    {
        [$user, $benefit, $locations] = $this->scenario();
        $path = route('redemptions.intent', ['benefit' => $benefit->slug, 'location_id' => $locations->last()->id], false);
        $this->get($path)->assertRedirect('/register');
        $this->assertSame(['location_id' => $locations->last()->id], app(PublicJourneyContinuation::class)->get()['context']);
        $this->withSession(['url.intended' => 'https://evil.test'])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($path);
        $this->get($path)->assertInertia(fn (Assert $page) => $page->component('benefits/show')->where('benefit.id', $benefit->id)->where('redemptionIntent', true)->where('selectedLocationId', $locations->last()->id));
        $this->assertDatabaseCount('redemptions', 0);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'redeem_started']);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'redeem_confirmed']);
    }

    public function test_guest_without_location_resumes_selection_not_a_redemption(): void
    {
        [$user, $benefit] = $this->scenario();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->post(route('redemptions.start', $benefit))->assertRedirect('/register');
        $path = route('redemptions.intent', $benefit, false);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($path);
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('redemptionIntent', true)->where('selectedLocationId', null));
        $this->post(route('redemptions.start', $benefit))->assertSessionHasErrors('location_id');
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_signup_without_a_location_returns_to_benefit_without_verification_blocker(): void
    {
        [, $benefit] = $this->scenario();
        $path = route('redemptions.intent', $benefit, false);
        $this->get($path)->assertRedirect('/register');
        $this->post('/register', ['name' => 'Demo User', 'email' => 'demo@example.test', 'whatsapp' => '71234567'])->assertRedirect($path);
        $user = User::where('email', 'demo@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('benefit.id', $benefit->id)->where('redemptionIntent', true)->where('selectedLocationId', null)->where('hasActiveMembership', false));
        $this->get(route('verification.notice'))->assertOk();
        $this->assertDatabaseCount('redemptions', 0);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'redeem_confirmed']);
    }

    public function test_multi_location_requires_selection_and_rejects_foreign_or_unusable_locations(): void
    {
        [$user, $benefit, $locations] = $this->scenario();
        $path = route('redemptions.start', $benefit);
        $this->actingAs($user)->post($path)->assertSessionHasErrors('location_id');
        $benefit->update(['applies_to_all_locations' => false]);
        $benefit->syncLocations($locations);
        $unrelated = Location::factory()->published()->withPartner($benefit->partner)->create();
        $unrelated->setRedemptionPin('123456');
        $unrelated->save();
        $this->post($path, ['location_id' => $unrelated->id])->assertSessionHasErrors('redemption');
        $foreign = Location::factory()->published()->create();
        $this->post($path, ['location_id' => $foreign->id])->assertSessionHasErrors('redemption');
        $this->post($path, ['location_id' => 999999])->assertSessionHasErrors('location_id');
        $locations->first()->update(['status' => 'draft']);
        $this->post($path, ['location_id' => $locations->first()->id])->assertSessionHasErrors('redemption');
        $this->assertDatabaseCount('redemptions', 0);
        $this->post($path, ['location_id' => $locations->last()->id])->assertRedirect();
        $this->post($path, ['location_id' => $locations->last()->id])->assertRedirect();
        $this->assertDatabaseCount('redemptions', 1);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'redeem_started')->count());
    }

    public function test_single_location_resolves_deterministically(): void
    {
        [$user, $benefit, $locations] = $this->scenario(1);
        $this->actingAs($user)->get(route('redemptions.intent', $benefit))->assertInertia(fn (Assert $page) => $page->where('selectedLocationId', $locations->first()->id));
        $this->post(route('redemptions.start', $benefit))->assertRedirect();
        $this->assertSame($locations->first()->id, Redemption::sole()->location_id);
    }

    public function test_zero_locations_disables_action_and_cannot_redeem(): void
    {
        [$user, $benefit] = $this->scenario(0);
        $this->actingAs($user)->get(route('redemptions.intent', $benefit))->assertInertia(fn (Assert $page) => $page->where('availability.available', false)->where('redemptionIntent', false)->where('selectedLocationId', null));
        $this->post(route('redemptions.start', $benefit))->assertSessionHasErrors('location_id');
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_stale_auth_context_is_not_authorization(): void
    {
        [$user, $benefit, $locations] = $this->scenario();
        $path = route('redemptions.intent', ['benefit' => $benefit->slug, 'location_id' => $locations->first()->id], false);
        $this->get($path);
        $locations->first()->update(['status' => 'draft']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($path);
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('selectedLocationId', $locations->last()->id));
        $this->post(route('redemptions.start', $benefit), ['location_id' => $locations->first()->id])->assertSessionHasErrors('redemption');
        $benefit->update(['ends_at' => now()->subMinute()]);
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('availability.available', false));
        $this->post(route('redemptions.start', $benefit), ['location_id' => $locations->last()->id])->assertSessionHasErrors('redemption');
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_non_member_context_survives_membership_gate_and_unverified_member_can_redeem(): void
    {
        [, $benefit, $locations] = $this->scenario();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('redemptions.start', $benefit), ['location_id' => $locations->last()->id])->assertRedirect('/membresia?journey=BENEFIT&action=REDEEM&resource_id='.$benefit->id.'&location_id='.$locations->last()->id);
        $this->get('/membresia?journey=BENEFIT&action=REDEEM&resource_id='.$benefit->id.'&location_id='.$locations->last()->id)->assertOk();
        $this->assertSame(['location_id' => $locations->last()->id], app(PublicJourneyContinuation::class)->get()['context']);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->post(route('redemptions.start', $benefit), ['location_id' => $locations->last()->id])->assertRedirect('/membresia?journey=BENEFIT&action=REDEEM&resource_id='.$benefit->id.'&location_id='.$locations->last()->id);
        $this->assertDatabaseCount('redemptions', 0);
        app(MembershipService::class)->activate($user, User::factory()->create());
        $this->post(route('redemptions.start', $benefit), ['location_id' => $locations->last()->id])->assertRedirect();
        $this->assertSame($locations->last()->id, Redemption::sole()->location_id);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'redeem_confirmed']);
    }

    public function test_inactive_and_future_benefits_cannot_redeem(): void
    {
        [$user, $benefit, $locations] = $this->scenario();
        foreach ([['status' => 'draft'], ['status' => 'published', 'starts_at' => now()->addDay()]] as $state) {
            $benefit->update($state);
            $this->actingAs($user)->get(route('redemptions.intent', $benefit))->assertInertia(fn (Assert $page) => $page->where('availability.available', false)->where('redemptionIntent', false));
            $this->post(route('redemptions.start', $benefit), ['location_id' => $locations->first()->id])->assertSessionHasErrors('redemption');
        }
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_challenge_grant_verification_is_not_removed(): void
    {
        [$user, $benefit] = $this->scenario();
        $benefit->update(['access_mode' => 'social_challenge_grant']);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->get(route('redemptions.intent', $benefit))->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_context_rejects_foreign_locations_and_arbitrary_return_urls(): void
    {
        [, $benefit, $locations] = $this->scenario();
        $continuation = app(PublicJourneyContinuation::class);
        foreach ([['location_id' => 999999], ['return_url' => 'https://evil.test'], ['location_id' => $locations->first()->id, 'url' => '/login']] as $context) {
            try {
                $continuation->set('BENEFIT', $benefit->id, 'REDEEM', $context);
                $this->fail('Unsafe context accepted');
            } catch (\InvalidArgumentException) {
                $this->assertNull($continuation->get());
            }
        }
    }
}
