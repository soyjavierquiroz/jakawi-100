<?php

namespace Tests\Feature;

use App\Models\{Benefit, Challenge, City, Experience, Location, Partner, User};
use App\Discovery\{DiscoveryContext, DiscoveryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GlobalPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_union_paginates_all_eligible_domains_before_hydration_at_100_and_500_rows(): void
    {
        $city = City::where('slug', 'cochabamba')->sole();
        $partner = Partner::factory()->published()->create();
        Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        Benefit::factory()->count(70)->published()->forPartner($partner)->create(['title' => 'Zulu', 'category' => 'food', 'applies_to_all_locations' => true, 'featured' => false]);
        Challenge::insert(collect(range(1, 30))->map(fn ($n) => ['title' => 'Alfa', 'slug' => 'pagination-'.$n, 'city_id' => $city->id, 'status' => 'open', 'reward_type' => 'JP', 'reward_jp_amount' => 10, 'review_status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()])->all());
        // These must never enter the union, the count or any page.
        Benefit::factory()->published()->forPartner($partner)->create(['access_mode' => 'social_challenge_grant', 'applies_to_all_locations' => true]);
        Benefit::factory()->forPartner($partner)->create(['status' => 'draft', 'applies_to_all_locations' => true]);
        Challenge::create(['title' => 'Hidden', 'slug' => 'pagination-hidden', 'status' => 'open', 'reward_type' => 'JP', 'reward_jp_amount' => 10, 'review_status' => 'APPROVED', 'published_from' => now()->addDay()]);
        $service = app(DiscoveryService::class);
        $context = new DiscoveryContext(city: $city, surface: 'explore');
        foreach ([100, 500] as $total) {
            if ($total === 500) Benefit::factory()->count(400)->published()->forPartner($partner)->create(['title' => 'Zulu', 'category' => 'food', 'applies_to_all_locations' => true, 'featured' => false]);
            $seen = [];
            for ($number = 1; $number <= (int) ceil($total / 18); $number++) {
                $page = $service->explorePage($context, sort: 'name', page: $number);
                $this->assertSame($total, $page->total());
                $this->assertCount(min(18, $total - ($number - 1) * 18), $page->items());
                foreach ($page->items() as $item) {
                    $key = $item->type->value.':'.$item->sourceId;
                    $this->assertArrayNotHasKey($key, $seen);
                    $seen[$key] = true;
                }
                if ($number === 1) $this->assertSame('CHALLENGE', $page->items()[0]->type->value);
            }
            $this->assertCount($total, $seen);
            $this->assertSame(collect($service->explorePage($context, sort: 'name')->items())->map(fn ($item) => $item->type->value.':'.$item->sourceId)->all(), collect($service->explorePage($context, sort: 'name')->items())->map(fn ($item) => $item->type->value.':'.$item->sourceId)->all());
            $last = $service->explorePage($context, sort: 'name', page: 999);
            $this->assertSame((int) ceil($total / 18), $last->currentPage());
        }
        $fixtures = [];
        foreach (['/explorar', '/explorar?page=2', '/explorar?page=3', '/explorar?sort=name', '/explorar?sort=name&page=2', '/explorar?q=Alfa', '/explorar?q=missing', '/explorar?type=challenges', '/explorar?type=challenges&page=2'] as $url) {
            $fixtures[$url] = json_decode($this->get($url, ['X-Inertia' => 'true'])->assertOk()->getContent());
        }
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['/admin/benefits', '/admin/benefits?page=2', '/admin/partners', '/admin/locations', '/admin/campaigns', '/admin/memberships', '/admin/redemptions', '/admin/reward-rules', '/admin/expansion', '/admin/solicitudes-membresia'] as $url) {
            $fixtures[$url] = json_decode($this->get($url, ['X-Inertia' => 'true'])->assertOk()->getContent());
        }
        file_put_contents(storage_path('app/pagination-browser.json'), json_encode($fixtures));
        $this->assertSame(0, $service->explorePage($context, term: 'nothing-matches')->total());
        $this->assertSame(470, $service->explorePage($context, category: 'food')->total());
    }

    public function test_admin_partner_and_location_filters_run_before_pagination_and_keep_urls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Partner::factory()->count(100)->create(['status' => 'draft']);
        $wanted = Partner::factory()->published()->create(['name' => 'Needle']);
        Location::factory()->count(30)->withPartner($wanted)->create();
        $this->actingAs($admin)->get('/admin/partners')->assertInertia(fn (Assert $p) => $p->has('items.data', 25)->where('items.total', 101));
        $this->get('/admin/partners?q=Needle&status=published&page=9')->assertInertia(fn (Assert $p) => $p->has('items.data', 1)->where('items.current_page', 1)->where('items.data.0.id', $wanted->id));
        $this->get('/admin/locations?partner='.$wanted->id)->assertInertia(fn (Assert $p) => $p->has('items.data', 25)->where('items.total', 30)->where('items.next_page_url', fn ($url) => str_contains($url, 'partner='.$wanted->id)));
        $this->get('/admin/partners?q=missing')->assertInertia(fn (Assert $p) => $p->has('items.data', 0)->where('items.total', 0));
        $this->get('/admin/partners?per_page=101')->assertSessionHasErrors('per_page');
        $this->get('/explorar?sort=&page=')->assertOk();
        $this->get('/explorar?sort=unsafe')->assertSessionHasErrors('sort');
        $this->get('/explorar?per_page=19')->assertSessionHasErrors('per_page');
        $this->get('/explorar?page=-1')->assertSessionHasErrors('page');
        $this->actingAs(User::factory()->create())->get('/admin/partners')->assertForbidden();
    }
}
