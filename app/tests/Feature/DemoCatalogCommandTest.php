<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_is_complete_current_and_idempotent(): void
    {
        Storage::fake('public');
        $this->artisan('jakawi:seed-demo-catalog')->assertSuccessful();

        $this->assertSame(12, Merchant::query()->where('slug', 'like', 'demo-%')->count());
        $this->assertSame(30, Benefit::query()->where('slug', 'like', 'demo-%')->count());
        $this->assertSame(12, Merchant::query()->where('slug', 'like', 'demo-%')->whereNotNull('logo_path')->count());
        $this->assertSame(12, Merchant::query()->where('slug', 'like', 'demo-%')->whereNotNull('cover_path')->count());
        $this->assertSame(30, Benefit::query()->where('slug', 'like', 'demo-%')->whereNotNull('image_path')->count());
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
        Storage::fake('public');
        Storage::disk('public')->put('merchants/real-merchant.svg', '<svg/>');
        $merchant = Merchant::factory()->create(['slug' => 'real-merchant']);
        $benefit = Benefit::factory()->create([
            'merchant_id' => $merchant->id,
            'slug' => 'real-benefit',
        ]);

        $this->artisan('jakawi:seed-demo-catalog')->assertSuccessful();
        $this->artisan('jakawi:clear-demo-catalog')
            ->expectsOutput('Deleted 30 demo benefits and 12 demo merchants, plus demo images.')
            ->assertSuccessful();

        $this->assertDatabaseCount('merchants', 1);
        $this->assertDatabaseCount('benefits', 1);
        $this->assertDatabaseHas('merchants', ['id' => $merchant->id, 'slug' => 'real-merchant']);
        $this->assertDatabaseHas('benefits', ['id' => $benefit->id, 'slug' => 'real-benefit']);
        Storage::disk('public')->assertMissing('demo/merchants/demo-altura-nube-cafe-logo.svg');
        Storage::disk('public')->assertExists('merchants/real-merchant.svg');
    }

    public function test_generator_creates_deterministic_assets_and_force_regenerates_them(): void
    {
        Storage::fake('public');
        $this->artisan('jakawi:seed-demo-catalog')->assertSuccessful();

        $disk = Storage::disk('public');
        $logo = 'demo/merchants/demo-altura-nube-cafe-logo.svg';
        $benefitImage = 'demo/benefits/demo-altura-nube-cafe-cafe-frio-2x1.svg';
        $firstLogo = $disk->get($logo);

        $disk->assertExists($logo);
        $disk->assertExists($benefitImage);
        $this->assertStringContainsString('<svg', $firstLogo);

        $this->artisan('jakawi:generate-demo-images')
            ->expectsOutput('Demo images ready: 0 merchant logos, 0 merchant covers, 0 benefit images generated; 54 existing files skipped.')
            ->assertSuccessful();
        $this->artisan('jakawi:generate-demo-images --force')
            ->expectsOutput('Demo images ready: 12 merchant logos, 12 merchant covers, 30 benefit images generated; 0 existing files skipped.')
            ->assertSuccessful();

        $this->assertSame($firstLogo, $disk->get($logo));
    }
}
