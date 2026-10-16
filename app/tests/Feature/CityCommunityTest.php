<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\City;
use App\Models\CommunityInteraction;
use App\Models\Partner;
use App\Models\User;
use App\Rules\WhatsAppCommunityUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CityCommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_floating_invitation_uses_existing_frequency_and_official_open_action(): void
    {
        $city = $this->city();
        $this->actingAs(User::factory()->create());
        foreach (['/explorar', '/mi-jakawi'] as $path) {
            $this->get($path)->assertInertia(fn (Assert $page) => $page
                ->where('communityBubble.automatic', true)
                ->where('communityBubble.source', 'floating')
                ->missing('communityBubble.url'));
        }
        $this->assertDatabaseCount('community_interactions', 0);
        $this->action($city, 'presented', 'floating')->assertOk()->assertJson(['accepted' => true]);
        $this->get('/explorar')->assertInertia(fn (Assert $page) => $page->where('communityBubble.automatic', false));
        $this->action($city, 'opened', 'floating')->assertOk()->assertJson(['url' => $city->community_invite_url]);
        $this->assertDatabaseHas('community_interactions', ['exposure_count' => 1, 'status' => 'opened']);
    }

    public function test_floating_invitation_fails_closed_on_non_consumer_surfaces_and_guest(): void
    {
        $this->city();
        $this->get('/explorar')->assertInertia(fn (Assert $page) => $page->where('communityBubble', null));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['/', '/admin/ciudades', '/perfil'] as $path) {
            $this->get($path)->assertInertia(fn (Assert $page) => $page->where('communityBubble', null));
        }
    }

    public function test_floating_invitation_respects_opt_out_and_disabled_city(): void
    {
        $city = $this->city();
        $this->actingAs(User::factory()->create());
        foreach (['declined', 'self_reported_joined', 'snoozed', 'opened'] as $action) {
            $this->action($city, $action)->assertOk();
            $this->get('/explorar')->assertInertia(fn (Assert $page) => $page->where('communityBubble.automatic', false));
        }
        $city->update(['community_enabled' => false]);
        $this->get('/explorar')->assertInertia(fn (Assert $page) => $page->where('communityBubble', null));
    }

    private function city(): City
    {
        $city = City::where('slug', 'cochabamba')->firstOrFail();
        $city->update(['community_enabled' => true, 'community_name' => 'Comunidad JAKAWI · Cochabamba', 'community_invite_url' => 'https://chat.whatsapp.com/'.str_repeat('A', 22)]);

        return $city;
    }

    private function action(City $city, string $action, string $source = 'home')
    {
        return $this->withCredentials()->withCookie(config('session.cookie'), session()->getId())->postJson('/comunidades/'.$city->slug.'/interaccion', compact('action', 'source'));
    }

    public function test_guest_cannot_use_any_private_community_action(): void
    {
        $city = $this->city();
        foreach (['presented', 'opened', 'snoozed', 'declined', 'self_reported_joined', 'reset'] as $action) {
            $this->action($city, $action)->assertUnauthorized();
        }
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('community', null));
        $this->assertDatabaseCount('community_interactions', 0);
    }

    public function test_free_unverified_account_can_open_but_is_not_a_verified_member(): void
    {
        $city = $this->city();
        $this->actingAs(User::factory()->unverified()->create());
        $this->action($city, 'opened')->assertOk()->assertJson(['status' => 'opened', 'url' => $city->community_invite_url]);
        $this->assertDatabaseHas('community_interactions', ['status' => 'opened', 'exposure_count' => 0]);
        $this->assertDatabaseCount('memberships', 0);
        $event = AnalyticsEvent::where('event_name', 'community_opened')->sole();
        $this->assertEquals(['city_id' => $city->id, 'city_slug' => $city->slug, 'source' => 'home'], $event->metadata);
        $this->action($city, 'opened')->assertOk();
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'community_opened')->count());
    }

    public function test_pages_do_not_count_exposures_and_both_home_modes_offer_the_card(): void
    {
        $this->city();
        $this->actingAs(User::factory()->create());
        foreach ([true, false] as $prelaunch) {
            config(['jakawi.prelaunch_mode' => $prelaunch]);
            for ($i = 0; $i < 2; $i++) {
                $this->get('/')->assertInertia(fn (Assert $page) => $page->where('community.automatic', true)->where('community.source', 'home'));
            }
        }
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $page) => $page->where('community.automatic', false));
        $this->assertDatabaseCount('community_interactions', 0);
    }

    public function test_disabled_missing_and_invalid_configuration_fail_closed(): void
    {
        City::factory()->create(['status' => City::ACTIVE]);
        $city = $this->city();
        $this->actingAs(User::factory()->create());
        foreach ([['community_enabled' => false], ['community_enabled' => true, 'community_invite_url' => null], ['community_invite_url' => 'https://example.com'], ['community_invite_url' => 'https://chat.whatsapp.com/'.str_repeat('A', 22), 'status' => City::PAUSED]] as $data) {
            $city->update($data);
            $this->get('/mi-jakawi')->assertInertia(fn (Assert $page) => $page->where('community', null));
            $this->action($city, 'opened')->assertStatus($city->status === City::ACTIVE ? 404 : 409);
        }
    }

    public function test_snooze_interval_session_deduplication_and_limit_of_three(): void
    {
        $city = $this->city();
        $this->actingAs(User::factory()->create());
        for ($exposure = 1; $exposure <= 3; $exposure++) {
            $this->action($city, 'presented')->assertJson(['accepted' => true]);
            $this->action($city, 'presented')->assertJson(['accepted' => false]);
            $this->assertSame($exposure, CommunityInteraction::sole()->exposure_count);
            $this->action($city, 'snoozed')->assertOk();
            $this->action($city, 'snoozed')->assertOk();
            $this->travel(13)->days();
            session()->regenerate();
            $this->action($city, 'presented')->assertJson(['accepted' => false]);
            $this->travel(1)->days();
            // Same session would remain suppressed, even after the time interval.
            session()->regenerate();
        }
        $this->action($city, 'presented')->assertJson(['accepted' => false]);
        $this->assertSame(3, AnalyticsEvent::where('event_name', 'community_presented')->count());
        $this->assertSame(3, AnalyticsEvent::where('event_name', 'community_snoozed')->count());
    }

    public function test_ignored_invitation_waits_fourteen_days_and_same_session_stays_suppressed(): void
    {
        $city = $this->city();
        $this->actingAs(User::factory()->create());
        $this->action($city, 'presented')->assertJson(['accepted' => true]);
        $this->travel(14)->days();
        $this->action($city, 'presented')->assertJson(['accepted' => false]);
        session()->regenerate();
        $this->action($city, 'presented')->assertJson(['accepted' => true]);
    }

    public function test_opening_suspends_automatic_invites_for_thirty_days(): void
    {
        $city = $this->city();
        $this->actingAs(User::factory()->create());
        $this->action($city, 'opened')->assertOk();
        $this->travel(29)->days();
        $this->action($city, 'presented')->assertJson(['accepted' => false]);
        $this->travel(1)->days();
        $this->action($city, 'presented')->assertJson(['accepted' => true]);
    }

    public function test_opt_out_and_self_report_preserve_manual_access_and_can_be_corrected(): void
    {
        $city = $this->city();
        $this->actingAs(User::factory()->create());
        foreach (['declined', 'self_reported_joined'] as $status) {
            $this->action($city, $status)->assertOk();
            $this->action($city, $status)->assertOk();
            $this->travel(60)->days();
            session()->regenerate();
            $this->get('/mi-jakawi')->assertInertia(fn (Assert $page) => $page->where('community.automatic', false)->where('community.status', $status));
            $this->action($city, 'opened', 'mi-jakawi')->assertJson(['status' => $status]);
            $this->action($city, 'presented')->assertJson(['accepted' => false]);
            $this->assertSame(1, AnalyticsEvent::where('event_name', 'community_'.$status)->count());
            $this->action($city, 'reset', 'mi-jakawi')->assertJson(['status' => 'opened']);
            $this->travel(30)->days();
        }
        $this->action($city, 'presented')->assertJson(['accepted' => true]);
    }

    public function test_active_city_uses_independent_state_and_one_exposure_per_session_across_cities(): void
    {
        $first = $this->city();
        $second = City::factory()->create(['status' => City::ACTIVE, 'community_enabled' => true, 'community_name' => 'Otra comunidad', 'community_invite_url' => $first->community_invite_url]);
        $this->actingAs(User::factory()->create());
        $this->action($first, 'presented')->assertJson(['accepted' => true]);
        $this->action($first, 'declined')->assertOk();
        $this->withCookie('selected_city', $second->slug);
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $page) => $page->where('community.city_slug', $second->slug)->where('community.status', 'not_invited'));
        $this->action($second, 'presented')->assertJson(['accepted' => false]);
        session()->regenerate();
        $this->action($second, 'presented')->assertJson(['accepted' => true]);
        $this->action($first, 'opened')->assertConflict();
        $this->assertDatabaseHas('community_interactions', ['city_id' => $first->id, 'status' => 'declined']);
        $this->assertDatabaseHas('community_interactions', ['city_id' => $second->id, 'status' => 'invited']);
    }

    public function test_registration_marks_nonblocking_onboarding_without_counting_an_exposure(): void
    {
        $this->city();
        $this->post('/register', ['name' => 'Comunidad Test', 'email' => 'community@example.test', 'whatsapp' => '71234567'])->assertRedirect('/dashboard');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('community.source', 'onboarding')->where('community.automatic', true));
        $this->assertDatabaseCount('community_interactions', 0);
    }

    public function test_admin_configuration_validation_and_authorization(): void
    {
        $city = $this->city();
        $payload = $city->only(['name', 'slug', 'region', 'country_code', 'priority', 'community_name', 'community_enabled', 'community_invite_url']);
        $url = '/admin/ciudades/'.$city->slug;
        $this->put($url, $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->put($url, $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['https://evil.test', 'http://chat.whatsapp.com/'.str_repeat('A', 22), 'https://chat.whatsapp.com.evil.test/'.str_repeat('A', 22), 'https://chat.whatsapp.com@evil.test/'.str_repeat('A', 22), $payload['community_invite_url'].'?redirect=https://evil.test', '', 'https://chat.whatsapp.com/short'] as $invalid) {
            $this->assertFalse(WhatsAppCommunityUrl::valid($invalid));
            $this->put($url, [...$payload, 'community_invite_url' => $invalid])->assertSessionHasErrors('community_invite_url');
        }
        $this->put($url, [...$payload, 'community_name' => ''])->assertSessionHasErrors('community_name');
        $this->put($url, $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->get($url.'/editar')->assertInertia(fn (Assert $page) => $page->where('city.community_invite_url', $payload['community_invite_url']));
        $this->assertArrayNotHasKey('community_invite_url', $city->fresh()->toArray());
        $this->put($url, [...$payload, 'community_enabled' => false, 'community_invite_url' => '', 'community_name' => ''])->assertSessionHasNoErrors();
        $this->assertFalse($city->fresh()->hasCommunity());
    }

    public function test_partner_only_account_keeps_permanent_access_through_existing_member_page_redirect(): void
    {
        $city = $this->city();
        $user = User::factory()->create();
        $partner = Partner::factory()->published()->create();
        $user->partners()->attach($partner, ['role' => 'staff']);
        $this->actingAs($user);
        $this->action($city, 'declined')->assertOk();
        $this->get('/mi-jakawi')->assertRedirect('/partner');
        $this->get('/partner')->assertRedirect('/partner/'.$partner->slug);
        $this->get('/partner/'.$partner->slug)->assertInertia(fn (Assert $page) => $page->where('community.status', 'declined')->where('community.automatic', false));
        $second = Partner::factory()->published()->create();
        $user->partners()->attach($second, ['role' => 'manager']);
        $this->get('/partner')->assertInertia(fn (Assert $page) => $page->where('community.city_slug', $city->slug));
        $this->action($city, 'opened', 'mi-jakawi')->assertJson(['status' => 'declined']);
    }

    public function test_analytics_disabled_does_not_block_community_access(): void
    {
        $city = $this->city();
        config(['jakawi.analytics.enabled' => false, 'services.meta.enabled' => false]);
        $this->actingAs(User::factory()->create());
        $this->action($city, 'presented')->assertJson(['accepted' => true]);
        $this->action($city, 'opened')->assertOk();
        $this->assertDatabaseCount('analytics_events', 0);
    }
}
