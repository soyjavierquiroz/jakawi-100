<?php
namespace Tests\Feature;

use App\Models\{DigitalProduct, LandingPresentation, Partner, User};
use App\Services\{GluteoPerfectoDraft, GluteoPerfectoVisibility, DigitalCheckout};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalComingSoonTest extends TestCase
{
    use RefreshDatabase;

    private function visible(): DigitalProduct
    {
        config(['jakawi.prelaunch_mode' => true]);
        app(GluteoPerfectoDraft::class)->create(false);
        app(GluteoPerfectoVisibility::class)->publish(false);
        return DigitalProduct::where('slug','gluteo-perfecto')->sole();
    }

    public function test_public_discovery_and_both_pages_include_the_editorial_only_product(): void
    {
        $p=$this->visible();
        foreach (['/explorar?q=Glúteo%20Perfecto','/explorar?type=digital&q=Glúteo%20Perfecto','/productos-digitales?q=Glúteo%20Perfecto'] as $path) {
            $this->get($path)->assertOk()->assertInertia(fn (Assert $a) => $a->has('digitalProducts',1)->where('digitalProducts.0.id',$p->id)->where('digitalProducts.0.coming_soon',true)->where('digitalProducts.0.public_price',null));
        }
        $this->get('/productos-digitales/gluteo-perfecto')->assertOk()->assertInertia(fn (Assert $a) => $a->where('product.coming_soon',true)->where('canCollect',false)->where('prelaunch',true));
        $this->get('/l/gluteo-perfecto')->assertOk()->assertInertia(fn (Assert $a) => $a->where('preview',false)->where('product.coming_soon',true)->where('canBuy',false)->where('copy.eyebrow','Disponible próximamente')->has('blocks',11)->has('blocks.5.items',3)->has('blocks.8.items',5)->where('blocks.1.verified',false)->missing('blocks.1.source'));
        DigitalProduct::create(['partner_id'=>$p->partner_id,'name'=>'Otro borrador','slug'=>'otro-borrador']);
        $this->get('/explorar?type=digital')->assertInertia(fn (Assert $a) => $a->has('digitalProducts',1));
        $this->get('/productos-digitales/otro-borrador')->assertNotFound();
    }

    public function test_editorial_mode_blocks_checkout_even_if_prelaunch_is_later_disabled(): void
    {
        $p=$this->visible();$user=User::factory()->create();
        $this->actingAs($user)->post('/productos-digitales/gluteo-perfecto/comprar')->assertStatus(423);
        config(['jakawi.prelaunch_mode'=>false]);
        $this->post('/productos-digitales/gluteo-perfecto/comprar')->assertStatus(423);
        foreach(['digital_orders','digital_payments','digital_access_grants'] as $table) $this->assertDatabaseCount($table,0);
        $this->get('/l/gluteo-perfecto')->assertInertia(fn (Assert $a) => $a->where('canBuy',false));
    }

    public function test_editor_and_previews_remain_editable_without_price_or_pdf_and_publication_is_idempotent(): void
    {
        $p=$this->visible();$l=LandingPresentation::where('slug','gluteo-perfecto')->sole();
        $before=$l->editorial_blocks;app(GluteoPerfectoVisibility::class)->publish(false);
        $this->assertEquals($before,$l->fresh()->editorial_blocks);
        $this->assertSame(1,DigitalProduct::count());$this->assertSame(1,LandingPresentation::count());
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->post('/admin/productos-digitales/gluteo-perfecto',['name'=>$p->name,'slug'=>$p->slug,'status'=>'published','currency'=>'BOB','description'=>'Texto revisado por el propietario'])->assertRedirect();
        $this->assertNull($p->fresh()->public_price);$this->assertNull($p->fresh()->file_path);
        $this->put('/admin/productos-digitales/gluteo-perfecto/landings/gluteo-perfecto',['name'=>$l->name,'slug'=>$l->slug,'editorial_blocks'=>$before])->assertRedirect();
        $this->get('/admin/landings/gluteo-perfecto/edit')->assertOk();
        $this->get('/admin/productos-digitales/gluteo-perfecto/preview')->assertOk();
        $this->get('/admin/productos-digitales/gluteo-perfecto/landings/gluteo-perfecto/preview')->assertOk()->assertInertia(fn (Assert $a) => $a->where('preview',true)->where('canBuy',false)->has('blocks',11));
    }
}
