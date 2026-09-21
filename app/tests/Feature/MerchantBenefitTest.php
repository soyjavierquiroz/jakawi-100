<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantBenefitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_catalog_responds(): void
    {
        $this->get('/beneficios')->assertOk();
    }

    public function test_active_benefit_appears_in_catalog(): void
    {
        Benefit::factory()->create(['title' => 'Visible Benefit']);

        $this->get('/beneficios')->assertOk()->assertSee('Visible Benefit');
    }

    public function test_inactive_benefit_does_not_appear_in_catalog(): void
    {
        Benefit::factory()->inactive()->create(['title' => 'Hidden Benefit']);

        $this->get('/beneficios')->assertOk()->assertDontSee('Hidden Benefit');
    }

    public function test_expired_benefit_does_not_appear_in_catalog(): void
    {
        Benefit::factory()->expired()->create(['title' => 'Expired Benefit']);

        $this->get('/beneficios')->assertOk()->assertDontSee('Expired Benefit');
    }

    public function test_inactive_merchant_hides_benefits(): void
    {
        $merchant = Merchant::factory()->inactive()->create();
        Benefit::factory()->create([
            'merchant_id' => $merchant->id,
            'title' => 'Merchant Hidden Benefit',
        ]);

        $this->get('/beneficios')->assertOk()->assertDontSee('Merchant Hidden Benefit');
    }

    public function test_detail_by_slug_works(): void
    {
        $benefit = Benefit::factory()->create(['slug' => 'demo-benefit']);

        $this->get("/beneficios/{$benefit->slug}")
            ->assertOk()
            ->assertSee($benefit->title);
    }

    public function test_admin_requires_auth(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_create_merchant(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/admin/merchants', [
                'name' => 'Demo Merchant',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 0,
            ])
            ->assertRedirect('/admin/merchants');

        $this->assertDatabaseHas('merchants', [
            'name' => 'Demo Merchant',
            'slug' => 'demo-merchant',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_benefit(): void
    {
        $merchant = Merchant::factory()->create();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/admin/benefits', [
                'merchant_id' => $merchant->id,
                'title' => 'Demo Benefit',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect('/admin/benefits');

        $this->assertDatabaseHas('benefits', [
            'merchant_id' => $merchant->id,
            'title' => 'Demo Benefit',
            'slug' => 'demo-benefit',
        ]);
    }
}
