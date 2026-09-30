<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\City;
use App\Models\CityInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CityInterestTest extends TestCase
{
    use RefreshDatabase;

    private City $active;

    private City $unlocking;

    private City $comingSoon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->active = City::query()->where('slug', 'cochabamba')->sole();
        $this->unlocking = City::query()->where('slug', 'la-paz')->sole();
        $this->comingSoon = City::query()->where('slug', 'sucre')->sole();
    }

    public function test_inactive_city_has_a_public_landing_with_real_count_only(): void
    {
        $this->get('/ciudades/la-paz')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('cities/show')
            ->where('city.slug', 'la-paz')
            ->where('interestCount', 0)
            ->where('interestRecorded', false)
        );
    }

    public function test_active_city_redirects_into_selected_city_experience(): void
    {
        $this->get('/ciudades/cochabamba')->assertRedirect('/')->assertCookie('selected_city', 'cochabamba');
    }

    public function test_guest_interest_is_idempotent_and_does_not_select_inactive_city_or_create_economic_records(): void
    {
        $visitor = '4cf311b3-483e-42fc-98ea-2b78790f2fe9';

        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/interes')->assertRedirect('/ciudades/la-paz')->assertCookieMissing('selected_city');
        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/interes')->assertRedirect('/ciudades/la-paz');

        $this->assertDatabaseCount('city_interests', 1);
        $this->assertDatabaseHas('city_interests', ['city_id' => $this->unlocking->id, 'visitor_id' => $visitor, 'user_id' => null]);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('membership_purchases', 0);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertDatabaseCount('unlock_participations', 0);
    }

    public function test_authenticated_interest_is_idempotent_and_each_city_is_distinct(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/ciudades/la-paz/interes')->assertRedirect();
        $this->actingAs($user)->post('/ciudades/la-paz/interes')->assertRedirect();
        $this->actingAs($user)->post('/ciudades/sucre/interes')->assertRedirect();

        $this->assertDatabaseCount('city_interests', 2);
        $this->assertDatabaseHas('city_interests', ['city_id' => $this->unlocking->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('city_interests', ['city_id' => $this->comingSoon->id, 'user_id' => $user->id]);
    }

    public function test_interest_preserves_available_attribution_without_changing_referral_policy(): void
    {
        $visitor = 'e4a3c55e-9107-4cd0-92dc-e44d73d087b4';
        $touch = AttributionTouch::create(['anonymous_id' => $visitor, 'utm_source' => 'radio', 'utm_campaign' => 'la-paz', 'landing_page' => '/r/ABC', 'occurred_at' => now()]);

        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/interes')->assertRedirect();

        $interest = CityInterest::sole();
        $this->assertSame($touch->id, $interest->attribution_touch_id);
        $this->assertSame('radio', $interest->attribution_snapshot['utm_source']);
        $this->assertSame('la-paz', $interest->attribution_snapshot['utm_campaign']);
    }

    public function test_repeat_interest_retains_its_original_attribution_snapshot(): void
    {
        $visitor = '4b638e6f-270e-4c19-a69b-7441054e39d5';
        $first = AttributionTouch::create(['anonymous_id' => $visitor, 'utm_source' => 'radio', 'occurred_at' => now()->subMinute()]);

        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/interes')->assertRedirect();
        AttributionTouch::create(['anonymous_id' => $visitor, 'utm_source' => 'social', 'occurred_at' => now()]);
        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/interes')->assertRedirect();

        $interest = CityInterest::sole();
        $this->assertSame($first->id, $interest->attribution_touch_id);
        $this->assertSame('radio', $interest->attribution_snapshot['utm_source']);
    }

    public function test_paused_city_never_exposes_discovery_or_accepts_interest(): void
    {
        $this->unlocking->update(['status' => City::PAUSED]);

        $this->get('/ciudades/la-paz')->assertOk()->assertInertia(fn (Assert $page) => $page->where('city.status', City::PAUSED));
        $this->post('/ciudades/la-paz/interes')->assertStatus(422);
        $this->assertDatabaseCount('city_interests', 0);
    }

    public function test_admin_sees_accurate_interest_totals_without_public_identity_data(): void
    {
        $registered = User::factory()->create();
        CityInterest::create(['city_id' => $this->unlocking->id, 'user_id' => $registered->id]);
        CityInterest::create(['city_id' => $this->unlocking->id, 'visitor_id' => '07fc76c2-4efc-4c49-a4e1-45cd8cc34f90']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/ciudades/la-paz')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('interestSummary.total', 2)
            ->where('interestSummary.registered', 1)
            ->where('interestSummary.guests', 1)
        );
        $this->get('/ciudades/la-paz')->assertInertia(fn (Assert $page) => $page->where('interestCount', 2)->missing('interests'));
    }
}
