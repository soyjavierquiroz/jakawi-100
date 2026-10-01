<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\City;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Unlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExploreDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_explore_keeps_opportunity_types_categories_search_city_and_places_separate(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $other = City::query()->where('slug', 'la-paz')->sole();
        $other->update(['status' => City::ACTIVE]);
        $partner = Partner::factory()->published()->create(['name' => 'Café Central', 'category' => 'cafe']);
        $place = Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['title' => 'Beneficio café', 'category' => 'cafe', 'applies_to_all_locations' => true]);
        $experience = Experience::factory()->published()->create(['title' => 'Experiencia café', 'category' => 'cafe']);
        ExperienceSession::factory()->for($experience)->upcoming()->withLocation($place)->create();
        $unlock = Unlock::create(['title' => 'Desbloqueo café', 'slug' => 'desbloqueo-cafe', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE]);
        $unlock->locations()->attach($place);
        $otherPartner = Partner::factory()->published()->create(['name' => 'Otra ciudad']);
        $otherPlace = Location::factory()->published()->withPartner($otherPartner)->create(['city_id' => $other->id]);
        $otherBenefit = Benefit::factory()->published()->forPartner($otherPartner)->create(['title' => 'Beneficio otra ciudad', 'category' => 'cafe', 'applies_to_all_locations' => true]);
        $otherUnlock = Unlock::create(['title' => 'Desbloqueo otra ciudad', 'slug' => 'desbloqueo-otra-ciudad', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE]);
        $otherUnlock->locations()->attach($otherPlace);

        $this->withCookie('selected_city', $city->slug)->get('/explorar')->assertInertia(fn (Assert $page) => $page
            ->has('opportunities', 3)
            ->where('opportunities.0.city.id', $city->id)
            ->where('places', [])
        );
        $this->withCookie('selected_city', $city->slug)->get('/explorar?type=benefits')->assertInertia(fn (Assert $page) => $page->has('opportunities', 1)->where('opportunities.0.type', 'BENEFIT')->where('opportunities.0.source_id', $benefit->id));
        $this->withCookie('selected_city', $city->slug)->get('/explorar?type=experiences')->assertInertia(fn (Assert $page) => $page->has('opportunities', 1)->where('opportunities.0.type', 'EXPERIENCE')->where('opportunities.0.source_id', $experience->id));
        $this->withCookie('selected_city', $city->slug)->get('/explorar?type=unlocks')->assertInertia(fn (Assert $page) => $page->has('opportunities', 1)->where('opportunities.0.type', 'UNLOCK')->where('opportunities.0.source_id', $unlock->id));
        $this->withCookie('selected_city', $city->slug)->get('/explorar?type=places&q=central')->assertInertia(fn (Assert $page) => $page->has('places', 1)->where('places.0.id', $partner->id)->has('opportunities', 0));

        $this->withCookie('selected_city', $city->slug)->get('/explorar?category=cafe')->assertInertia(fn (Assert $page) => $page->has('opportunities', 2)->where('opportunities.0.categories.0', 'cafe'));
        $this->withCookie('selected_city', $city->slug)->get('/explorar?type=unlocks&category=cafe')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));
        $this->withCookie('selected_city', $city->slug)->get('/explorar?q=beneficio&type=benefits')->assertInertia(fn (Assert $page) => $page->has('opportunities', 1)->where('opportunities.0.source_id', $benefit->id));
        $this->withCookie('selected_city', $city->slug)->get('/explorar?q=experiencia&category=cafe')->assertInertia(fn (Assert $page) => $page->has('opportunities', 1)->where('opportunities.0.source_id', $experience->id));
        $this->withCookie('selected_city', $city->slug)->get('/explorar?q=desbloqueo')->assertInertia(fn (Assert $page) => $page->has('opportunities', 1)->where('opportunities.0.source_id', $unlock->id));
        $this->withCookie('selected_city', $city->slug)->get('/explorar?q=otra%20ciudad')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));

        $this->assertNotSame($benefit->id, $otherBenefit->id);
        $this->assertNotSame($unlock->id, $otherUnlock->id);
    }

    public function test_explore_caps_each_explicit_result_set_at_twenty_four(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $partner = Partner::factory()->published()->create();
        Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        Benefit::factory()->count(25)->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);

        $this->withCookie('selected_city', $city->slug)->get('/explorar?type=benefits')->assertInertia(fn (Assert $page) => $page->has('opportunities', 24));
    }

    public function test_search_matches_the_supported_fields_without_crossing_domains_or_cities(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $other = City::query()->where('slug', 'la-paz')->sole();
        $other->update(['status' => City::ACTIVE]);

        $partner = Partner::factory()->published()->create(['name' => 'Socio Brújula', 'description' => 'Café de especialidad']);
        $place = Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['title' => 'Ahorro matinal', 'category' => 'cafe', 'applies_to_all_locations' => true]);
        $experience = Experience::factory()->published()->create(['title' => 'Taller de barismo', 'category' => 'cafe']);
        $experience->partners()->attach($partner, ['role' => 'host', 'sort_order' => 0]);
        ExperienceSession::factory()->for($experience)->upcoming()->withLocation($place)->create();
        $unlock = Unlock::create(['title' => 'Club secreto', 'short_description' => 'Una cata nocturna', 'slug' => 'club-secreto', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE]);
        $unlock->locations()->attach($place);

        $otherPartner = Partner::factory()->published()->create(['name' => 'Socio Ajeno', 'description' => 'Café ajeno']);
        $otherPlace = Location::factory()->published()->withPartner($otherPartner)->create(['city_id' => $other->id]);
        $otherExperience = Experience::factory()->published()->create(['title' => 'Taller ajeno', 'category' => 'cafe']);
        ExperienceSession::factory()->for($otherExperience)->upcoming()->withLocation($otherPlace)->create();
        $otherUnlock = Unlock::create(['title' => 'Club ajeno', 'short_description' => 'Cata ajena', 'slug' => 'club-ajeno', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE]);
        $otherUnlock->locations()->attach($otherPlace);

        $request = fn (string $query) => $this->withCookie('selected_city', $city->slug)->get('/explorar?'.$query);

        $request('q=ahorro&type=benefits')->assertInertia(fn (Assert $page) => $page->where('opportunities.0.source_id', $benefit->id));
        $request('q=br%C3%BAjula&type=benefits')->assertInertia(fn (Assert $page) => $page->where('opportunities.0.source_id', $benefit->id));
        $request('q=barismo&type=experiences')->assertInertia(fn (Assert $page) => $page->where('opportunities.0.source_id', $experience->id));
        $request('q=br%C3%BAjula&type=experiences')->assertInertia(fn (Assert $page) => $page->where('opportunities.0.source_id', $experience->id));
        $request('q=club&type=unlocks')->assertInertia(fn (Assert $page) => $page->where('opportunities.0.source_id', $unlock->id));
        $request('q=nocturna&type=unlocks')->assertInertia(fn (Assert $page) => $page->where('opportunities.0.source_id', $unlock->id));
        $request('q=br%C3%BAjula&type=places')->assertInertia(fn (Assert $page) => $page->where('places.0.id', $partner->id)->has('opportunities', 0));
        $request('q=especialidad&type=places')->assertInertia(fn (Assert $page) => $page->where('places.0.id', $partner->id)->has('opportunities', 0));
        $request('q=ajeno')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));
    }

    public function test_search_composes_category_with_todo_and_normalizes_blank_whitespace(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $partner = Partner::factory()->published()->create(['name' => 'Café Brújula', 'category' => 'cafe']);
        $place = Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['title' => 'Café diario', 'category' => 'cafe', 'applies_to_all_locations' => true]);
        $experience = Experience::factory()->published()->create(['title' => 'Café guiado', 'category' => 'cafe']);
        ExperienceSession::factory()->for($experience)->upcoming()->withLocation($place)->create();
        $unlock = Unlock::create(['title' => 'Café oculto', 'slug' => 'cafe-oculto', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE]);
        $unlock->locations()->attach($place);

        $this->withCookie('selected_city', $city->slug)->get('/explorar?q=%20%20%20')->assertInertia(fn (Assert $page) => $page
            ->where('query', '')
            ->has('opportunities', 3)
        );
        $this->withCookie('selected_city', $city->slug)->get('/explorar?q=caf%C3%A9&category=cafe')->assertInertia(fn (Assert $page) => $page
            ->has('opportunities', 2)
            ->where('opportunities', fn ($opportunities): bool => collect($opportunities)->pluck('type')->sort()->values()->all() === ['BENEFIT', 'EXPERIENCE'])
        );
        $this->withCookie('selected_city', $city->slug)->get('/explorar?q=caf%C3%A9&type=unlocks&category=cafe')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));
        $this->assertNotSame($benefit->id, $experience->id);
    }
}
