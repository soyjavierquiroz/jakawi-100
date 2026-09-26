<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicV2HttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_public_tracks_views_and_keeps_a_first_party_visitor(): void
    {
        $first = $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('welcome')->has('featuredBenefits')->has('featuredExperiences'));
        $cookieName = config('jakawi.analytics.visitor_cookie');
        $this->assertMatchesRegularExpression('/'.preg_quote($cookieName, '/').'=(?<visitor>[0-9a-f-]{36});/i', $first->headers->get('Set-Cookie'));
        preg_match('/'.preg_quote($cookieName, '/').'=(?<visitor>[0-9a-f-]{36});/i', $first->headers->get('Set-Cookie'), $matches);
        $visitor = $matches['visitor'];
        $this->withUnencryptedCookie($cookieName, $visitor)->get('/')->assertOk();
        $this->assertSame(2, AnalyticsEvent::where('event_name', 'home_view')->count());
        $this->assertSame($visitor, AnalyticsEvent::latest('id')->value('visitor_id'));
        $this->actingAs(User::factory()->create())->withUnencryptedCookie($cookieName, $visitor)->get('/')->assertOk();
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'home_view', 'visitor_id' => $visitor]);
        $invalid = $this->withUnencryptedCookie($cookieName, 'invalid')->get('/')->assertOk();
        $this->assertMatchesRegularExpression('/'.preg_quote($cookieName, '/').'=[0-9a-f-]{36};/i', $invalid->headers->get('Set-Cookie'));
    }

    public function test_partner_and_location_are_public_only_when_published_and_do_not_leak_private_fields(): void
    {
        $partner = Partner::factory()->published()->create(['legal_name' => 'Private LLC', 'tax_id' => 'TAX', 'contact_name' => 'Private']);
        $location = Location::factory()->published()->withPartner($partner)->create(['manager_name' => 'Manager', 'manager_phone' => '70000000', 'manager_email' => 'private@example.test']);
        $location->setRedemptionPin('123456');
        $location->save();
        foreach (['draft', 'paused', 'archived'] as $status) {
            $this->get('/partners/'.Partner::factory()->create(['status' => $status])->slug)->assertNotFound();
            $this->get('/lugares/'.Location::factory()->create(['status' => $status])->slug)->assertNotFound();
        }
        $this->get('/partners/'.$partner->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->component('partners/show')->missing('partner.legal_name')->missing('partner.tax_id')->missing('partner.contact_name')->missing('partner.contact_phone')->missing('partner.contact_email')->missing('partner.internal_notes'));
        $this->get('/lugares/'.$location->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->component('locations/show')->missing('location.manager_name')->missing('location.manager_phone')->missing('location.manager_email')->missing('location.redemption_pin_hash')->missing('location.redemption_pin'));
        $this->assertDatabaseCount('analytics_events', 2);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'partner_view', 'partner_id' => $partner->id]);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'location_view', 'location_id' => $location->id]);
    }

    public function test_benefit_listing_filter_and_detail_follow_availability(): void
    {
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create();
        $food = Benefit::factory()->published()->forPartner($partner)->create(['category' => 'food', 'applies_to_all_locations' => true]);
        $cafe = Benefit::factory()->published()->forPartner($partner)->create(['category' => 'cafe', 'applies_to_all_locations' => true]);
        foreach ([['draft'], ['paused'], ['published', now()->addDay()], ['published', null, now()->subDay()]] as $state) {
            $benefit = Benefit::factory()->forPartner($partner)->create(['status' => $state[0], 'starts_at' => $state[1] ?? null, 'ends_at' => $state[2] ?? null]);
            $this->get('/beneficios/'.$benefit->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('availability.available', false)
                ->where('availability.reason', 'ESTE BENEFICIO NO ESTÁ DISPONIBLE AHORA')
            );
        }
        $this->get('/beneficios?category=food')->assertOk()->assertInertia(fn (Assert $page) => $page->has('benefits', 1)->where('benefits.0.slug', $food->slug));
        $this->get('/beneficios?category=not-a-category')->assertOk()->assertInertia(fn (Assert $page) => $page->has('benefits', 0));
        $this->get('/beneficios/'.$food->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->component('benefits/show')->has('locations', 1)->missing('benefit.partner.legal_name'));
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'benefit_view', 'benefit_id' => $food->id, 'partner_id' => $partner->id]);
        $this->assertSame($location->id, $food->availableLocations()->sole()->id);
        $this->assertNotSame($food->id, $cafe->id);
    }

    public function test_experience_publication_sessions_partners_and_reservation_redirects_are_safe(): void
    {
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->withoutPartner()->published()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'whatsapp', 'reservation_whatsapp' => '+59170000000']);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'host']]);
        ExperienceSession::factory()->for($experience)->withLocation($location)->upcoming()->create();
        ExperienceSession::factory()->for($experience)->past()->create();
        ExperienceSession::factory()->for($experience)->cancelled()->create(['starts_at' => now()->addDay()]);
        $this->get('/experiencias')->assertOk()->assertInertia(fn (Assert $page) => $page->has('experiences', 1));
        $this->get('/experiencias/'.$experience->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->component('experiences/show')->has('experience.partners', 1)->has('experience.sessions', 1)->has('experience.reservation_targets', 1));
        $this->get('/experiencias/'.$experience->slug.'/reservar')->assertRedirect('https://wa.me/59170000000');
        $this->assertDatabaseCount('analytics_events', 3);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'experience_reserve_click', 'experience_id' => $experience->id]);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'whatsapp_click', 'experience_id' => $experience->id]);
        foreach (['url' => 'https://example.test/book', 'external' => 'https://example.test/external', 'phone' => '+59171111111'] as $method => $target) {
            $item = Experience::factory()->published()->create(['reservation_method' => $method, $method === 'phone' ? 'reservation_phone' : 'reservation_url' => $target]);
            ExperienceSession::factory()->for($item)->upcoming()->create();
            $this->get('/experiencias/'.$item->slug.'/reservar')->assertRedirect($method === 'phone' ? 'tel:'.$target : $target);
        }
        $none = Experience::factory()->published()->create(['reservation_method' => 'none']);
        $this->get('/experiencias/'.$none->slug.'/reservar')->assertNotFound();
        $this->get('/experiencias/'.Experience::factory()->create(['status' => 'draft'])->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('availability.available', false));
    }

    public function test_contact_redirects_and_empty_states_are_safe(): void
    {
        $partner = Partner::factory()->published()->create(['whatsapp' => '+59170000000']);
        $location = Location::factory()->published()->withPartner($partner)->create(['whatsapp' => '+59171111111', 'maps_url' => 'https://maps.example.test/place']);
        $this->get('/partners/'.$partner->slug.'/whatsapp')->assertRedirect('https://wa.me/59170000000');
        $this->get('/lugares/'.$location->slug.'/whatsapp')->assertRedirect('https://wa.me/59171111111');
        $this->get('/lugares/'.$location->slug.'/mapa')->assertRedirect('https://maps.example.test/place');
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'maps_click', 'location_id' => $location->id]);
        $this->assertSame('location_detail', AnalyticsEvent::where('event_name', 'maps_click')->sole()->metadata['source']);
        $empty = Location::factory()->withoutPartner()->published()->create(['maps_url' => null, 'whatsapp' => null]);
        $this->get('/lugares/'.$empty->slug.'/mapa')->assertNotFound();
        $this->get('/lugares/'.$empty->slug.'/whatsapp')->assertNotFound();
        $this->get('/beneficios')->assertOk();
        $this->get('/experiencias')->assertOk();
    }

    public function test_mi_jakawi_access_empty_and_roi_are_safe(): void
    {
        $this->get('/mi-jakawi')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/mi-jakawi')->assertOk();
        $membership = Membership::create(['user_id' => $user->id, 'amount_paid' => 100, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'status' => 'active']);
        $this->actingAs($user)->get('/mi-jakawi')->assertOk()->assertInertia(fn (Assert $page) => $page->where('membership.confirmed_savings', '0.00')->where('membership.remaining_to_payback', '100.00')->where('membership.has_paid_for_itself', false));
        $this->assertNotNull($membership);
    }

    public function test_mi_jakawi_uses_confirmed_redemptions_for_value_and_orders_real_activity(): void
    {
        config()->set('jakawi.membership.price_bob', 120);
        config()->set('jakawi.membership.duration_days', 400);
        $user = User::factory()->create();
        $membership = Membership::create(['user_id' => $user->id, 'amount_paid' => 120, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'status' => 'active']);
        $partner = Partner::factory()->create();
        $experience = Experience::factory()->create();
        $session = ExperienceSession::factory()->for($experience)->create();
        Redemption::create(['public_id' => (string) str()->ulid(), 'code' => 'ABC123', 'user_id' => $user->id, 'membership_id' => $membership->id, 'partner_id' => $partner->id, 'location_id' => null, 'benefit_id' => null, 'partner_name' => 'Café Real', 'location_name' => 'Centro', 'benefit_title' => 'Café de cortesía', 'status' => 'confirmed', 'savings_amount' => 75, 'expires_at' => now(), 'confirmed_at' => now()->subDay()]);
        Redemption::create(['public_id' => (string) str()->ulid(), 'code' => 'DEF456', 'user_id' => $user->id, 'membership_id' => $membership->id, 'partner_id' => $partner->id, 'location_id' => null, 'benefit_id' => null, 'partner_name' => 'No confirmado', 'location_name' => 'Centro', 'benefit_title' => 'No cuenta', 'status' => 'pending', 'savings_amount' => 99, 'expires_at' => now()->addMinutes(10)]);
        ExperienceReservation::create(['user_id' => $user->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'confirmed', 'party_size' => 1, 'checked_in_at' => now()]);

        $this->actingAs($user)->get('/mi-jakawi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('membership.confirmed_savings', '75.00')
            ->where('membership.remaining_to_payback', '45.00')
            ->where('membership.has_paid_for_itself', false)
            ->where('membershipConfig.price_bob', 120)
            ->where('membershipConfig.duration_days', 400)
            ->where('valueStats.benefits_used', 1)
            ->where('valueStats.experiences_lived', 1)
            ->where('activity.0.type', 'experience')
            ->where('activity.1.type', 'redemption')
            ->missing('membership.founder_number'));
    }

    public function test_mi_jakawi_shows_expired_membership_history_and_payback(): void
    {
        $user = User::factory()->create();
        $membership = Membership::create(['user_id' => $user->id, 'amount_paid' => 100, 'starts_at' => now()->subYear(), 'ends_at' => now()->subDay(), 'status' => 'active']);
        Redemption::create(['public_id' => (string) str()->ulid(), 'code' => 'GHI789', 'user_id' => $user->id, 'membership_id' => $membership->id, 'partner_id' => null, 'location_id' => null, 'benefit_id' => null, 'partner_name' => 'Valor histórico', 'location_name' => 'Centro', 'benefit_title' => 'Beneficio recibido', 'status' => 'confirmed', 'savings_amount' => 100, 'expires_at' => now()->subDay(), 'confirmed_at' => now()->subDays(2)]);

        $this->actingAs($user)->get('/mi-jakawi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('membership.is_active', false)
            ->where('membership.confirmed_savings', '100.00')
            ->where('membership.has_paid_for_itself', true)
            ->where('activity.0.title', 'Valor histórico'));
    }
}
