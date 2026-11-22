<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function destinations(): array
    {
        $collect = function (array $groups) use (&$collect): array {
            $paths = [];
            foreach ($groups as $group) {
                $paths = [...$paths, ...array_column($group['links'], 0), ...$collect($group['secondary'] ?? [])];
            }
            return $paths;
        };

        return ['/admin', ...$collect(json_decode(file_get_contents(resource_path('admin-navigation.json')), true))];
    }

    public function test_sidebar_is_flat_and_preserves_all_existing_destinations(): void
    {
        $groups = json_decode(file_get_contents(resource_path('admin-navigation.json')), true);
        $this->assertCount(4, $groups);
        foreach ($groups as $group) {
            $this->assertArrayNotHasKey('secondary', $group);
        }
        $expected = ['/admin', '/admin/memberships', '/admin/conciliacion-membresias', '/admin/sales', '/admin/solicitudes-membresia', '/admin/promoters', '/admin/partners', '/admin/solicitudes-partner', '/admin/locations', '/admin/benefits', '/admin/experiences', '/admin/redemptions', '/admin/review', '/admin/retos', '/admin/desbloqueos', '/admin/productos-digitales', '/admin/digital/pedidos', '/admin/digital/cobro', '/admin/landings', '/admin/attribution', '/admin/ciudades', '/admin/expansion', '/admin/reward-rules', '/admin/adjustments', '/admin/campaigns', '/admin/settings', '/admin/payouts'];
        $this->assertEqualsCanonicalizing($expected, $this->destinations());
        $this->assertCount(count($expected), array_unique($this->destinations()));
    }

    public function test_all_navigation_destinations_keep_guest_and_non_admin_authorization(): void
    {
        foreach ($this->destinations() as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        foreach ($this->destinations() as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_admin_retains_primary_secondary_and_historical_destinations(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach ($this->destinations() as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_legacy_individual_affiliate_redirects_are_preserved(): void
    {
        foreach (['/afiliados', '/afiliados/aplicar', '/affiliate/legacy', '/creator/legacy'] as $path) {
            $this->get($path)->assertRedirect('https://jakawi.club/afiliados');
        }
    }

    public function test_reconciliation_uses_shared_navigation_and_external_club_authorization(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/conciliacion-membresias')
            ->assertOk()
            ->assertSee('https://jakawi.club/admin/comisiones', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('Operación JAKAWI')
            ->assertSee('Configuración editorial')
            ->assertSee('Pagos a Partners · histórico/incompleto');
    }
}
