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
        $this->get('/productos-digitales?q=remoto')->assertOk()->assertInertia(fn (Assert $a) => $a->component('digital/index')->has('products.data', 1));
        $this->get('/explorar?q=remoto')->assertOk()->assertInertia(fn (Assert $a) => $a->has('digitalProducts', 1)->where('digitalProducts.0.name', 'Manual QA'));
        $this->get('/productos-digitales/manual-qa')->assertOk()->assertInertia(fn (Assert $a) => $a->component('digital/show')->missing('product.file_path'));
        $this->get('/productos-digitales/manual-qa')->assertOk();
        $digital->update(['status' => 'draft']);
        $this->get('/productos-digitales/manual-qa')->assertNotFound();
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
