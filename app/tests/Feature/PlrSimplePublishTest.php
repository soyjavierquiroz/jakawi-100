<?php
namespace Tests\Feature;

use App\Models\{AppSetting, DigitalProduct, DigitalOrder, User};
use App\Services\{DigitalAssetCleanup, DigitalCollection, DigitalProductQr, DigitalSaleAvailability, HostedAppCatalogImport, HostedApps};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Tests\TestCase;

class PlrSimplePublishTest extends TestCase
{
    use DatabaseMigrations;

    // The existing delivery migration is forward-only. Refresh this isolated DB
    // before each case without calling its intentionally forbidden down().
    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () { \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false; });
    }
    private User $admin;
    private DigitalProduct $p;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('digital-private'); Storage::fake('public');
        config(['hosted_apps.secret'=>str_repeat('a',64)]);
        $this->admin=User::factory()->create(['is_admin'=>true]);
        $id=app(HostedAppCatalogImport::class)->run(['slug'=>'mascotas-qa-es','vendor_id'=>'mascotas_qa_es','package_sha256'=>str_repeat('a',64),'name'=>'Adiestramiento de perros','language'=>'es','short_description'=>'Descripción original corta','description'=>'Descripción original del paquete','category'=>'mascotas-animales'])['product_id'];
        $this->p=DigitalProduct::findOrFail($id);
        $this->p->update(['cover_path'=>'digital-products/'.$id.'/cover/00000000-0000-4000-8000-000000000001.webp']);
        Storage::disk('public')->put($this->p->cover_path,'QA cover');
        AppSetting::create(['key'=>DigitalCollection::KEY,'value'=>['enabled'=>true,'bank'=>'QA BANK','account'=>'QA','holder'=>'QA','currency'=>'BOB','version'=>'qa1','account_key'=>hash('sha256','qa')]]);
        $this->health();
    }
    private function health(bool $protected=true): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['jakawi.store/*/_health'=>Http::response(['protected'=>true,'functional'=>true]), 'jakawi.store/*'=>Http::response('', $protected ? 403 : 200)]);
    }
    private function shared(string $amount): void
    {
        $service=app(DigitalProductQr::class);
        $data=['reuse_confirmed'=>'1'];
        $saved=collect($service->sharedAdminState())->keyBy('tier');
        foreach (['public'=>'49', 'member'=>'29'] as $tier=>$value) {
            if ($value === $amount || ! ($saved[$tier]['current'] ?? false)) $data[$tier.'_qr']=UploadedFile::fake()->image('QA-'.$value.'.png',(int)$value,10);
        }
        $this->actingAs($this->admin)->post('/admin/digital/cobro/qr-compartido',$data)->assertSessionHasNoErrors();
    }
    private function payload(): array
    {
        return ['name'=>$this->p->name,'slug'=>$this->p->slug,'description'=>$this->p->description,'short_description'=>$this->p->short_description,
            'support_information'=>$this->p->support_information,'access_terms'=>$this->p->access_terms,'category'=>$this->p->category,
            'status'=>'draft','delivery_type'=>'hosted_app','app_key'=>$this->p->app_key,'public_price'=>'49.00','member_price'=>'29.00','currency'=>'BOB',
            'operation'=>'publish_sale','use_shared_qr'=>'1'];
    }
    public function test_adopted_gluteos_keeps_public_generic_js_but_requires_guarded_screen_and_imports_require_guarded_assets(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['jakawi.store/*/_health'=>Http::response(['protected'=>true,'functional'=>true]), 'jakawi.store/*/app.js'=>Http::response('generic JS',200), 'jakawi.store/*'=>Http::response('',403)]);
        $importedKey=$this->p->app_key;
        $this->p->app_key='gluteos';
        $this->assertTrue(app(HostedApps::class)->ready($this->p,true));
        $this->p->app_key=$importedKey;
        $this->assertFalse(app(HostedApps::class)->ready($this->p,true));
    }

    public function test_new_categories_are_available_in_admin_and_owner_selection_is_preserved(): void
    {
        $labels=config('jakawi.category_labels');
        foreach (['mascotas-animales','familia-crianza','salud-autocuidado','relaciones-pareja','finanzas-personales','comunicacion-habilidades','manualidades-creatividad','fiestas-celebraciones'] as $key) $this->assertArrayHasKey($key,$labels);
        $this->actingAs($this->admin)->get('/admin/productos-digitales/'.$this->p->slug.'/editar')->assertOk()->assertInertia(fn ($page) => $page->where('categoryLabels.mascotas-animales','Mascotas y animales'));
        $this->post('/admin/productos-digitales/'.$this->p->slug,array_replace($this->payload(),['operation'=>'save','category'=>'familia-crianza']))->assertSessionHasNoErrors();
        $this->assertSame('familia-crianza',$this->p->fresh()->category);
    }

    public function test_two_qrs_need_only_owner_confirmation_and_keep_exact_amount_currency_account_validity(): void
    {
        $url='/admin/digital/cobro/qr-compartido';
        $this->actingAs($this->admin)->post($url,[])->assertSessionHasErrors(['reuse_confirmed']);
        $this->post($url,['reuse_confirmed'=>'1'])->assertSessionHasErrors(['public_qr','member_qr']);
        $this->shared('49');$this->shared('29');
        $qr=app(DigitalProductQr::class);
        $this->assertTrue($qr->verified($this->p,'public')); $this->assertTrue($qr->verified($this->p,'member'));
        $public=$qr->resolved($this->p,'public');$member=$qr->resolved($this->p,'member');
        $this->assertNotSame($public['qr_path'],$member['qr_path']);
        $other=$this->p->replicate();$other->slug='otra-app';$other->save();
        $this->assertSame($public['qr_path'],$qr->resolved($other,'public')['qr_path']);
        $this->assertCount(2,Storage::disk('digital-private')->allFiles('qr'));
        $this->assertCount(0,Storage::disk('digital-private')->allFiles('statements'));
        foreach ($qr->sharedAdminState() as $state) {
            $this->assertTrue($state['current']);
            $this->actingAs($this->admin)->get($state['preview_url'])->assertOk()->assertHeader('Cache-Control','no-store, private');
        }
        $this->assertNull($public['valid_until']);
        $this->assertSame('owner',$public['confirmation_mode']);
        $this->p->public_price='59.00';$this->assertFalse($qr->verified($this->p,'public'));
        $this->p->public_price='49.00';$this->p->currency='USD';$this->assertFalse($qr->verified($this->p,'public'));$this->p->currency='BOB';
        $s=AppSetting::where('key',DigitalCollection::KEY)->sole();$old=$s->value;$s->update(['value'=>array_replace($old,['version'=>'changed'])]);
        $this->assertFalse($qr->verified($this->p,'public'));$s->update(['value'=>$old]);
        $shared=AppSetting::where('key',$public['shared_key'])->sole();$shared->update(['value'=>array_replace($shared->value,['valid_until'=>now()->subMinute()->toIso8601String()])]);
        $this->assertFalse($qr->verified($this->p,'public'));
    }
    public function test_publish_saves_edits_and_reports_missing_then_activates_in_one_action_without_enabling_gifts(): void
    {
        $url='/admin/productos-digitales/'.$this->p->slug;
        $this->actingAs($this->admin)->post($url,array_replace($this->payload(),['name'=>'Nombre del propietario']))->assertSessionHasErrors('sale_enabled');
        $this->assertSame('Nombre del propietario',$this->p->fresh()->name);
        $this->assertSame('draft',$this->p->fresh()->status);
        $this->assertFalse(app(DigitalSaleAvailability::class)->canSell($this->p->fresh()));
        $this->shared('49');$this->shared('29');
        $this->post($url,$this->payload())->assertSessionHasNoErrors()->assertRedirect('/admin/productos-digitales');
        $this->p->refresh();$this->assertSame('published',$this->p->status);
        $this->assertTrue(app(DigitalSaleAvailability::class)->canSell($this->p));
        $this->assertFalse($this->p->welcome_gift_enabled);$this->assertFalse($this->p->promotional_gift_enabled);
        $this->assertDatabaseCount('digital_orders',0);$this->assertDatabaseCount('digital_access_grants',0);
        $this->health(false);$this->assertFalse(app(HostedApps::class)->ready($this->p,true));
    }
    public function test_switch_to_shared_removes_specific_associations_but_preserves_pending_order_qr_and_replaces_editorial_images(): void
    {
        $qr=app(DigitalProductQr::class);
        foreach (['public','member'] as $tier) DB::transaction(fn()=> $qr->save($this->p,$tier,UploadedFile::fake()->image($tier.'.png',$tier==='public'?15:16,10),true,$this->admin->id));
        $specific=$qr->settings($this->p);$public=$specific['public']['qr_path'];$member=$specific['member']['qr_path'];
        $this->assertFalse($qr->usesShared($this->p));$this->assertTrue($qr->verified($this->p,'public'));
        $buyer=User::factory()->create();
        DigitalOrder::create(['reference'=>(string)\Illuminate\Support\Str::uuid(),'partner_id'=>$this->p->partner_id,'public_price'=>'49.00','user_id'=>$buyer->id,'digital_product_id'=>$this->p->id,'product_name'=>'QA','product_slug'=>$this->p->slug,
            'file_path'=>'','file_sha256'=>'','member_at_purchase'=>false,'amount'=>'49.00','currency'=>'BOB','status'=>'pending',
            'collection_snapshot'=>$qr->snapshot($this->p,false),'expires_at'=>now()->addDay()]);
        // Replacement commits before cleanup; a pending order keeps its original QR.
        DB::transaction(fn()=> $qr->save($this->p,'public',UploadedFile::fake()->image('public-new.png',18,10),true,$this->admin->id));
        DB::transaction(fn()=> $qr->save($this->p,'member',UploadedFile::fake()->image('member-new.png',19,10),true,$this->admin->id));
        Storage::disk('digital-private')->assertExists($public);Storage::disk('digital-private')->assertMissing($member);
        $replacement=$qr->settings($this->p);
        $this->shared('49');$this->shared('29');
        $this->assertFalse($qr->usesShared($this->p));
        $this->assertSame($replacement['public']['qr_path'],$qr->resolved($this->p,'public')['qr_path']);
        DB::transaction(fn()=> $qr->setShared($this->p,true));
        foreach (['public','member'] as $tier) Storage::disk('digital-private')->assertMissing($replacement[$tier]['qr_path']);
        $this->assertArrayNotHasKey('public',$qr->settings($this->p));
        Storage::disk('digital-private')->assertExists($public);Storage::disk('digital-private')->assertMissing($member);
        $this->assertTrue($qr->orderReady(DigitalOrder::sole()));
        $cleanup=app(DigitalAssetCleanup::class);
        $this->assertSame(1,$cleanup->clean('digital-private',[$public])['referenced']);
        $gallery='digital-products/'.$this->p->id.'/image/00000000-0000-4000-8000-000000000002.webp';
        Storage::disk('public')->put($gallery,'gallery QA');$this->p->update(['gallery_paths'=>[$gallery]]);
        $old=$this->p->cover_path;
        $this->actingAs($this->admin)->post('/admin/productos-digitales/'.$this->p->slug,array_replace($this->payload(),['operation'=>'save','gallery_clear'=>'1','cover'=>UploadedFile::fake()->image('new.png')]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);Storage::disk('public')->assertExists($this->p->fresh()->cover_path);
        Storage::disk('public')->assertMissing($gallery);
        $this->assertSame(1,$cleanup->clean('public',[$old])['absent']);
        Storage::disk('digital-private')->put('products/archived.pdf','keep');
        $this->assertSame(1,$cleanup->clean('digital-private',['products/archived.pdf'])['retained_uncertain']);
        Storage::disk('digital-private')->assertExists('products/archived.pdf');
    }
    public function test_checkout_selects_each_shared_qr_and_replacement_keeps_order_history_and_cleans_only_unused_images(): void
    {
        config(['jakawi.prelaunch_mode'=>false]);
        $this->shared('49');
        $qr=app(DigitalProductQr::class);
        $this->p->update(['status'=>'published']);
        app(DigitalSaleAvailability::class)->configure($this->p,true,true,$this->admin->id);
        $checkout=app(\App\Services\DigitalCheckout::class);
        $buyer=User::factory()->create();
        $member=User::factory()->create();
        \App\Models\Membership::create(['user_id'=>$member->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'amount_paid'=>'100.00']);
        $public=$checkout->start($buyer,$this->p);$private=$checkout->start($member,$this->p);
        foreach ([[$public,'public','49.00'],[$private,'member','29.00']] as [$order,$tier,$amount]) {
            $this->assertSame($amount,$order->amount);
            $this->assertSame($tier,$order->collection_snapshot['qr_tier']);
            $this->assertSame($qr->resolved($this->p,$tier)['qr_path'],$order->collection_snapshot['qr_path']);
            $this->assertSame('pending',$order->status);
        }
        $before=DigitalOrder::orderBy('id')->get()->toJson();
        $this->actingAs($this->admin)->post('/admin/digital/cobro/qr-compartido',[
            'public_qr'=>UploadedFile::fake()->image('public-new.png',50,10),
            'member_qr'=>UploadedFile::fake()->image('member-new.png',30,10),'reuse_confirmed'=>'1',
        ])->assertSessionHasNoErrors();
        foreach ([$public,$private] as $order) Storage::disk('digital-private')->assertExists($order->collection_snapshot['qr_path']);
        $this->assertSame($before,DigitalOrder::orderBy('id')->get()->toJson());
        $unused=$qr->resolved($this->p,'member')['qr_path'];
        $this->post('/admin/digital/cobro/qr-compartido',[
            'member_qr'=>UploadedFile::fake()->image('member-next.png',31,10),'reuse_confirmed'=>'1',
        ])->assertSessionHasNoErrors();
        Storage::disk('digital-private')->assertMissing($unused);
        $this->assertDatabaseCount('digital_access_grants',0);
        // A mismatched image cannot be confirmed as both fixed amounts; no partial save.
        $state=AppSetting::where('key','like',DigitalProductQr::SHARED_PREFIX.'%')->get()->toJson();
        $this->post('/admin/digital/cobro/qr-compartido',[
            'public_qr'=>UploadedFile::fake()->image('same.png',40,10),
            'member_qr'=>UploadedFile::fake()->image('same.png',40,10),'reuse_confirmed'=>'1',
        ])->assertSessionHasErrors('member_qr');
        $this->assertSame($state,AppSetting::where('key','like',DigitalProductQr::SHARED_PREFIX.'%')->get()->toJson());
    }

}
