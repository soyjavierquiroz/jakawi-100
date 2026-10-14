<?php

namespace Tests\Feature;

use App\Models\{Benefit, Location, Membership, Partner, Redemption, ReferralRelationship, User};
use App\Services\{LandingPresentationDefaults, RedemptionService};
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BenefitAuthenticatedAccessTest extends TestCase
{
    use RefreshDatabase;

    private function offer(string $mode = Benefit::ACCESS_AUTHENTICATED): array
    {
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create([
            'access_mode' => $mode, 'redemption_limit_per_member' => 1,
        ]);
        $benefit->syncLocations([$location]);
        return [$benefit, $location, $partner];
    }

    public function test_public_non_member_is_denied_at_controller_and_service(): void
    {
        [$benefit, $location] = $this->offer(Benefit::ACCESS_PUBLIC);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('redemptions.start', $benefit), ['location_id' => $location->id])
            ->assertRedirect('/membresia?journey=BENEFIT&action=REDEEM&resource_id='.$benefit->id.'&location_id='.$location->id);
        $this->assertDatabaseCount('redemptions', 0);
        $this->expectException(DomainException::class);
        app(RedemptionService::class)->start($user, $benefit, $location);
    }

    public function test_members_can_redeem_both_ordinary_modes(): void
    {
        foreach ([Benefit::ACCESS_PUBLIC, Benefit::ACCESS_AUTHENTICATED] as $mode) {
            [$benefit, $location] = $this->offer($mode);
            $user = User::factory()->create();
            Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'amount_paid' => 100]);
            $this->actingAs($user)->post(route('redemptions.start', $benefit))->assertRedirect();
            $redemption = Redemption::where('benefit_id', $benefit->id)->sole();
            app(RedemptionService::class)->confirm($redemption->code, '123456');
            $this->assertTrue($redemption->fresh()->isConfirmed());
        }
    }

    public function test_free_account_owned_delivery_pin_idempotency_and_limit(): void
    {
        [$benefit, $location] = $this->offer();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post(route('redemptions.start', $benefit))->assertRedirect();
        $redemption = Redemption::sole();
        $this->assertNull($redemption->membership_id);
        $this->get(route('redemptions.show', $redemption->public_id))->assertInertia(fn (Assert $p) => $p
            ->component('redemptions/show')->where('redemption.status', 'pending')->where('redemption.membership', null)
            ->where('redemption.qr_url', fn ($url) => is_string($url) && str_contains($url, 'signature=')));
        $this->actingAs(User::factory()->create())->get(route('redemptions.show', $redemption->public_id))->assertForbidden();
        $service = app(RedemptionService::class);
        try {
            $service->confirm($redemption->code, '000000');
            $this->fail('Invalid PIN accepted');
        } catch (DomainException $e) {
            $this->assertStringContainsString('PIN', $e->getMessage());
        }
        $this->assertTrue($redemption->fresh()->isPending());
        $confirmed = $service->confirm($redemption->code, '123456');
        $this->assertTrue($confirmed->isConfirmed());
        $this->assertSame($confirmed->id, $service->confirm($redemption->code, '123456')->id);
        $this->actingAs($user)->post(route('redemptions.start', $benefit))->assertSessionHasErrors('redemption');
        $this->assertDatabaseCount('redemptions', 1);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('membership_purchases', 0);
        $this->assertDatabaseCount('challenge_reward_grants', 0);
        $this->assertSame(1, \App\Models\AnalyticsEvent::where('event_name', 'redeem_confirmed')->count());
    }

    public function test_missing_pin_and_foreign_location_remain_blocked(): void
    {
        [$benefit, $location] = $this->offer();
        $user = User::factory()->create();
        $foreign = Location::factory()->published()->create();
        $this->actingAs($user)->post(route('redemptions.start', $benefit), ['location_id' => $foreign->id])->assertSessionHasErrors('redemption');
        $location->forceFill(['redemption_pin_hash' => null])->save();
        $this->post(route('redemptions.start', $benefit), ['location_id' => $location->id])->assertSessionHasErrors('redemption');
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_guest_partner_landing_signup_activation_and_delivery_preserve_acquisition_without_membership(): void
    {
        [$benefit, $location, $partner] = $this->offer();
        $landing = $benefit->landingPresentations()->create(['name' => 'Account offer', 'slug' => 'account-offer', 'status' => 'PUBLISHED', 'publish_scope' => 'ALL', 'media_mode' => 'NONE']);
        app(LandingPresentationDefaults::class)->choose($benefit, $landing, 'ALL');
        $this->get('/'.$partner->slug)->assertOk()->assertInertia(fn (Assert $p) => $p->where('benefits.0.id', $benefit->id));
        $this->get(route('benefits.show', $benefit))->assertRedirect(route('landing-presentations.show', $landing));
        $this->get('/l/account-offer')->assertInertia(fn (Assert $p) => $p->where('copy.action.kind', 'guest')
            ->where('copy.membership', 'Disponible con tu cuenta JAKAWI. No requiere membresía activa.'));
        $this->post(route('redemptions.start', $benefit))->assertRedirect('/register');
        $this->assertDatabaseCount('redemptions', 0);
        $this->post(route('landing-presentations.acquisition', $landing), ['method' => 'register'])->assertNoContent();
        $this->post('/register', ['name' => 'Account Offer User', 'email' => 'account-offer@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect(route('landing-presentations.activation', $landing, false));
        $user = User::whereEmail('account-offer@example.test')->sole();
        $this->assertSame($partner->id, ReferralRelationship::where('referred_user_id', $user->id)->sole()->acquisition_partner_id);
        $this->get(route('landing-presentations.activation', $landing))->assertInertia(fn (Assert $p) => $p
            ->where('conversionStage', 'activation')->where('hasActiveMembership', false)->where('benefit.access_mode', 'authenticated'));
        $this->get('/l/account-offer')->assertInertia(fn (Assert $p) => $p->where('copy.action.kind', 'native')->where('landingDirectAction.data.location_id', $location->id));
        $this->post(route('redemptions.start', $benefit))->assertRedirect();
        $redemption = Redemption::sole();
        $this->get(route('redemptions.show', $redemption->public_id))->assertOk();
        app(RedemptionService::class)->confirm($redemption->code, '123456');
        $this->post(route('redemptions.start', $benefit))->assertSessionHasErrors('redemption');
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('redemptions', 1);
    }

    public function test_admin_accepts_authenticated_and_rejects_unknown_access_mode(): void
    {
        [$benefit, $location, $partner] = $this->offer();
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = ['title' => 'Account offer', 'slug' => 'admin-account-offer', 'partner_id' => $partner->id,
            'status' => 'published', 'access_mode' => 'authenticated', 'location_scope' => 'selected', 'location_ids' => [$location->id]];
        $this->actingAs($admin)->post('/admin/benefits', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('benefits', ['slug' => 'admin-account-offer', 'access_mode' => 'authenticated']);
        $payload['slug'] = 'invalid-mode';
        $payload['access_mode'] = 'anonymous';
        $this->post('/admin/benefits', $payload)->assertSessionHasErrors('access_mode');
        $this->assertDatabaseMissing('benefits', ['slug' => 'invalid-mode']);
    }
}
