<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\User;
use App\Support\ConversionIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConversionPaywallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_unlock_stores_a_server_side_intent_and_renders_the_contextual_paywall(): void
    {
        $benefit = $this->availableBenefit(['estimated_savings' => '45.00']);

        $this->get('/beneficios/'.$benefit->slug.'/desbloquear')
            ->assertOk()
            ->assertSessionHas(ConversionIntent::SESSION_KEY, ['benefit_id' => $benefit->id, 'return_target' => 'benefit'])
            ->assertInertia(fn (Assert $page) => $page->component('membership/paywall')
                ->where('paywall.triggerBenefit.id', $benefit->id)
                ->where('paywall.triggerBenefit.title', $benefit->title)
                ->where('paywall.triggerBenefit.partner.name', $benefit->partner->name)
                ->where('paywall.triggerBenefit.estimated_savings', '45.00')
                ->where('paywall.state', 'guest')
                ->where('paywall.membership.price_bob', config('jakawi.membership.price_bob'))
                ->where('paywall.membership.duration_days', config('jakawi.membership.duration_days')));

        $this->assertDatabaseCount('analytics_events', 2);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'benefit_unlock_clicked', 'benefit_id' => $benefit->id]);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'membership_viewed', 'benefit_id' => $benefit->id]);
    }

    public function test_free_and_expired_users_can_view_the_paywall_but_active_members_return_to_the_benefit(): void
    {
        $benefit = $this->availableBenefit(['estimated_savings' => null]);
        $free = User::factory()->create();
        $expired = User::factory()->create();
        Membership::create(['user_id' => $expired->id, 'amount_paid' => 100, 'starts_at' => now()->subYear(), 'ends_at' => now()->subDay(), 'status' => 'active']);
        $active = User::factory()->create();
        Membership::create(['user_id' => $active->id, 'amount_paid' => 100, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'status' => 'active']);

        $this->actingAs($free)->get('/beneficios/'.$benefit->slug.'/desbloquear')->assertOk()->assertInertia(fn (Assert $page) => $page->where('paywall.state', 'free')->where('paywall.triggerBenefit.estimated_savings', null));
        $this->actingAs($expired)->get('/beneficios/'.$benefit->slug.'/desbloquear')->assertOk()->assertInertia(fn (Assert $page) => $page->where('paywall.state', 'expired'));
        $this->actingAs($active)->get('/beneficios/'.$benefit->slug.'/desbloquear')->assertRedirect('/beneficios/'.$benefit->slug);
        $this->assertSame(4, AnalyticsEvent::count());
    }

    public function test_membership_offer_is_read_from_the_server_configuration(): void
    {
        Config::set('jakawi.membership.price_bob', 129);
        Config::set('jakawi.membership.duration_days', 400);
        $benefit = $this->availableBenefit();

        $this->get('/beneficios/'.$benefit->slug.'/desbloquear')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('paywall.membership.price_bob', 129)
                ->where('paywall.membership.duration_days', 400));
    }

    public function test_login_and_registration_return_to_the_same_contextual_paywall(): void
    {
        $benefit = $this->availableBenefit();
        $intent = [ConversionIntent::SESSION_KEY => ['benefit_id' => $benefit->id, 'return_target' => 'benefit']];
        $user = User::factory()->create();

        $this->withSession($intent)->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/beneficios/'.$benefit->slug.'/desbloquear');
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->withSession($intent)->post('/register', [
            'name' => 'Conversion User',
            'email' => 'conversion@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/beneficios/'.$benefit->slug.'/desbloquear');
    }

    public function test_invalid_intent_and_unavailable_benefits_do_not_create_redirects_or_paywalls(): void
    {
        $user = User::factory()->create();
        $this->withSession([ConversionIntent::SESSION_KEY => ['benefit_id' => 123, 'return_target' => 'https://evil.com']])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/mi-jakawi');

        $unpublished = Benefit::factory()->forPartner(Partner::factory()->published()->create())->create(['status' => 'draft']);
        $this->get('/beneficios/'.$unpublished->slug.'/desbloquear')->assertNotFound();
    }

    private function availableBenefit(array $attributes = []): Benefit
    {
        $partner = Partner::factory()->published()->create();

        return Benefit::factory()->published()->forPartner($partner)->create($attributes);
    }
}
