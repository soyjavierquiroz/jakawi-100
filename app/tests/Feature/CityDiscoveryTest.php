<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\City;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Unlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CityDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private City $cbb;

    private City $lpz;

    private City $tarija;

    private Partner $partner;

    private Location $cbbLocation;

    private Location $lpzLocation;

    private Benefit $allBenefit;

    private Benefit $cbbBenefit;

    private Benefit $lpzBenefit;

    private Experience $experience;

    private ExperienceSession $cbbSession;

    private ExperienceSession $lpzSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cbb = City::query()->where('slug', 'cochabamba')->sole();
        $this->lpz = City::query()->where('slug', 'la-paz')->sole();
        $this->lpz->update(['status' => City::ACTIVE]);
        $this->tarija = City::query()->where('slug', 'tarija')->sole();
        $this->partner = Partner::factory()->published()->create(['name' => 'Partner nacional']);
        $this->cbbLocation = Location::factory()->published()->withPartner($this->partner)->create(['city_id' => $this->cbb->id, 'name' => 'Lugar CBB']);
        $this->lpzLocation = Location::factory()->published()->withPartner($this->partner)->create(['city_id' => $this->lpz->id, 'name' => 'Lugar LPZ']);
        $this->allBenefit = Benefit::factory()->published()->forPartner($this->partner)->create(['slug' => 'beneficio-todas', 'applies_to_all_locations' => true]);
        $this->cbbBenefit = Benefit::factory()->published()->forPartner($this->partner)->create(['slug' => 'beneficio-cbb']);
        $this->cbbBenefit->locations()->attach($this->cbbLocation);
        $this->lpzBenefit = Benefit::factory()->published()->forPartner($this->partner)->create(['slug' => 'beneficio-lpz']);
        $this->lpzBenefit->locations()->attach($this->lpzLocation);
        $this->experience = Experience::factory()->published()->create(['slug' => 'experiencia-bi-ciudad']);
        $this->cbbSession = ExperienceSession::factory()->for($this->experience)->upcoming()->withLocation($this->cbbLocation)->create();
        $this->lpzSession = ExperienceSession::factory()->for($this->experience)->upcoming()->withLocation($this->lpzLocation)->create();
        ExperienceSession::factory()->for($this->experience)->upcoming()->withoutLocation()->create(['venue_label' => 'Centro de Cochabamba']);
        $unlock = Unlock::create(['title' => 'Unlock CBB', 'slug' => 'unlock-cbb', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE]);
        $unlock->locations()->attach($this->cbbLocation);
    }

    public function test_resolver_falls_back_and_only_accepts_active_cities(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('selectedCity.slug', 'cochabamba')->has('availableCities', 5));
        $this->withCookie('selected_city', 'la-paz')->get('/')->assertInertia(fn (Assert $page) => $page->where('selectedCity.slug', 'la-paz'));
        $this->withCookie('selected_city', 'missing')->get('/')->assertInertia(fn (Assert $page) => $page->where('selectedCity.slug', 'cochabamba'));
        $this->withCookie('selected_city', $this->tarija->slug)->get('/')->assertInertia(fn (Assert $page) => $page->where('selectedCity.slug', 'cochabamba'));
    }

    public function test_paused_selected_city_is_invalidated_without_exposing_its_discovery(): void
    {
        $this->cbb->update(['status' => City::PAUSED]);

        $this->withCookie('selected_city', 'cochabamba')->get('/')->assertInertia(
            fn (Assert $page) => $page->where('selectedCity.slug', 'la-paz')->where('discovery.hero.city.slug', 'la-paz')
        );
    }

    public function test_city_selection_is_guest_safe_and_rejects_non_active_cities(): void
    {
        $this->post('/ciudades/la-paz/seleccionar', ['return_to' => '/explorar?q=cafe'])->assertRedirect('/explorar?q=cafe')->assertCookie('selected_city', 'la-paz');
        $this->post('/ciudades/tarija/seleccionar', ['return_to' => 'https://evil.test'])->assertRedirect('/')->assertCookieMissing('selected_city');
    }

    public function test_home_and_explore_are_city_scoped_without_economic_side_effects(): void
    {
        $user = User::factory()->create();
        $membership = Membership::create(['user_id' => $user->id, 'status' => Membership::STATUS_ACTIVE, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'amount_paid' => '100.00']);
        $before = [DB::table('memberships')->count(), DB::table('reward_transactions')->count(), DB::table('jp_holds')->count()];

        $this->withCookie('selected_city', 'cochabamba')->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('selectedCity.slug', 'cochabamba')->has('discovery.hero')->has('discovery.forYou'));
        $this->withCookie('selected_city', 'la-paz')->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('selectedCity.slug', 'la-paz')->where('discovery.hero.city.slug', 'la-paz'));

        foreach ([['', 'opportunities', 3], ['benefits', 'opportunities', 2], ['experiences', 'opportunities', 1], ['unlocks', 'opportunities', 0], ['places', 'places', 1]] as [$type, $property, $count]) {
            $this->withCookie('selected_city', 'la-paz')->get('/explorar?type='.$type)->assertInertia(fn (Assert $page) => $page->has($property, $count));
        }
        $this->withCookie('selected_city', 'la-paz')->get('/explorar?type=benefits&q=beneficio-cbb')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));
        $this->withCookie('selected_city', 'cochabamba')->get('/explorar?type=benefits&q=beneficio-lpz')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));
        $this->withCookie('selected_city', 'la-paz')->get('/explorar?type=experiences')->assertInertia(fn (Assert $page) => $page->where('opportunities.0.source_id', $this->experience->id));
        $this->withCookie('selected_city', 'la-paz')->get('/explorar')->assertInertia(fn (Assert $page) => $page->missing('opportunities.0.venue_label'));

        $this->assertSame($membership->id, Membership::findOrFail($membership->id)->id);
        $this->assertSame($before, [DB::table('memberships')->count(), DB::table('reward_transactions')->count(), DB::table('jp_holds')->count()]);
    }
}
