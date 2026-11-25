<?php
namespace Tests\Feature;

use App\Models\{AppSetting, DigitalProduct, User};
use App\Services\{DigitalCollection, DigitalGifts, DigitalProductQr, GluteoPerfectoDraft, GluteoPerfectoVisibility};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigitalAdminProductSaveTest extends TestCase
{
    use RefreshDatabase;
    private DigitalProduct $product;
    private User $admin;
    private string $url='/admin/productos-digitales/gluteo-perfecto';

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode'=>true,'digital_gifts.enabled'=>false,'digital_gifts.welcome_prelaunch_enabled'=>true,'media.disk'=>'minio-media']);
        Storage::fake('digital-private'); Storage::fake('minio-media');
        app(GluteoPerfectoDraft::class)->create(false);app(GluteoPerfectoVisibility::class)->publish(false);
        $this->product=DigitalProduct::where('slug','gluteo-perfecto')->sole();
        Storage::disk('digital-private')->put('products/original.pdf',"%PDF-1.4\n% QA original\n%%EOF");
        $this->product->update(['public_price'=>'49.00','member_price'=>'29.00','file_path'=>'products/original.pdf','file_sha256'=>hash('sha256',Storage::disk('digital-private')->get('products/original.pdf')),'welcome_gift_enabled'=>true,'gift_pdf_sha256'=>null]);
        AppSetting::updateOrCreate(['key'=>DigitalGifts::COURTESY_KEY],['value'=>['enabled'=>true,'product_id'=>$this->product->id,'pdf_sha256'=>$this->product->file_sha256]]);
        $this->admin=User::factory()->create(['is_admin'=>true]);$this->actingAs($this->admin);
    }
    private function data(array $overrides=[]): array
    {
        return array_merge($this->product->only(['name','slug','description','status','public_price','member_price','currency','support_information','access_terms']),['welcome_gift_enabled'=>true,'promotional_gift_enabled'=>false,'gift_pdf_final'=>false,'public_qr_confirmed'=>false,'member_qr_confirmed'=>false,'sale_enabled'=>false,'pdf_final'=>false,'operation'=>'save'],$overrides);
    }
    private function account(): void
    {
        AppSetting::updateOrCreate(['key'=>DigitalCollection::KEY],['value'=>['enabled'=>true,'exclusive_account'=>true,'bank'=>'BANCO FICTICIO QA','account'=>'QA-000','holder'=>'QA NO REAL','currency'=>'BOB','version'=>'qa-admin-v1','account_key'=>hash('sha256','qa-admin')]]);
    }
    public function test_editorial_save_and_price_update_preserve_courtesy_without_commercial_authorization(): void
    {
        $courtesy=AppSetting::where('key',DigitalGifts::COURTESY_KEY)->sole()->value;
        $this->from($this->url.'/editar')->post($this->url,$this->data(['short_description'=>'Editorial QA','public_price'=>'50.00','member_price'=>'30.00']))->assertRedirect('/admin/productos-digitales')->assertSessionHasNoErrors();
        $p=$this->product->fresh();$this->assertSame('Editorial QA',$p->short_description);$this->assertSame('50.00',$p->public_price);$this->assertTrue($p->welcome_gift_enabled);$this->assertNull($p->gift_pdf_sha256);
        $this->assertSame($courtesy,AppSetting::where('key',DigitalGifts::COURTESY_KEY)->sole()->value);
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($p));
        $this->assertDatabaseCount('digital_orders',0);$this->assertDatabaseCount('digital_access_grants',0);
    }
    public function test_two_qrs_can_be_stored_without_confirming_or_enabling_sales_and_retry_does_not_duplicate(): void
    {
        $this->post($this->url,$this->data(['public_qr'=>UploadedFile::fake()->image('public.png'),'member_qr'=>UploadedFile::fake()->image('member.png')]))->assertSessionHasNoErrors()->assertRedirect('/admin/productos-digitales');
        $qrs=app(DigitalProductQr::class)->settings($this->product);
        foreach(['public','member'] as $tier){Storage::disk('digital-private')->assertExists($qrs[$tier]['qr_path']);$this->assertNull($qrs[$tier]['verified_by']);}
        $before=Storage::disk('digital-private')->allFiles();
        $this->post($this->url,$this->data(['description'=>'Reintento editorial']))->assertSessionHasNoErrors();
        $this->assertSame($before,Storage::disk('digital-private')->allFiles());
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($this->product->fresh()));
    }
    public function test_confirmation_without_authorized_account_returns_field_error_before_any_upload(): void
    {
        $before=$this->product->fresh()->getAttributes();$files=Storage::disk('digital-private')->allFiles();
        $this->from($this->url.'/editar')->post($this->url,$this->data(['name'=>'Sin guardar','cover'=>UploadedFile::fake()->image('cover.png'),'public_qr'=>UploadedFile::fake()->image('public.png'),'member_qr'=>UploadedFile::fake()->image('member.png'),'public_qr_confirmed'=>true,'member_qr_confirmed'=>true]))->assertRedirect($this->url.'/editar')->assertSessionHasErrors(['public_qr_confirmed']);
        $this->assertSame($before,$this->product->fresh()->getAttributes());$this->assertSame($files,Storage::disk('digital-private')->allFiles());$this->assertSame([],Storage::disk('minio-media')->allFiles());
        $this->assertStringContainsString('cuenta exclusiva',session('errors')->first('public_qr_confirmed'));
    }
    public function test_confirmation_keeps_amount_and_currency_checks_and_does_not_enable_sales(): void
    {
        $this->account();
        $this->post($this->url,$this->data(['public_qr'=>UploadedFile::fake()->image('public.png'),'member_qr'=>UploadedFile::fake()->image('member.png'),'public_qr_confirmed'=>true,'member_qr_confirmed'=>true]))->assertSessionHasNoErrors();
        $qrs=app(DigitalProductQr::class)->settings($this->product);
        $this->assertSame('49.00',$qrs['public']['amount']);$this->assertSame('29.00',$qrs['member']['amount']);
        $this->assertTrue(app(DigitalProductQr::class)->verified($this->product,'public'));$this->assertTrue(app(DigitalProductQr::class)->verified($this->product,'member'));
        $this->from($this->url.'/editar')->post($this->url,$this->data(['currency'=>'USD','public_qr_confirmed'=>true]))->assertRedirect($this->url.'/editar')->assertSessionHasErrors('public_qr_confirmed');
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($this->product->fresh()));
    }
    public function test_invalid_fields_and_incomplete_sale_return_to_editor_without_changing_product(): void
    {
        $this->from($this->url.'/editar')->post($this->url,$this->data(['member_price'=>'60.00']))->assertRedirect($this->url.'/editar')->assertSessionHasErrors('member_price');
        $this->from($this->url.'/editar')->post($this->url,$this->data(['sale_enabled'=>true,'pdf_final'=>true,'operation'=>'authorize_sale']))->assertRedirect($this->url.'/editar')->assertSessionHasErrors('sale_enabled');
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($this->product->fresh()));$this->assertSame('29.00',$this->product->fresh()->member_price);
    }
    public function test_uploaded_files_have_valid_private_minio_references(): void
    {
        if(getenv('MINIO_INTEGRATION')!=='1')$this->markTestSkipped('Explicit isolated MinIO integration required.');
        $cfg=require config_path('filesystems.php');$prefix='qa-admin-save-'.Str::uuid();
        foreach(['digital-private','minio-media'] as $disk){config(['filesystems.disks.'.$disk=>$cfg['disks'][$disk]]);config(['filesystems.disks.'.$disk.'.root'=>$prefix.'/'.$disk]);Storage::forgetDisk($disk);}
        $private=Storage::disk('digital-private');$media=Storage::disk('minio-media');
        try {
            $this->post($this->url,$this->data(['pdf'=>UploadedFile::fake()->createWithContent('qa.pdf',"%PDF-1.4\n% QA ONLY\n%%EOF"),'cover'=>UploadedFile::fake()->image('cover.png'),'gallery'=>[UploadedFile::fake()->image('gallery.png')],'public_qr'=>UploadedFile::fake()->image('public.png'),'member_qr'=>UploadedFile::fake()->image('member.png')]))->assertSessionHasNoErrors()->assertRedirect('/admin/productos-digitales');
            $p=$this->product->fresh();$this->assertTrue($media->exists($p->cover_path));$this->assertTrue($media->exists($p->gallery_paths[0]));$this->assertTrue($private->exists($p->file_path));$this->assertSame($p->file_sha256,hash('sha256',$private->get($p->file_path)));
            foreach(app(DigitalProductQr::class)->settings($p) as $qr){$this->assertTrue($private->exists($qr['qr_path']));$this->assertSame($qr['qr_sha256'],hash('sha256',$private->get($qr['qr_path'])));$this->assertNull($qr['verified_by']);}
            $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($p));
        } finally {foreach([$private,$media] as $disk)foreach($disk->allFiles() as $key)$disk->delete($key);}
    }
}
