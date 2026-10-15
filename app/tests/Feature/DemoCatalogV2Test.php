<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCatalogV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_lifecycle_is_idempotent_safe_and_respects_public_visibility(): void
    {
        Storage::fake('public');
        $real = Partner::factory()->published()->create(['slug' => 'real-partner']);
        Artisan::call('jakawi:seed-demo-catalog');
        $this->assertCounts();
        $this->assertSame(6, Benefit::where('slug', 'like', 'demo-%')->where('featured', true)->count());
        $this->assertTrue(Benefit::where('slug', 'like', 'demo-%')->where('applies_to_all_locations', true)->exists());
        $this->assertTrue(Benefit::where('slug', 'like', 'demo-%')->where('applies_to_all_locations', false)->has('locations')->exists());
        $this->assertTrue(Location::where('slug', 'like', 'demo-%')->whereNotNull('redemption_pin_hash')->get()->every(fn (Location $l) => Hash::check('123456', $l->redemption_pin_hash)));
        Storage::disk('public')->assertExists('demo/partners/demo-altura-nube-logo.svg');
        $this->assertSame(3, Experience::where('slug', 'demo-sunset-jakawi')->sole()->partners()->count());
        Artisan::call('jakawi:seed-demo-catalog');
        $this->assertCounts();
        $this->get('/partners/demo-altura-nube')->assertStatus(301)->assertRedirect(url('/demo-altura-nube'));
        foreach (['/', '/beneficios', '/experiencias', '/demo-altura-nube', '/lugares/demo-altura-nube-cala-cala', '/beneficios/demo-beneficio-01'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertSame('hidden', Experience::where('slug', 'demo-sunset-jakawi')->sole()->catalog_visibility);
        $this->get('/experiencias/demo-sunset-jakawi')->assertNotFound();
        Artisan::call('jakawi:clear-demo-catalog');
        $this->assertCounts(0, 0, 0, 0, 0);
        $this->assertDatabaseHas('partners', ['id' => $real->id]);
        Storage::disk('public')->assertMissing('demo/partners/demo-altura-nube-logo.svg');
    }

    private function assertCounts(int $partners = 13, int $locations = 18, int $benefits = 30, int $experiences = 8, int $sessions = 14): void
    {
        $this->assertSame($partners, Partner::where('slug', 'like', 'demo-%')->count());
        $this->assertSame($locations, Location::where('slug', 'like', 'demo-%')->count());
        $this->assertSame($benefits, Benefit::where('slug', 'like', 'demo-%')->count());
        $items = Experience::where('slug', 'like', 'demo-%');
        $this->assertSame($experiences, $items->count());
        $this->assertSame($sessions, ExperienceSession::whereIn('experience_id', $items->pluck('id'))->count());
    }
}
