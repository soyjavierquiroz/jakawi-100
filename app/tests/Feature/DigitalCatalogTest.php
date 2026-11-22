<?php

namespace Tests\Feature;

use App\Models\DigitalProduct;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_partner_has_no_location_and_catalog_is_global(): void
    {
        $p = Partner::where('slug', 'jakawi-digital')->firstOrFail();
        $this->assertSame(0, $p->locations()->count());
        $digital = DigitalProduct::create(['partner_id' => $p->id, 'name' => 'Manual QA', 'slug' => 'manual-qa', 'description' => 'Contenido buscable remoto', 'public_price' => '9.90', 'status' => 'published', 'currency' => 'BOB']);
        $this->get('/productos-digitales?q=remoto')->assertOk()->assertInertia(fn (Assert $a) => $a->component('explore')->where('type', 'digital')->has('digitalProducts', 1)->where('opportunities', [])->where('places', [])->where('categories', []));
        $this->get('/explorar?q=remoto')->assertOk()->assertInertia(fn (Assert $a) => $a->has('digitalProducts', 1)->where('digitalProducts.0.name', 'Manual QA'));
        $this->get('/productos-digitales/manual-qa')->assertOk()->assertInertia(fn (Assert $a) => $a->component('digital/show')->missing('product.file_path'));
        $this->get('/productos-digitales/manual-qa')->assertOk();
        $digital->update(['status' => 'draft']);
        $this->get('/productos-digitales/manual-qa')->assertNotFound();
    }

    public function test_digital_filter_and_direct_catalog_share_search_sort_pagination_and_ignore_physical_filters(): void
    {
        $partner = Partner::where('slug', 'jakawi-digital')->sole();
        foreach (range(1, 20) as $n) {
            DigitalProduct::create(['partner_id' => $partner->id, 'name' => sprintf('Manual %02d', $n), 'slug' => 'manual-'.$n, 'commercial_content' => 'Contenido remoto', 'status' => 'published', 'currency' => 'BOB']);
        }
        DigitalProduct::create(['partner_id' => $partner->id, 'name' => 'Borrador', 'slug' => 'borrador', 'status' => 'draft']);
        $hidden = Partner::factory()->create(['status' => 'draft']);
        DigitalProduct::create(['partner_id' => $hidden->id, 'name' => 'Oculto', 'slug' => 'oculto', 'status' => 'published']);

        foreach (['/explorar?type=digital', '/productos-digitales?type=places'] as $url) {
            $this->withCookie('selected_city', 'la-paz')->get($url.'&category=cafe&q=remoto&sort=name')->assertOk()->assertInertia(fn (Assert $a) => $a
                ->component('explore')->where('type', 'digital')->where('category', null)->where('categories', [])
                ->where('places', [])->where('opportunities', [])->has('digitalProducts', 18)
                ->where('digitalProducts.0.name', 'Manual 01')->where('pagination.total', 20)->where('pagination.last_page', 2)
                ->where('sort', 'name')->missing('digitalProducts.0.file_path'));
        }
        $this->get('/explorar?type=digital&sort=name&page=2')->assertInertia(fn (Assert $a) => $a->has('digitalProducts', 2)->where('digitalProducts.0.name', 'Manual 19'));
        $this->get('/productos-digitales?sort=newest')->assertInertia(fn (Assert $a) => $a->where('digitalProducts.0.name', 'Manual 20'));
        $this->get('/explorar?type=digital&q=Manual%2003')->assertInertia(fn (Assert $a) => $a->has('digitalProducts', 1)->where('pagination.total', 1));
        $this->get('/productos-digitales?q=ausente')->assertInertia(fn (Assert $a) => $a->has('digitalProducts', 0)->where('pagination.total', 0));
        $this->get('/explorar?type=benefits')->assertInertia(fn (Assert $a) => $a->has('digitalProducts', 0));
        $this->get('/productos-digitales?sort=invalid')->assertSessionHasErrors('sort');
    }

    public function test_only_admin_can_manage_internal_products_and_partner_isolation_is_enforced(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = Partner::factory()->create();
        $digital = DigitalProduct::create(['partner_id' => $other->id, 'name' => 'Otro', 'slug' => 'otro']);
        $this->actingAs(User::factory()->create())->get('/admin/productos-digitales')->assertForbidden();
        $this->actingAs($admin)->get('/admin/productos-digitales/otro/editar')->assertForbidden();
        $this->post('/admin/productos-digitales/otro', ['name' => 'Cambio'])->assertForbidden();
        $this->post('/admin/productos-digitales', ['name' => 'Borrador QA', 'slug' => 'borrador-qa', 'status' => 'draft', 'currency' => 'BOB'])->assertRedirect('/admin/productos-digitales');
        $this->assertDatabaseHas('digital_products', ['slug' => 'borrador-qa', 'status' => 'draft', 'partner_id' => Partner::where('slug', 'jakawi-digital')->value('id')]);
        $this->assertSame('Otro', $digital->fresh()->name);
    }
}
