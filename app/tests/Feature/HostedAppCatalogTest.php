<?php
namespace Tests\Feature;
use App\Models\{AppSetting,DigitalProduct,Partner};
use App\Services\{HostedAppCatalogImport,HostedApps};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HostedAppCatalogTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        DigitalProduct::create(['partner_id'=>Partner::where('slug','jakawi-digital')->value('id'),'slug'=>'gluteo-perfecto','name'=>'Glúteos QA','delivery_type'=>'hosted_app','app_key'=>'gluteos','status'=>'draft']);
    }
    private function data(): array
    {
        return ['slug'=>'segunda-qa-es','vendor_id'=>'segunda_qa_es','name'=>'App de prueba ES','language'=>'es','package_sha256'=>str_repeat('a',64)];
    }
    public function test_import_creates_only_one_draft_with_defaults_without_commercial_permissions(): void
    {
        $import=app(HostedAppCatalogImport::class);
        $first=$import->run($this->data());
        $again=$import->run($this->data());
        $this->assertSame($first['product_id'],$again['product_id']);
        $this->assertSame('unchanged',$again['result']);
        $p=DigitalProduct::findOrFail($first['product_id']);
        $this->assertSame('draft',$p->status);
        $this->assertSame('hosted_app',$p->delivery_type);
        $this->assertSame('49.00',$p->public_price);
        $this->assertSame('29.00',$p->member_price);
        $this->assertFalse($p->welcome_gift_enabled);
        $this->assertSame('https://jakawi.store/segunda-qa-es/_entry',app(HostedApps::class)->registry()['segunda-qa-es']['entry']);
        $this->assertArrayHasKey('gluteos',app(HostedApps::class)->registry());
        $this->assertDatabaseMissing('app_settings',['key'=>'digital_sale:'.$p->id]);
    }
    public function test_changed_package_and_duplicate_vendor_are_rejected(): void
    {
        $import=app(HostedAppCatalogImport::class);
        $import->run($this->data());
        foreach ([$this->data()+[],array_replace($this->data(),['slug'=>'otra-qa-es'])] as $i=>$data) {
            if ($i===0) $data['package_sha256']=str_repeat('b',64);
            try { $import->run($data); $this->fail('Debió rechazar duplicado/reemplazo'); }
            catch (\RuntimeException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertSame(2,DigitalProduct::where('delivery_type','hosted_app')->count());
    }
    public function test_spanish_and_gluteos_guards(): void
    {
        foreach ([['language'=>'pt'],['slug'=>'gluteos'],['vendor_id'=>'gluteo_perfeito_es']] as $change) {
            try { app(HostedAppCatalogImport::class)->run(array_replace($this->data(),$change)); $this->fail('Debió rechazar variante/reservado'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertDatabaseCount('digital_products',1);
    }
    public function test_fill_missing_preserves_owner_edits_and_gluteos_and_never_enables_sales(): void
    {
        $before=DigitalProduct::where('app_key','gluteos')->sole()->getAttributes();
        $data=$this->data()+['description'=>'Descripción real','short_description'=>'Resumen real','category'=>'fitness'];
        $id=app(HostedAppCatalogImport::class)->run($data)['product_id'];
        $p=DigitalProduct::find($id);
        $p->update(['public_price'=>'59.00','short_description'=>'Editado por propietario','category'=>null,'member_price'=>null]);
        app(HostedAppCatalogImport::class)->run($data+['fill_missing'=>true]);
        $p->refresh();
        $this->assertSame('59.00',$p->public_price);
        $this->assertSame('29.00',$p->member_price);
        $this->assertSame('Editado por propietario',$p->short_description);
        $this->assertSame('fitness',$p->category);
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($p));
        $this->assertSame($before,DigitalProduct::where('app_key','gluteos')->sole()->getAttributes());
        $p->update(['member_price'=>'35.00']);
        app(HostedAppCatalogImport::class)->run($data);
        $this->assertSame('35.00',$p->fresh()->member_price);
    }
    public function test_pilates_draft_saves_blank_optional_multipart_sale_field_and_stays_unavailable(): void
    {
        $data=array_replace($this->data(),['slug'=>'pilates-app-es','vendor_id'=>'pilates_app_es','name'=>'Pilates en casa']);
        $id=app(HostedAppCatalogImport::class)->run($data)['product_id'];
        $admin=\App\Models\User::factory()->create(['is_admin'=>true]);
        $url='/admin/productos-digitales/pilates-app-es';
        $payload=['name'=>'Pilates en casa','slug'=>'pilates-app-es','delivery_type'=>'hosted_app','app_key'=>'pilates-app-es','status'=>'draft','currency'=>'BOB','public_price'=>'49.00','member_price'=>'29.00','sale_enabled'=>'','pdf_final'=>'0','public_qr_confirmed'=>'0','member_qr_confirmed'=>'0','operation'=>'save'];
        $this->actingAs($admin)->post($url,$payload)->assertSessionHasNoErrors()->assertRedirect('/admin/productos-digitales');
        $p=DigitalProduct::find($id);
        $this->assertSame('49.00',$p->public_price);
        $this->assertSame('draft',$p->status);
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($p));
        $payload['operation']='authorize_sale';$payload['sale_enabled']='1';$payload['pdf_final']='1';
        $this->post($url,$payload)->assertSessionHasErrors('sale_enabled');
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($p->fresh()));
    }
    public function test_identical_fixed_qr_cannot_be_confirmed_at_49_and_29(): void
    {
        $id=app(HostedAppCatalogImport::class)->run($this->data())['product_id'];
        $p=DigitalProduct::find($id);
        \Illuminate\Support\Facades\Storage::fake('digital-private');
        AppSetting::updateOrCreate(['key'=>\App\Services\DigitalCollection::KEY],['value'=>['enabled'=>true,'exclusive_account'=>true,'bank'=>'QA','account'=>'QA','holder'=>'QA','currency'=>'BOB','version'=>'qa','account_key'=>hash('sha256','QA')]]);
        $file=\Illuminate\Http\UploadedFile::fake()->image('fixed49.png');
        $admin=\App\Models\User::factory()->create(['is_admin'=>true]);
        $service=app(\App\Services\DigitalProductQr::class);
        $service->save($p,'public',$file,true,$admin->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->save($p,'member',$file,true,$admin->id);
    }

}
