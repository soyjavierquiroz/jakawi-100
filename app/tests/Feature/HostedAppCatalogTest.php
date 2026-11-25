<?php
namespace Tests\Feature;
use App\Models\{AppSetting,DigitalProduct,Partner};
use App\Services\{HostedAppCatalogImport,HostedApps};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HostedAppCatalogTest extends TestCase
{
    use RefreshDatabase;
    private function data(): array
    {
        return ['slug'=>'segunda-qa-es','vendor_id'=>'segunda_qa_es','name'=>'App de prueba ES','language'=>'es','package_sha256'=>str_repeat('a',64)];
    }
    public function test_import_creates_only_one_draft_without_prices_or_commercial_permissions(): void
    {
        $import=app(HostedAppCatalogImport::class);
        $first=$import->run($this->data());
        $again=$import->run($this->data());
        $this->assertSame($first['product_id'],$again['product_id']);
        $this->assertSame('unchanged',$again['result']);
        $p=DigitalProduct::findOrFail($first['product_id']);
        $this->assertSame('draft',$p->status);
        $this->assertSame('hosted_app',$p->delivery_type);
        $this->assertNull($p->public_price);
        $this->assertNull($p->member_price);
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
        $this->assertSame(1,DigitalProduct::where('delivery_type','hosted_app')->count());
    }
    public function test_spanish_and_gluteos_guards(): void
    {
        foreach ([['language'=>'pt'],['slug'=>'gluteos'],['vendor_id'=>'gluteo_perfeito_es']] as $change) {
            try { app(HostedAppCatalogImport::class)->run(array_replace($this->data(),$change)); $this->fail('Debió rechazar variante/reservado'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertDatabaseCount('digital_products',0);
    }
}
