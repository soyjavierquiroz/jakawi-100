<?php
namespace Tests\Feature;

use App\Models\{AppSetting, DigitalProduct, User};
use App\Services\{DigitalCatalog, DigitalCollection, DigitalProductQr, DigitalSaleAvailability, HostedAppCatalogImport};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalAdminCatalogTest extends TestCase
{
    use DatabaseMigrations;
    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () { \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false; });
    }
    private User $admin;
    private DigitalProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public'); Storage::fake('digital-private');
        config(['hosted_apps.secret'=>str_repeat('a',64)]);
        Http::fake(['jakawi.store/*/_health'=>Http::response(['protected'=>true,'functional'=>true]),'jakawi.store/*'=>Http::response('',403)]);
        $this->admin=User::factory()->create(['is_admin'=>true]);
        $id=app(HostedAppCatalogImport::class)->run(['slug'=>'mascotas-qa-es','vendor_id'=>'qa','name'=>'Mascotas QA','language'=>'es','package_sha256'=>str_repeat('a',64),'category'=>'mascotas-animales','short_description'=>'Descripción corta','description'=>'Descripción completa'])['product_id'];
        $this->product=DigitalProduct::findOrFail($id);
        $this->product->update(['cover_path'=>'qa.webp']);
        AppSetting::create(['key'=>DigitalCollection::KEY,'value'=>['enabled'=>true,'bank'=>'QA','account'=>'QA','holder'=>'QA','currency'=>'BOB','version'=>'v1','account_key'=>'qa-key']]);
    }
    private function qrs(): void
    {
        $this->actingAs($this->admin)->post('/admin/digital/cobro/qr-compartido',['reuse_confirmed'=>true,'public_qr'=>UploadedFile::fake()->image('public.png',49,10),'member_qr'=>UploadedFile::fake()->image('member.png',29,10)])->assertSessionHasNoErrors();
    }
    private function bulk(array $ids,array $changes): array
    {
        $preview=$this->actingAs($this->admin)->postJson('/admin/productos-digitales/lote',['ids'=>$ids,'changes'=>$changes,'phase'=>'preview'])->assertOk()->json();
        return $this->postJson('/admin/productos-digitales/lote',['ids'=>$ids,'changes'=>$changes,'phase'=>'apply','confirmation'=>$preview['confirmation']])->assertOk()->json();
    }
    public function test_server_search_filters_sort_pagination_and_bounded_queries(): void
    {
        $partner=app(DigitalCatalog::class)->internalPartner();
        for($i=0;$i<55;$i++) DigitalProduct::create(['partner_id'=>$partner->id,'name'=>sprintf('Producto %02d',$i),'slug'=>'producto-'.$i,'category'=>'cocina-recetas','delivery_type'=>'pdf','status'=>'draft','currency'=>'BOB','public_price'=>49,'member_price'=>29]);
        DB::flushQueryLog();DB::enableQueryLog();
        $this->actingAs($this->admin)->get('/admin/productos-digitales?q=Producto&state=draft&category=cocina-recetas&type=pdf&sort=name_desc&per_page=25&page=2')->assertOk()->assertInertia(fn(Assert $page)=>$page->has('products.data',25)->where('products.total',55)->where('products.data.0.name','Producto 29')->where('filters.q','Producto')->where('counts.total',56)->where('products.current_page',2)->where('products.next_page_url',fn($url)=>str_contains($url,'q=Producto')&&str_contains($url,'sort=name_desc')));
        $this->assertLessThan(35,count(DB::getQueryLog()));DB::disableQueryLog();
        $this->get('/admin/productos-digitales?q=Mascotas%20y%20animales')->assertOk()->assertInertia(fn(Assert $page)=>$page->where('products.total',1));
        $this->get('/admin/productos-digitales?qr=pending&state=missing')->assertOk()->assertInertia(fn(Assert $page)=>$page->where('products.total',56));
    }
    public function test_bulk_only_explicit_selected_fields_change_and_confirmation_is_bound_to_selection(): void
    {
        $other=$this->product->replicate();$other->slug='other';$other->save();$before=$other->fresh()->getAttributes();
        $result=$this->bulk([$this->product->id],['category'=>'familia-crianza','public_price'=>'59.00','member_price'=>'39.00']);
        $this->assertSame(1,$result['ready']);$p=$this->product->fresh();
        $this->assertSame('familia-crianza',$p->category);$this->assertSame('59.00',$p->public_price);$this->assertSame('39.00',$p->member_price);
        $this->assertSame('draft',$p->status);$this->assertSame($before,$other->fresh()->getAttributes());
        $this->postJson('/admin/productos-digitales/lote',['ids'=>[$p->id],'changes'=>['category'=>'fitness'],'phase'=>'apply'])->assertUnprocessable();
        $preview=$this->postJson('/admin/productos-digitales/lote',['ids'=>[$p->id],'changes'=>['category'=>'fitness'],'phase'=>'preview'])->assertOk()->json();
        $this->postJson('/admin/productos-digitales/lote',['ids'=>[$other->id],'changes'=>['category'=>'fitness'],'phase'=>'apply','confirmation'=>$preview['confirmation']])->assertUnprocessable();
        $this->bulk([$p->id],['member_price'=>'999.00']);$this->assertSame('39.00',$p->fresh()->member_price);
    }
    public function test_shared_qr_matches_prices_currency_account_and_fills_only_missing_defaults(): void
    {
        $this->qrs();
        $this->product->update(['support_information'=>'Soporte personalizado','access_terms'=>null]);
        $result=$this->bulk([$this->product->id],['qr_mode'=>'shared','fill_defaults'=>true]);
        $this->assertSame(1,$result['ready']);$p=$this->product->fresh();
        $this->assertSame('Soporte personalizado',$p->support_information);$this->assertSame(config('jakawi-digital.plr_defaults.access_terms'),$p->access_terms);
        $this->assertTrue(app(DigitalProductQr::class)->verified($p,'public'));
        $this->assertSame('draft',$p->status);
        $result=$this->bulk([$p->id],['public_price'=>'59.00','qr_mode'=>'shared']);
        $this->assertSame(1,$result['blocked']);$this->assertSame('49.00',$p->fresh()->public_price);
        $p->update(['currency'=>'USD']);$result=$this->bulk([$p->id],['qr_mode'=>'shared']);$this->assertSame(1,$result['blocked']);
    }
    public function test_publication_preview_and_apply_skip_blocked_revalidate_and_preserve_business(): void
    {
        $this->qrs();$blocked=$this->product->replicate();$blocked->slug='blocked';$blocked->category=null;$blocked->save();
        $untouched=$this->product->replicate();$untouched->slug='untouched';$untouched->save();
        $before=$untouched->fresh()->getAttributes();
        $ids=[$this->product->id,$blocked->id];$changes=['operation'=>'publish_sale'];
        $preview=$this->actingAs($this->admin)->postJson('/admin/productos-digitales/lote',compact('ids','changes')+['phase'=>'preview'])->assertOk()->json();
        $this->assertSame(1,$preview['ready']);$this->assertSame(1,$preview['blocked']);$this->assertStringContainsString('categoría',implode(' ',$preview['results'][1]['missing']));
        $this->assertSame('draft',$this->product->fresh()->status);
        $r=$this->postJson('/admin/productos-digitales/lote',compact('ids','changes')+['phase'=>'apply','confirmation'=>$preview['confirmation']])->assertOk()->json();
        $this->assertSame(1,$r['ready']);$this->assertSame(1,$r['blocked']);
        $this->assertTrue(app(DigitalSaleAvailability::class)->canSell($this->product->fresh()));$this->assertSame('draft',$blocked->fresh()->status);$this->assertSame($before,$untouched->fresh()->getAttributes());
        $this->assertFalse($this->product->fresh()->welcome_gift_enabled);$this->assertDatabaseCount('digital_orders',0);$this->assertDatabaseCount('digital_access_grants',0);
        $this->bulk([$this->product->id],['operation'=>'disable_sale']);$this->assertFalse(app(DigitalSaleAvailability::class)->canSell($this->product->fresh()));$this->assertSame('published',$this->product->fresh()->status);
        // A once-ready product changed after preview is revalidated, never blindly published.
        $ids=[$untouched->id];$preview=$this->postJson('/admin/productos-digitales/lote',compact('ids','changes')+['phase'=>'preview'])->assertOk()->json();
        $untouched->update(['category'=>null]);
        $this->postJson('/admin/productos-digitales/lote',compact('ids','changes')+['phase'=>'apply','confirmation'=>$preview['confirmation']])->assertOk()->assertJsonPath('blocked',1);
        $this->assertSame('draft',$untouched->fresh()->status);
    }
    public function test_permissions_and_internal_scope_remain_required(): void
    {
        $this->get('/admin/productos-digitales')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/productos-digitales')->assertForbidden();
        $this->postJson('/admin/productos-digitales/lote',['ids'=>[$this->product->id],'changes'=>['category'=>'fitness'],'phase'=>'preview'])->assertForbidden();
        $this->actingAs($this->admin)->postJson('/admin/productos-digitales/lote',['ids'=>[99999999],'changes'=>['category'=>'fitness'],'phase'=>'preview'])->assertForbidden();
    }
}
