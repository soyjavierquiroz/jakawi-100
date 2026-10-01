<?php

namespace Tests\Feature;

use App\Discovery\DiscoveryContext;
use App\Discovery\DiscoveryService;
use App\Discovery\OpportunityType;
use App\Models\Benefit;
use App\Models\City;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_all_domains_in_one_city_and_batches_unlock_progress(): void
    {
        $city = City::query()->where('slug', 'cochabamba')->sole();
        $otherCity = City::query()->where('slug', 'la-paz')->sole();
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $otherLocation = Location::factory()->published()->withPartner($partner)->create(['city_id' => $otherCity->id]);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        $experience = Experience::factory()->published()->create();
        $experience->partners()->attach($partner, ['role' => 'HOST', 'sort_order' => 1]);
        ExperienceSession::factory()->for($experience)->upcoming()->withLocation($location)->create();
        $unlock = $this->unlock($location);
        UnlockParticipation::create(['unlock_id' => $unlock->id, 'user_id' => User::factory()->create()->id, 'status' => UnlockParticipation::COMMITTED]);
        $wrongCity = $this->unlock($otherLocation);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = app(DiscoveryService::class)->discover(new DiscoveryContext($city, candidateLimitPerDomain: 10));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $items = collect([$result->hero, ...$result->forYou, ...$result->happeningNow, ...$result->discoverMore])->filter();

        $this->assertEqualsCanonicalizing([OpportunityType::BENEFIT, OpportunityType::EXPERIENCE, OpportunityType::UNLOCK], $items->pluck('type')->unique()->all());
        $this->assertTrue($items->every(fn ($item) => $item->city->id === $city->id));
        $this->assertFalse($items->contains(fn ($item) => $item->sourceId === $wrongCity->id));
        $this->assertSame(1, collect($queries)->filter(fn (array $query) => str_contains($query['query'], 'unlock_participations'))->count());
    }

    private function unlock(Location $location): Unlock
    {
        $unlock = Unlock::create([
            'title' => fake()->sentence(2), 'slug' => fake()->unique()->slug(), 'origin' => 'JAKAWI', 'type' => 'BENEFIT',
            'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE,
        ]);
        $unlock->locations()->attach($location);

        return $unlock;
    }
}
