<?php

namespace Tests\Feature;

use App\Discovery\BenefitDiscoveryQuery;
use App\Discovery\ExperienceDiscoveryQuery;
use App\Discovery\UnlockDiscoveryQuery;
use App\Models\Benefit;
use App\Models\City;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Unlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscoveryEligibilityQueryTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    private City $otherCity;

    private Partner $partner;

    private Location $location;

    private Location $otherLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->city = City::query()->where('slug', 'cochabamba')->sole();
        $this->otherCity = City::query()->where('slug', 'la-paz')->sole();
        $this->partner = Partner::factory()->published()->create();
        $this->location = Location::factory()->published()->withPartner($this->partner)->create(['city_id' => $this->city->id]);
        $this->otherLocation = Location::factory()->published()->withPartner($this->partner)->create(['city_id' => $this->otherCity->id]);
    }

    public function test_benefit_query_preserves_availability_and_city_geography(): void
    {
        $allLocations = Benefit::factory()->published()->forPartner($this->partner)->create(['applies_to_all_locations' => true]);
        $pivot = Benefit::factory()->published()->forPartner($this->partner)->create();
        $pivot->locations()->attach($this->location);
        $wrongCity = Benefit::factory()->published()->forPartner($this->partner)->create();
        $wrongCity->locations()->attach($this->otherLocation);
        $expired = Benefit::factory()->expired()->forPartner($this->partner)->create(['applies_to_all_locations' => true]);
        $draft = Benefit::factory()->draft()->forPartner($this->partner)->create(['applies_to_all_locations' => true]);
        $unpublishedPartner = Partner::factory()->create();
        $unpublishedPartnerLocation = Location::factory()->published()->withPartner($unpublishedPartner)->create(['city_id' => $this->city->id]);
        $unpublishedPartnerBenefit = Benefit::factory()->published()->forPartner($unpublishedPartner)->create(['applies_to_all_locations' => true]);

        $ids = app(BenefitDiscoveryQuery::class)->forCity($this->city)->pluck('id');

        $this->assertEqualsCanonicalizing([$allLocations->id, $pivot->id], $ids->all());
        $this->assertNotContains($wrongCity->id, $ids);
        $this->assertNotContains($expired->id, $ids);
        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($unpublishedPartnerBenefit->id, $ids);
        $this->assertSame($this->city->id, $unpublishedPartnerLocation->city_id);
    }

    public function test_experience_query_returns_only_relevant_city_sessions_without_new_publication_rules(): void
    {
        $experience = Experience::factory()->published()->create();
        $citySession = ExperienceSession::factory()->for($experience)->upcoming()->withLocation($this->location)->create();
        $otherSession = ExperienceSession::factory()->for($experience)->upcoming()->withLocation($this->otherLocation)->create();
        ExperienceSession::factory()->for($experience)->upcoming()->withoutLocation()->create(['venue_label' => 'Cochabamba']);
        $pastOnly = Experience::factory()->published()->create();
        ExperienceSession::factory()->for($pastOnly)->past()->withLocation($this->location)->create();

        $draftLocation = Location::factory()->withPartner($this->partner)->create(['city_id' => $this->city->id]);
        $unpublishedPartner = Partner::factory()->create();
        $publicationSemantics = Experience::factory()->published()->create();
        $publicationSemantics->partners()->attach($unpublishedPartner, ['role' => 'HOST']);
        $draftLocationSession = ExperienceSession::factory()->for($publicationSemantics)->upcoming()->withLocation($draftLocation)->create();

        $results = app(ExperienceDiscoveryQuery::class)->forCity($this->city)->get()->keyBy('id');

        $this->assertTrue($results->has($experience->id));
        $this->assertFalse($results->has($pastOnly->id));
        $this->assertTrue($results->has($publicationSemantics->id));
        $this->assertSame([$citySession->id], $results->get($experience->id)->sessions->pluck('id')->all());
        $this->assertSame($this->location->id, $results->get($experience->id)->sessions->sole()->location->id);
        $this->assertNotContains($otherSession->id, $results->get($experience->id)->sessions->pluck('id'));
        $this->assertSame($draftLocationSession->id, $results->get($publicationSemantics->id)->sessions->sole()->id);
    }

    public function test_unlock_query_preserves_states_geography_and_publication_semantics_without_progress_queries(): void
    {
        $active = $this->unlock(Unlock::ACTIVE, $this->location);
        $goalReached = $this->unlock(Unlock::GOAL_REACHED, $this->location);
        $wrongCity = $this->unlock(Unlock::ACTIVE, $this->otherLocation);
        $unlocked = $this->unlock(Unlock::UNLOCKED, $this->location);
        $draftLocation = Location::factory()->withPartner($this->partner)->create(['city_id' => $this->city->id]);
        $unpublishedLocationUnlock = $this->unlock(Unlock::ACTIVE, $draftLocation);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $results = app(UnlockDiscoveryQuery::class)->forCity($this->city)->get()->keyBy('id');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($results->has($active->id));
        $this->assertTrue($results->has($goalReached->id));
        $this->assertTrue($results->has($unpublishedLocationUnlock->id));
        $this->assertFalse($results->has($wrongCity->id));
        $this->assertFalse($results->has($unlocked->id));
        $this->assertSame([$this->city->id], $results->get($active->id)->locations->pluck('city_id')->all());
        $this->assertFalse(collect($queries)->contains(fn (array $query) => str_contains($query['query'], 'unlock_participations')));
    }

    private function unlock(string $status, Location $location): Unlock
    {
        $unlock = Unlock::create([
            'title' => fake()->sentence(2),
            'slug' => fake()->unique()->slug(),
            'origin' => 'JAKAWI',
            'type' => 'BENEFIT',
            'minimum_commitments' => 2,
            'free_user_eligible' => true,
            'member_eligible' => true,
            'status' => $status,
        ]);
        $unlock->locations()->attach($location);

        return $unlock;
    }
}
