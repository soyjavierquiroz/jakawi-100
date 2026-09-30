<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CityInterest;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_per_city_read_only_expansion_metrics(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $laPaz = City::query()->where('slug', 'la-paz')->sole();
        $sucre = City::query()->where('slug', 'sucre')->sole();
        $user = User::factory()->create();
        $latestActivity = now()->startOfSecond();
        $earlierActivity = $latestActivity->copy()->subDay();
        CityInterest::create(['city_id' => $laPaz->id, 'user_id' => $user->id, 'created_at' => $earlierActivity, 'updated_at' => $earlierActivity]);
        CityInterest::create(['city_id' => $laPaz->id, 'visitor_id' => 'acff7808-164b-4cb5-a6b1-734641481d2e', 'created_at' => $latestActivity, 'updated_at' => $latestActivity]);
        CityInterest::create(['city_id' => $sucre->id, 'visitor_id' => '10a61f54-d7ff-447e-bb99-4f6d9910bf0a']);
        foreach ([PartnerApplication::SUBMITTED, PartnerApplication::CONTACTED, PartnerApplication::QUALIFIED, PartnerApplication::APPROVED] as $status) {
            PartnerApplication::create(['city_id' => $laPaz->id, 'business_name' => "Negocio {$status}", 'business_name_normalized' => "negocio {$status}", 'contact_name' => 'Contacto', 'status' => $status]);
        }
        PartnerApplication::create(['city_id' => $sucre->id, 'business_name' => 'Otro', 'business_name_normalized' => 'otro', 'contact_name' => 'Contacto', 'status' => PartnerApplication::APPROVED]);
        $published = Partner::factory()->published()->create();
        Location::factory()->published()->for($laPaz, 'cityEntity')->for($published)->count(2)->create();
        Location::factory()->for($laPaz, 'cityEntity')->create();
        Location::factory()->published()->for($sucre, 'cityEntity')->for(Partner::factory()->published())->create();
        $statuses = City::query()->pluck('status', 'id')->all();

        $response = $this->actingAs($admin)->get('/admin/expansion');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/expansion/index')
            ->has('cities', City::count())
            ->where('cities.1.id', $laPaz->id)
            ->where('cities.1.interest_total', 2)
            ->where('cities.1.interest_registered', 1)
            ->where('cities.1.interest_guests', 1)
            ->where('cities.1.interest_latest_activity', $latestActivity->format('Y-m-d H:i:s').'+00')
            ->where('cities.1.application_total', 4)
            ->where('cities.1.application_submitted', 1)
            ->where('cities.1.application_contacted', 1)
            ->where('cities.1.application_qualified', 1)
            ->where('cities.1.application_approved', 1)
            ->where('cities.1.location_total', 3)
            ->where('cities.1.published_partner_total', 1)
            ->where('cities.3.interest_total', 1)
            ->where('cities.3.application_total', 1)
            ->where('cities.3.published_partner_total', 1)
            ->missing('cities.1.contact_email')
            ->missing('cities.1.visitor_id')
        );
        $this->assertSame($statuses, City::query()->pluck('status', 'id')->all());
        $this->assertDatabaseCount('analytics_events', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_expansion_requires_admin_and_filters_by_status(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/expansion')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/expansion?status='.City::UNLOCKING)->assertInertia(fn (Assert $page) => $page
            ->has('cities', 2)
            ->where('filters.status', City::UNLOCKING)
        );
    }

    public function test_city_detail_includes_the_expansion_metrics(): void
    {
        $city = City::query()->where('slug', 'la-paz')->sole();
        CityInterest::create(['city_id' => $city->id, 'visitor_id' => 'f729a211-0c60-4961-b9f6-7be2b01d997f']);
        PartnerApplication::create(['city_id' => $city->id, 'business_name' => 'Una', 'business_name_normalized' => 'una', 'contact_name' => 'Contacto', 'status' => PartnerApplication::CONTACTED]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/ciudades/la-paz')->assertInertia(fn (Assert $page) => $page
            ->where('expansionMetrics.interest_total', 1)
            ->where('expansionMetrics.application_contacted', 1)
            ->where('expansionMetrics.location_total', 0)
            ->where('expansionMetrics.published_partner_total', 0)
        );
    }
}
