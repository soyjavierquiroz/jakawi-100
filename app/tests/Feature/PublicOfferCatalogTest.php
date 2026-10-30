<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\LandingPresentation;
use App\Models\Location;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicOfferCatalogTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/catalog/v1/offers';

    private function landing(Model $offer, array $attributes = []): LandingPresentation
    {
        return $offer->landingPresentations()->create(array_merge([
            'name' => 'Public presentation', 'slug' => 'offer-'.strtolower(class_basename($offer)).'-'.$offer->id,
            'status' => 'PUBLISHED', 'default_scope' => 'ALL',
        ], $attributes));
    }

    public function test_public_benefit_is_allowlisted_and_the_read_does_not_write_or_set_cookies(): void
    {
        $partner = Partner::factory()->published()->create(['contact_email' => 'private@example.test', 'tax_id' => 'TAX-PRIVATE', 'internal_notes' => 'SECRET-NOTES']);
        $location = Location::factory()->published()->for($partner)->create(['city' => 'La Paz', 'city_id' => null]);
        $offer = Benefit::factory()->published()->for($partner)->create([
            'title' => 'Public benefit', 'short_description' => 'Public summary', 'applies_to_all_locations' => true,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'review_notes' => 'PRIVATE-REVIEW',
            'estimated_savings' => 9876, 'image_path' => 'public-benefit.jpg',
        ]);
        $landing = $this->landing($offer, ['campaign_key' => 'INTERNAL-CAMPAIGN', 'hero_path' => 'public-hero.jpg']);
        DB::enableQueryLog();
        $response = $this->get(self::URL)->assertOk()->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.0.id', 'benefit:'.$offer->id)->assertJsonPath('data.0.com_id', $offer->id)
            ->assertJsonPath('data.0.partners', [['id' => $partner->id, 'name' => $partner->name]])
            ->assertJsonPath('data.0.cities', ['La Paz'])->assertJsonPath('data.0.name', 'Public benefit')
            ->assertJsonPath('data.0.short_description', 'Public summary')->assertJsonPath('data.0.category', $offer->category)
            ->assertJsonPath('data.0.landing_url', route('landing-presentations.show', $landing->slug))
            ->assertJsonPath('data.0.image_url', 'https://jakawi.com/storage/public-hero.jpg')
            ->assertJsonPath('data.0.publication_status', 'published')->assertJsonPath('data.0.landing_status', 'PUBLISHED')
            ->assertJsonPath('data.0.availability_status', 'available')
            ->assertJsonPath('data.0.starts_at', $offer->starts_at->toISOString())
            ->assertJsonPath('data.0.ends_at', $offer->ends_at->toISOString())
            ->assertJsonPath('data.0.updated_at', $offer->updated_at->toISOString());
        $queries = DB::getQueryLog(); DB::disableQueryLog();
        foreach ($queries as $query) $this->assertMatchesRegularExpression('/^select/i', $query['query']);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertEqualsCanonicalizing(['id', 'com_id', 'type', 'name', 'short_description', 'image_url', 'partners', 'cities', 'category', 'landing_url', 'publication_status', 'landing_status', 'availability_status', 'updated_at', 'starts_at', 'ends_at'], array_keys($response->json('data.0')));
        foreach (['private@example.test', 'TAX-PRIVATE', 'SECRET-NOTES', 'PRIVATE-REVIEW', '9876', 'INTERNAL-CAMPAIGN', 'club_ref', 'contact_email', 'redemption_pin', 'commission'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
    }

    public function test_experience_preserves_multiple_public_partners_and_uses_real_session_cities_without_inventing_offer_dates(): void
    {
        $offer = Experience::factory()->published()->create(['regular_price' => 8765, 'member_price' => 7654]);
        $a = Partner::factory()->published()->create(); $b = Partner::factory()->published()->create();
        $private = Partner::factory()->create();
        $offer->partners()->attach($a, ['role' => 'host']);
        $offer->partners()->attach($b, ['role' => 'host']);
        $offer->partners()->attach($private, ['role' => 'host']);
        $location = Location::factory()->published()->for($a)->create(['city' => 'Cochabamba', 'city_id' => null]);
        ExperienceSession::factory()->for($offer)->for($location)->upcoming()->create();
        $this->landing($offer);
        $response = $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'experience')->assertJsonPath('data.0.id', 'experience:'.$offer->id)
            ->assertJsonCount(2, 'data.0.partners')->assertJsonPath('data.0.cities', ['Cochabamba'])
            ->assertJsonPath('data.0.availability_status', 'scheduled');
        $this->assertArrayNotHasKey('starts_at', $response->json('data.0'));
        $this->assertArrayNotHasKey('ends_at', $response->json('data.0'));
        foreach ([$private->name, '8765', '7654', 'regular_price', 'reservation_url'] as $value) $this->assertStringNotContainsString($value, $response->getContent());
    }

    public function test_history_and_time_based_states_remain_in_the_feed(): void
    {
        $this->freezeTime();
        $partner = Partner::factory()->published()->create();
        foreach (['paused' => [], 'archived' => [], 'expired' => ['ends_at' => now()->subSecond()], 'upcoming' => ['starts_at' => now()->addDay()]] as $state => $dates) {
            $offer = Benefit::factory()->published()->for($partner)->create($dates + ['status' => in_array($state, ['paused', 'archived']) ? $state : 'published']);
            $this->landing($offer);
        }
        foreach (['paused', 'archived', 'ended', 'unavailable', 'upcoming'] as $state) {
            $offer = Experience::factory()->published()->create(['status' => in_array($state, ['paused', 'archived']) ? $state : 'published', 'catalog_visibility' => $state === 'upcoming' ? 'upcoming' : 'legacy']);
            $offer->partners()->attach($partner, ['role' => 'host']);
            if ($state === 'ended') ExperienceSession::factory()->for($offer)->past()->create(['ends_at' => now()->subHour()]);
            $this->landing($offer);
        }
        $this->assertSame(['paused', 'archived', 'expired', 'upcoming', 'paused', 'archived', 'ended', 'unavailable', 'upcoming'], array_column($this->getJson(self::URL)->assertOk()->assertJsonCount(9, 'data')->json('data'), 'availability_status'));
    }

    public function test_drafts_hidden_grant_only_missing_and_unpublished_landings_are_excluded(): void
    {
        $partner = Partner::factory()->published()->create();
        foreach ([['status' => 'draft'], ['status' => 'paused', 'published_at' => null], ['access_mode' => Benefit::ACCESS_CHALLENGE_GRANT]] as $attributes) {
            $this->landing(Benefit::factory()->published()->for($partner)->create($attributes));
        }
        Benefit::factory()->published()->for($partner)->create();
        foreach (['DRAFT', 'ARCHIVED'] as $status) $this->landing(Benefit::factory()->published()->for($partner)->create(), ['status' => $status, 'default_scope' => 'NONE']);
        $this->landing(Benefit::factory()->published()->create()); // Private partner.
        foreach (['hidden', 'active'] as $visibility) {
            $offer = Experience::factory()->published()->create(['catalog_visibility' => $visibility]);
            if ($visibility === 'hidden') $offer->partners()->attach($partner, ['role' => 'host']);
            $this->landing($offer);
        }
        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 50, 'total' => 0, 'last_page' => 1], 'links' => ['next' => null, 'prev' => null]]);
    }

    public function test_pagination_and_identifiers_are_stable_and_landings_do_not_duplicate_offers(): void
    {
        $partner = Partner::factory()->published()->create();
        $ids = [];
        foreach (range(1, 3) as $i) {
            $offer = Benefit::factory()->published()->for($partner)->create(); $this->landing($offer);
            $ids[] = 'benefit:'.$offer->id;
        }
        $experience = Experience::factory()->published()->create(); $experience->partners()->attach($partner, ['role' => 'host']);
        $landing = $this->landing($experience); $ids[] = 'experience:'.$experience->id;
        $this->landing($experience, ['slug' => 'extra-campaign', 'default_scope' => 'NONE']);
        $first = $this->getJson(self::URL.'?per_page=2')->assertOk()->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2);
        $second = $this->getJson($first->json('links.next'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('links.next', null);
        $this->assertSame($ids, [...array_column($first->json('data'), 'id'), ...array_column($second->json('data'), 'id')]);
        $experience->update(['title' => 'A different title']);
        $this->getJson(self::URL.'?per_page=2&page=2')->assertJsonPath('data.1.id', end($ids))
            ->assertJsonPath('data.1.landing_url', route('landing-presentations.show', $landing->slug));
        $this->getJson(self::URL.'?page=99')->assertOk()->assertJsonCount(0, 'data');
        foreach (['per_page=0', 'per_page=101', 'page=-1', 'page=1.5', 'per_page[]=2'] as $bad) $this->get(self::URL.'?'.$bad)->assertUnprocessable();
        $this->postJson(self::URL)->assertMethodNotAllowed();
    }

    public function test_query_count_is_bounded_when_page_grows(): void
    {
        $partner = Partner::factory()->published()->create();
        foreach (range(1, 10) as $i) $this->landing(Benefit::factory()->published()->for($partner)->create());
        Model::preventLazyLoading();
        try {
            DB::enableQueryLog();
            $this->getJson(self::URL.'?per_page=1')->assertOk(); $small = count(DB::getQueryLog()); DB::flushQueryLog();
            $this->getJson(self::URL.'?per_page=10')->assertOk()->assertJsonCount(10, 'data'); $large = count(DB::getQueryLog());
            $this->assertSame($small, $large); $this->assertLessThanOrEqual(8, $large);
        } finally { DB::disableQueryLog(); Model::preventLazyLoading(false); }
    }

    public function test_guest_landing_choice_images_and_publication_changes_follow_existing_records(): void
    {
        $partner = Partner::factory()->published()->create();
        $offer = Benefit::factory()->published()->for($partner)->create(['image_path' => 'public-image.jpg']);
        $this->landing($offer, ['default_scope' => 'NONE']);
        $guest = $this->landing($offer, ['slug' => 'guest-offer', 'default_scope' => 'GUESTS', 'media_mode' => 'NONE']);
        $this->getJson(self::URL)->assertJsonCount(1, 'data')->assertJsonPath('data.0.image_url', null)
            ->assertJsonPath('data.0.landing_url', route('landing-presentations.show', $guest->slug));
        $offer->update(['status' => 'paused']);
        $this->getJson(self::URL)->assertJsonPath('data.0.id', 'benefit:'.$offer->id)->assertJsonPath('data.0.availability_status', 'paused');
        $partner->update(['status' => 'paused']);
        $this->getJson(self::URL)->assertJsonCount(0, 'data');
    }

    public function test_experience_with_session_in_progress_is_not_reported_as_ended(): void
    {
        $offer = Experience::factory()->published()->create();
        $offer->partners()->attach(Partner::factory()->published()->create(), ['role' => 'host']);
        ExperienceSession::factory()->for($offer)->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $this->landing($offer);
        $this->getJson(self::URL)->assertJsonPath('data.0.availability_status', 'unavailable');
    }
}
