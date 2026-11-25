<?php
namespace Tests\Feature;
use App\Models\{Benefit, Location, Membership, Partner, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
class MiJakawiPresentationTest extends TestCase
{
    use RefreshDatabase;
    public function test_cards_reuse_detail_availability_and_distinguish_free_from_member_access(): void
    {
        $user = User::factory()->create();
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456'); $location->save();
        $free = Benefit::factory()->published()->forPartner($partner)->create(['featured' => true, 'access_mode' => 'authenticated', 'applies_to_all_locations' => true, 'sort_order' => 0, 'image_path' => 'benefits/existing.jpg']);
        Benefit::factory()->published()->forPartner($partner)->create(['featured' => true, 'access_mode' => 'public', 'applies_to_all_locations' => true, 'sort_order' => 1]);
        Benefit::factory()->published()->forPartner($partner)->create(['access_mode' => 'social_challenge_grant', 'sort_order' => 2]);
        Benefit::factory()->forPartner($partner)->create(['status' => 'draft']);
        $this->actingAs($user)->get('/mi-jakawi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('mi-jakawi')->where('membershipStatus.type', 'free')->where('membershipStatus.benefits.exclusive_member_benefits', false)
            ->has('featuredBenefits', 2)->where('featuredBenefits.0.slug', $free->slug)
            ->where('featuredBenefits.0.access_label', 'Disponible con tu cuenta JAKAWI · Revisa las condiciones')
            ->where('featuredBenefits.0.availability.available', true)
            ->where('featuredBenefits.0.image_url', fn ($url) => str_ends_with($url, '/storage/benefits/existing.jpg'))
            ->where('featuredBenefits.1.access_label', 'Requiere membresía activa')->has('challenges', 0));
        $location->update(['status' => 'draft']);
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $page) => $page->where('featuredBenefits.0.availability.available', false));
    }
    public function test_pioneer_uses_existing_effective_status_and_keeps_benefit_cards(): void
    {
        config(['jakawi.membership.official_launch_date' => null]);
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'type' => 'pioneer', 'status' => 'active', 'starts_at' => now()->subYears(2), 'ends_at' => now()->subYear(), 'amount_paid' => 100]);
        $partner = Partner::factory()->published()->create();
        Benefit::factory()->published()->forPartner($partner)->create();
        $this->actingAs($user)->get('/mi-jakawi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('membershipStatus.type', 'pioneer')->where('membershipStatus.status', 'active')
            ->where('membershipStatus.effective_ends_at', null)->where('membershipStatus.benefits.exclusive_member_benefits', true)
            ->has('featuredBenefits', 1)->where('featuredBenefits.0.access_label', 'Con tu membresía · Revisa las condiciones'));
    }
    public function test_library_count_is_unique_active_and_scoped_to_the_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->get('/mi-jakawi')->assertInertia(fn (Assert $p) => $p->where('digitalProductCount', 0));
        $products = collect(range(1, 3))->map(fn ($n) => \App\Models\DigitalProduct::create(['partner_id' => Partner::where('slug', 'jakawi-digital')->value('id'), 'name' => "QA $n", 'slug' => "qa-count-$n", 'status' => 'published', 'currency' => 'BOB']));
        $grant = function ($owner, $product, $status = 'active', $revoked = null) {
            // Distinct orders can grant the same product; the dashboard counts products.
            $order = \App\Models\DigitalOrder::create(['reference' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $owner->id, 'digital_product_id' => $product->id, 'status' => 'confirmed', 'product_name' => $product->name, 'product_slug' => $product->slug, 'partner_id' => $product->partner_id, 'public_price' => 10, 'amount' => 10, 'currency' => 'BOB', 'member_at_purchase' => false, 'file_path' => 'qa/count.pdf', 'file_sha256' => str_repeat('a', 64), 'collection_snapshot' => [], 'expires_at' => now()->addDay()]);
            \App\Models\DigitalAccessGrant::create(['digital_order_id' => $order->id, 'user_id' => $owner->id, 'digital_product_id' => $product->id, 'source' => 'purchase', 'status' => $status, 'revoked_at' => $revoked]);
        };
        $grant($user, $products[0]);
        $grant($user, $products[0]);
        $grant($user, $products[1], 'revoked', now());
        $grant($user, $products[2], 'active', now());
        $grant($other, $products[1]);
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $p) => $p->where('digitalProductCount', 1));
        $grant($user, $products[1]);
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $p) => $p->where('digitalProductCount', 2));
    }
}
