<?php
namespace Tests\Feature;

use App\Models\{DigitalProduct, Partner, User};
use App\Services\HostedAppCatalogImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlrCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function imported(): array
    {
        DigitalProduct::create(['partner_id'=>Partner::where('slug','jakawi-digital')->value('id'),'slug'=>'gluteo-perfecto','name'=>'Glúteos Perfectos','delivery_type'=>'hosted_app','app_key'=>'gluteos','category'=>'fitness','status'=>'published']);
        return ['slug'=>'pastel-gourmet-es','vendor_id'=>'bolo_gourmet_es','name'=>'Pastel Gourmet','language'=>'es','package_sha256'=>str_repeat('a',64),'category'=>'cocina-recetas'];
    }

    public function test_admin_lists_and_saves_editorial_categories_without_changing_commercial_data(): void
    {
        $data=$this->imported();
        $id=app(HostedAppCatalogImport::class)->run($data)['product_id'];
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->get('/admin/productos-digitales/pastel-gourmet-es/editar')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('categoryLabels.cocina-recetas','Cocina y recetas')->where('categoryLabels.astrologia','Astrología')->where('categoryLabels.fitness','Fitness'));
        $p=DigitalProduct::findOrFail($id);$before=$p->getAttributes();
        $this->post('/admin/productos-digitales/pastel-gourmet-es',[
            'name'=>$p->name,'slug'=>$p->slug,'delivery_type'=>$p->delivery_type,'app_key'=>$p->app_key,'status'=>'draft','currency'=>'BOB','public_price'=>'49.00','member_price'=>'29.00',
            'support_information'=>$p->support_information,'access_terms'=>$p->access_terms,'category'=>'negocios-marketing','operation'=>'save',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/productos-digitales');
        $p->refresh();$this->assertSame('negocios-marketing',$p->category);
        foreach (['status','currency','public_price','member_price','welcome_gift_enabled','promotional_gift_enabled','support_information','access_terms'] as $field) $this->assertEquals($before[$field],$p->getAttributes()[$field]);
        app(HostedAppCatalogImport::class)->run($data+['fill_missing'=>true]);
        $this->assertSame('negocios-marketing',$p->fresh()->category);
        $p->update(['category'=>null]);
        app(HostedAppCatalogImport::class)->run($data+['fill_missing'=>true]);
        $this->assertSame('cocina-recetas',$p->fresh()->category);
        $this->assertSame('fitness',DigitalProduct::findOrFail(1)->category);
    }

    public function test_digital_categories_filter_unified_explore_and_are_not_physical_filters(): void
    {
        $data=$this->imported();$id=app(HostedAppCatalogImport::class)->run($data)['product_id'];
        DigitalProduct::findOrFail($id)->update(['status'=>'published']);
        foreach (['/explorar?category=cocina-recetas','/explorar?type=digital&category=cocina-recetas'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn(Assert $p)=>$p->component('explore')->where('category','cocina-recetas')->has('results',1)->where('results.0.product.id',$id)->where('categoryLabels.cocina-recetas','Cocina y recetas'));
        }
        $this->actingAs(User::factory()->create())->get('/explorar?type=digital&category=cocina-recetas')->assertOk()->assertInertia(fn(Assert $p)=>$p->has('results',1));
        $this->get('/explorar?type=places&category=cocina-recetas')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('category',null)->where('categories',config('jakawi.categories')));
        $this->assertNotContains('cocina-recetas',config('jakawi.categories'));
    }
}
