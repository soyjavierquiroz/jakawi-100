<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\City;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CityLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_cities_exist_in_deterministic_order_after_migration(): void
    {
        $cities = City::orderByDesc('priority')->pluck('slug')->all();

        $this->assertSame(['cochabamba', 'la-paz', 'santa-cruz', 'sucre', 'tarija'], $cities);
        $this->assertSame(City::ACTIVE, City::where('slug', 'cochabamba')->value('status'));
        $this->assertSame(City::UNLOCKING, City::where('slug', 'la-paz')->value('status'));
        $this->assertSame(City::COMING_SOON, City::where('slug', 'sucre')->value('status'));
    }

    public function test_location_belongs_to_city_without_changing_partner_location_relationships(): void
    {
        $partner = Partner::factory()->create();
        $cochabamba = City::where('slug', 'cochabamba')->sole();
        $laPaz = City::where('slug', 'la-paz')->sole();
        $locations = Location::factory()->count(2)->for($partner)->create();
        $locations[0]->update(['city_id' => $cochabamba->id, 'city' => $cochabamba->name]);
        $locations[1]->update(['city_id' => $laPaz->id, 'city' => $laPaz->name]);

        $this->assertCount(2, $partner->fresh()->locations);
        $this->assertSame($cochabamba->id, $locations[0]->fresh()->cityEntity->id);
        $this->assertSame($laPaz->id, $locations[1]->fresh()->cityEntity->id);
    }

    public function test_admin_location_persists_city_and_synchronizes_legacy_city_string(): void
    {
        $city = City::where('slug', 'la-paz')->sole();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/locations', $this->locationPayload(['city_id' => $city->id, 'city' => 'Different free text']))->assertRedirect();

        $location = Location::where('slug', 'canonical-location')->sole();
        $this->assertSame($city->id, $location->city_id);
        $this->assertSame('La Paz', $location->city);
        $this->actingAs($admin)->post('/admin/locations', $this->locationPayload(['slug' => 'invalid-city', 'city_id' => 999999]))->assertSessionHasErrors('city_id');
    }

    public function test_online_location_can_have_no_city_but_physical_location_requires_one(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/locations', $this->locationPayload(['city_id' => null]))->assertSessionHasErrors('city_id');
        $this->actingAs($admin)->post('/admin/locations', $this->locationPayload(['slug' => 'online-location', 'location_type' => 'online', 'city_id' => null]))->assertRedirect();
        $this->assertDatabaseHas('locations', ['slug' => 'online-location', 'city_id' => null, 'city' => null]);
    }

    public function test_city_cannot_be_deleted_while_referenced_by_a_location(): void
    {
        $city = City::where('slug', 'cochabamba')->sole();
        Location::factory()->create(['city_id' => $city->id]);

        $this->expectException(QueryException::class);
        $city->delete();
    }

    public function test_city_work_does_not_change_benefit_session_or_redemption_relationships(): void
    {
        $partner = Partner::factory()->create();
        $location = Location::factory()->for($partner)->create();
        $benefit = Benefit::factory()->for($partner)->create();
        $benefit->locations()->attach($location);
        $experience = Experience::factory()->create();
        $session = $experience->sessions()->create(['starts_at' => now()->addDay(), 'status' => 'scheduled', 'location_id' => $location->id]);
        $benefitLocationIds = $benefit->locations()->pluck('locations.id')->all();
        $sessionLocationId = $session->location_id;
        $redemptionCount = Redemption::count();

        $location->update(['city_id' => City::where('slug', 'cochabamba')->value('id')]);

        $this->assertSame($benefitLocationIds, $benefit->fresh()->locations()->pluck('locations.id')->all());
        $this->assertSame($sessionLocationId, $session->fresh()->location_id);
        $this->assertSame($redemptionCount, Redemption::count());
        $this->assertFalse(Schema::hasColumn('partners', 'city_id'));
        $this->assertFalse(Schema::hasColumn('users', 'city_id'));
    }

    public function test_migration_backfills_only_exact_cochabamba_legacy_city_without_changing_related_records(): void
    {
        // C4's city_interests migration follows C2. Revert both so this test
        // recreates locations before C2 adds city_id and executes its backfill.
        Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]);
        $partner = Partner::factory()->create();
        $matching = Location::factory()->for($partner)->create(['city' => 'Cochabamba']);
        $nonmatching = Location::factory()->for($partner)->create(['city' => 'cochabamba centro']);
        $benefit = Benefit::factory()->for($partner)->create();
        $benefit->locations()->attach($matching);
        $experience = Experience::factory()->create();
        $session = $experience->sessions()->create(['starts_at' => now()->addDay(), 'status' => 'scheduled', 'location_id' => $matching->id]);
        $locationCount = Location::count();
        $partnerLocationIds = $partner->locations()->orderBy('id')->pluck('id')->all();
        $benefitLocationIds = $benefit->locations()->orderBy('locations.id')->pluck('locations.id')->all();
        $redemptionCount = Redemption::count();

        Artisan::call('migrate', ['--force' => true]);

        $cochabamba = City::where('slug', 'cochabamba')->sole();
        $this->assertSame($locationCount, Location::count());
        $this->assertSame($partnerLocationIds, $partner->fresh()->locations()->orderBy('id')->pluck('id')->all());
        $this->assertSame($benefitLocationIds, $benefit->fresh()->locations()->orderBy('locations.id')->pluck('locations.id')->all());
        $this->assertSame($matching->id, $session->fresh()->location_id);
        $this->assertSame($redemptionCount, Redemption::count());
        $this->assertSame($cochabamba->id, $matching->fresh()->city_id);
        $this->assertSame('Cochabamba', $matching->fresh()->city);
        $this->assertNull($nonmatching->fresh()->city_id);
    }

    private function locationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Canonical Location',
            'slug' => 'canonical-location',
            'location_type' => 'branch',
            'status' => 'published',
            'city_id' => City::where('slug', 'cochabamba')->value('id'),
        ], $overrides);
    }
}
