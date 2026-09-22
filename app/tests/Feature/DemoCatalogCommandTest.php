<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_is_complete_current_and_idempotent(): void
    {
        $this->artisan('jakawi:seed-demo-catalog')->assertSuccessful();

        $this->assertSame(12, Merchant::query()->where('slug', 'like', 'demo-%')->count());
        $this->assertSame(30, Benefit::query()->where('slug', 'like', 'demo-%')->count());
        $this->assertSame(5, Benefit::query()->where('slug', 'like', 'demo-%')->where('is_featured', true)->count());
        $this->assertSame(12, Merchant::query()->where('slug', 'like', 'demo-%')->where('is_active', true)->count());
        $this->assertSame(30, Benefit::query()->where('slug', 'like', 'demo-%')->where('is_active', true)->count());
        $this->assertTrue(Merchant::query()->pluck('slug')->every(fn (string $slug) => str_starts_with($slug, 'demo-')));
        $this->assertTrue(Benefit::query()->pluck('slug')->every(fn (string $slug) => str_starts_with($slug, 'demo-')));
        $this->assertSame(30, Benefit::query()->where('slug', 'like', 'demo-%')->where('starts_at', '<=', now())->where('ends_at', '>=', now())->count());
        $this->assertSame(30, Benefit::query()->where('slug', 'like', 'demo-%')->whereIn('estimated_savings', [10, 15, 20, 25, 30, 35, 40, 50])->count());
        $this->assertSame(20, Benefit::query()->where('slug', 'like', 'demo-%')->where('redemption_limit_per_member', 1)->count());
        $this->assertSame(5, Benefit::query()->where('slug', 'like', 'demo-%')->where('redemption_limit_per_member', 2)->count());
        $this->assertSame(5, Benefit::query()->where('slug', 'like', 'demo-%')->whereNull('redemption_limit_per_member')->count());

        $this->artisan('jakawi:seed-demo-catalog')->assertSuccessful();

        $this->assertSame(12, Merchant::query()->where('slug', 'like', 'demo-%')->count());
        $this->assertSame(30, Benefit::query()->where('slug', 'like', 'demo-%')->count());
    }

    public function test_clear_removes_only_demo_catalog_records(): void
    {
        $merchant = Merchant::factory()->create(['slug' => 'real-merchant']);
        $benefit = Benefit::factory()->create([
            'merchant_id' => $merchant->id,
            'slug' => 'real-benefit',
        ]);

        $this->artisan('jakawi:seed-demo-catalog')->assertSuccessful();
        $this->artisan('jakawi:clear-demo-catalog')
            ->expectsOutput('Deleted 30 demo benefits and 12 demo merchants.')
            ->assertSuccessful();

        $this->assertDatabaseCount('merchants', 1);
        $this->assertDatabaseCount('benefits', 1);
        $this->assertDatabaseHas('merchants', ['id' => $merchant->id, 'slug' => 'real-merchant']);
        $this->assertDatabaseHas('benefits', ['id' => $benefit->id, 'slug' => 'real-benefit']);
    }
}
