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

class HomePersonalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_exposes_mixed_discovery_sections_without_duplicates_and_keeps_my_jakawi_separate(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $benefit = $this->benefit($partner, $location, ['featured' => true]);
        $experience = $this->experience($partner, $location);
        $unlock = $this->unlock($location);
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'status' => Membership::STATUS_ACTIVE, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'amount_paid' => '100.00']);
        $before = [DB::table('memberships')->count(), DB::table('reward_transactions')->count(), DB::table('unlock_participations')->count()];

        $this->actingAs($user)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('discovery.hero')
            ->has('discovery.forYou')
            ->has('discovery.happeningNow')
            ->has('discovery.discoverMore')
            ->has('myJakawi')
            ->missing('featuredBenefits')
            ->missing('featuredExperiences')
            ->missing('featuredUnlocks'));

        $response = $this->actingAs($user)->get('/')->viewData('page')['props']['discovery'];
        $items = collect([$response['hero'], ...$response['forYou'], ...$response['happeningNow'], ...$response['discoverMore']])->filter();
        $this->assertEqualsCanonicalizing(['BENEFIT', 'EXPERIENCE', 'UNLOCK'], $items->pluck('type')->unique()->all());
        $this->assertCount($items->count(), $items->map(fn (array $item) => $item['type'].'|'.$item['source_id'])->unique());
        $this->assertEqualsCanonicalizing([$benefit->id, $experience->id, $unlock->id], $items->pluck('source_id')->all());
        $this->assertSame($before, [DB::table('memberships')->count(), DB::table('reward_transactions')->count(), DB::table('unlock_participations')->count()]);
    }

    public function test_home_allows_a_null_hero_and_empty_optional_sections_for_empty_inventory(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('discovery.hero', null)
            ->has('discovery.forYou', 0)
            ->has('discovery.happeningNow', 0)
            ->has('discovery.discoverMore', 0)
            ->where('myJakawi', null));
    }

    public function test_home_uses_selected_city_for_guest_discovery(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $otherCity = City::query()->where('slug', 'la-paz')->sole();
        $otherCity->update(['status' => City::ACTIVE]);
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create(['city_id' => $otherCity->id]);
        $benefit = $this->benefit($partner, $location, ['featured' => true]);

        $this->withCookie('selected_city', $otherCity->slug)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('selectedCity.slug', $otherCity->slug)
            ->where('discovery.hero.type', 'BENEFIT')
            ->where('discovery.hero.source_id', $benefit->id)
            ->where('discovery.hero.city.id', $otherCity->id));
        $this->withCookie('selected_city', $city->slug)->get('/')->assertInertia(fn (Assert $page) => $page->where('discovery.hero', null));
    }

    public function test_partner_only_user_keeps_generic_discovery_ranking(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $featured = $this->benefit($partner, $location, ['featured' => true, 'category' => 'food']);
        $this->benefit($partner, $location, ['category' => 'cafe']);
        $user = User::factory()->create();
        $user->partners()->attach(Partner::factory()->create(), ['role' => 'manager']);
        $user->profile()->create(['interests' => ['cafe']]);

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('discovery.hero.type', 'BENEFIT')
            ->where('discovery.hero.source_id', $featured->id));
    }

    private function benefit(Partner $partner, Location $location, array $attributes = []): Benefit
    {
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(array_merge(['applies_to_all_locations' => true], $attributes));

        return $benefit;
    }

    private function experience(Partner $partner, Location $location): Experience
    {
        $experience = Experience::factory()->published()->create();
        $experience->partners()->attach($partner, ['role' => 'HOST', 'sort_order' => 1]);
        ExperienceSession::factory()->upcoming()->for($experience)->withLocation($location)->create(['starts_at' => now()->addDays(20)]);

        return $experience;
    }

    private function unlock(Location $location): Unlock
    {
        $unlock = Unlock::create(['title' => 'Unlock de prueba', 'slug' => 'unlock-de-prueba', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE]);
        $unlock->locations()->attach($location);

        return $unlock;
    }
}
