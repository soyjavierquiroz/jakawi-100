<?php

namespace Tests\Feature;

use App\Models\{Benefit, Challenge, City, DigitalGift, DigitalProduct, Experience, ExperienceSession, LandingPresentation, Location, Membership, Partner, Unlock, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExploreDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function digital(array $attributes = []): DigitalProduct
    {
        return DigitalProduct::create($attributes + ['partner_id' => Partner::where('slug', 'jakawi-digital')->value('id'), 'name' => 'Entrenamiento digital', 'slug' => 'entrenamiento-digital', 'status' => 'published', 'currency' => 'BOB', 'public_price' => 49, 'member_price' => 29]);
    }

    public function test_enabled_sale_and_incomplete_hosted_draft_do_not_break_guest_or_authenticated_explore(): void
    {
        $published = $this->digital(['slug'=>'gluteo-perfecto', 'delivery_type'=>'hosted_app', 'app_key'=>'gluteos']);
        $draft = $this->digital(['slug'=>'pilates-app-es', 'name'=>'Pilates en casa', 'status'=>'draft', 'delivery_type'=>'hosted_app', 'app_key'=>'pilates-app-es', 'description'=>null, 'cover_path'=>null]);
        \App\Models\AppSetting::updateOrCreate(['key'=>\App\Services\DigitalSaleAvailability::KEY], ['value'=>['enabled'=>true, 'product_id'=>$published->id]]);
        LandingPresentation::create(['subject_type'=>DigitalProduct::class, 'subject_id'=>$published->id, 'name'=>'App', 'slug'=>'app-coming', 'status'=>'PUBLISHED', 'default_scope'=>'ALL', 'editorial_blocks'=>[['type'=>'hero', 'availability'=>'COMING_SOON']]]);
        $assert = fn (Assert $p) => $p->component('explore')->has('results', 1)->where('results.0.product.id', $published->id)->where('results.0.product.coming_soon', true);
        $this->get('/explorar?type=digital')->assertOk()->assertInertia($assert);
        $this->actingAs(User::factory()->create())->get('/explorar?type=digital&sort=name&page=1')->assertOk()->assertInertia($assert);
        $draft->update(['status'=>'published']);
        $this->get('/explorar?type=digital&q=Pilates')->assertOk()->assertInertia(fn (Assert $p) => $p->has('results', 1)->where('results.0.product.id', $draft->id));
    }

    public function test_one_search_and_each_type_preserve_eligibility_and_city(): void
    {
        $city = City::where('slug', 'cochabamba')->sole();
        $partner = Partner::factory()->published()->create(['name' => 'Entrenamiento lugar', 'category' => 'fitness']);
        $place = Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['title' => 'Entrenamiento beneficio', 'category' => 'fitness', 'applies_to_all_locations' => true, 'featured' => true]);
        $experience = Experience::factory()->published()->create(['title' => 'Entrenamiento experiencia', 'category' => 'fitness']);
        ExperienceSession::factory()->for($experience)->upcoming()->withLocation($place)->create();
        $unlock = Unlock::create(['title' => 'Entrenamiento desbloqueo', 'slug' => 'entrenamiento-desbloqueo', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'status' => Unlock::ACTIVE]);
        $unlock->locations()->attach($place);
        $challenge = Challenge::create(['title' => 'Entrenamiento reto', 'slug' => 'entrenamiento-reto', 'review_status' => 'APPROVED', 'status' => 'open', 'city_id' => $city->id, 'reward_type' => 'JP', 'reward_jp_amount' => 25]);
        $digital = $this->digital();
        $this->digital(['slug' => 'borrador', 'status' => 'draft']);
        $get = fn ($q = '') => $this->withCookie('selected_city', 'cochabamba')->get('/explorar?q=entrenamiento'.$q);
        $get()->assertOk()->assertInertia(fn (Assert $p) => $p->has('results', 6)->where('pagination.total', 6)->where('results.0.identity', 'BENEFIT:'.$benefit->id)->missing('digitalProducts')->missing('opportunities')->missing('places'));
        foreach (['benefits' => 'BENEFIT:'.$benefit->id, 'experiences' => 'EXPERIENCE:'.$experience->id, 'challenges' => 'CHALLENGE:'.$challenge->id, 'unlocks' => 'UNLOCK:'.$unlock->id, 'places' => 'PLACE:'.$partner->id, 'digital' => 'DIGITAL:'.$digital->id] as $filter => $identity) {
            $get('&type='.$filter)->assertInertia(fn (Assert $p) => $p->has('results', 1)->where('results.0.identity', $identity)->where('pagination.total', 1));
        }
        $get('&category=fitness')->assertInertia(fn (Assert $p) => $p->has('results', 3));
        $get('&type=unlocks&category=fitness')->assertInertia(fn (Assert $p) => $p->has('results', 0));
        $other = City::where('slug', 'la-paz')->sole(); $other->update(['status' => City::ACTIVE]);
        $this->withCookie('selected_city', 'la-paz')->get('/explorar?q=entrenamiento')->assertInertia(fn (Assert $p) => $p->has('results', 1)->where('results.0.identity', 'DIGITAL:'.$digital->id));
    }

    public function test_combined_pagination_and_global_order_with_a_thousand_products_have_bounded_queries(): void
    {
        $city = City::where('slug', 'cochabamba')->sole();
        $partner = Partner::factory()->published()->create(['name' => 'Volume place']);
        Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['title' => 'Volume 0002 B', 'featured' => true, 'applies_to_all_locations' => true, 'published_at' => now()->addMinute()]);
        $rows = [];
        $digitalPartnerId = Partner::where('slug', 'jakawi-digital')->value('id');
        for ($n = 1; $n <= 1000; $n++) $rows[] = ['partner_id' => $digitalPartnerId, 'name' => sprintf('Volume %04d A', $n), 'slug' => 'volume-'.$n, 'status' => 'published', 'currency' => 'BOB', 'created_at' => now()->subDay()];
        DigitalProduct::insert($rows);
        $read = function ($query) {
            return $this->withCookie('selected_city', 'cochabamba')->get('/explorar?q=Volume&'.$query)->assertOk()->inertiaProps();
        };
        $featured = $read(''); $this->assertSame('BENEFIT:'.$benefit->id, $featured['results'][0]['identity']);
        $this->assertSame('BENEFIT:'.$benefit->id, $read('sort=newest')['results'][0]['identity']);
        DB::enableQueryLog(); $first = $read('sort=name'); $queries = count(DB::getQueryLog()); DB::disableQueryLog();
        $second = $read('sort=name&page=2'); $last = $read('sort=name&page=56');
        $this->assertSame(1002, $first['pagination']['total']);
        $this->assertCount(18, $first['results']); $this->assertCount(18, $second['results']); $this->assertCount(12, $last['results']);
        $this->assertSame('Volume 0001 A', $first['results'][0]['product']['name']);
        $this->assertSame('BENEFIT:'.$benefit->id, $first['results'][2]['identity']);
        $this->assertEmpty(array_intersect(array_column($first['results'], 'identity'), array_column($second['results'], 'identity')));
        $this->assertNull($last['pagination']['next_page_url']);
        $this->assertLessThan(45, $queries, 'Card queries must be bounded, not per product');
        $this->assertStringContainsString('sort=name', $first['pagination']['next_page_url']);
        $this->assertSame(1, $read('type=digital&q=Volume%200999')['pagination']['total']);
    }

    public function test_welcome_context_and_coming_soon_are_preserved_without_claims(): void
    {
        config(['jakawi.prelaunch_mode' => true, 'digital_gifts.welcome_prelaunch_enabled' => true]);
        $user = User::factory()->create();
        $membership = Membership::withoutEvents(fn () => Membership::create(['user_id' => $user->id, 'type' => 'pioneer', 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addYear(), 'amount_paid' => 100]));
        $gift = DigitalGift::firstOrCreate(['kind' => 'welcome', 'user_id' => $user->id], ['membership_id' => $membership->id, 'granted_at' => now()]);
        $product = $this->digital(['welcome_gift_enabled' => true, 'file_path' => 'products/fixture.pdf', 'file_sha256' => str_repeat('a', 64), 'gift_pdf_sha256' => str_repeat('a', 64)]);
        $this->digital(['slug' => 'not-eligible']);
        LandingPresentation::create(['subject_type' => DigitalProduct::class, 'subject_id' => $product->id, 'name' => 'Digital', 'slug' => 'digital-coming', 'status' => 'PUBLISHED', 'default_scope' => 'ALL', 'editorial_blocks' => [['type' => 'hero', 'availability' => 'COMING_SOON']]]);
        $this->actingAs($user)->get('/mi-jakawi')->assertInertia(fn (Assert $p) => $p->where('hasWelcomeGift', true));
        $this->get('/explorar?type=digital&gifts=1')->assertInertia(fn (Assert $p) => $p->component('explore')->where('giftFilter', true)->where('type', 'digital')->has('results', 1)->where('results.0.product.id', $product->id)->where('results.0.product.coming_soon', true)->where('results.0.product.public_price', '49.00')->where('results.0.product.member_price', '29.00'));
        $this->assertSame('pending', $gift->fresh()->status);
        $this->assertDatabaseCount('digital_access_grants', 0); $this->assertDatabaseCount('digital_orders', 0);
    }
}
