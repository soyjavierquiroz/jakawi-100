<?php
namespace Tests\Feature;

use App\Models\DigitalProduct;
use App\Models\Membership;
use App\Models\User;
use App\Services\DigitalCheckout;
use App\Services\DigitalCollection;
use App\Services\DigitalFiles;
use App\Services\DigitalProductQr;
use App\Services\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Opt-in integration: isolated QA database, real existing MinIO, only own UUID objects. */
class MinioDigitalStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_upload_minio_checkout_and_purchased_pdf_versions(): void
    {
        if (getenv('MINIO_INTEGRATION') !== '1') $this->markTestSkipped('Explicit real MinIO QA environment required.');
        config(['jakawi.prelaunch_mode'=>false, 'filesystems.disks.digital-private.root'=>'digital-private/qa-'.Str::uuid()]);
        Storage::forgetDisk('digital-private');
        $disk = Storage::disk('digital-private'); $media = Storage::disk(config('media.disk')); $publicKeys=[];
        try {
            $admin=User::factory()->create(['is_admin'=>true]); $buyer=User::factory()->create();
            $this->actingAs($admin)->post('/admin/digital/cobro', ['bank'=>'QA ONLY','account'=>'QA-NOT-REAL','holder'=>'QA','currency'=>'BOB','enabled'=>true,'exclusive_account'=>true])->assertRedirect();
            $pdf="%PDF-1.4\n% QA ONLY - NOT FOR SALE\n%%EOF";
            $this->post('/admin/productos-digitales', ['name'=>'MinIO QA','slug'=>'minio-qa','status'=>'published','currency'=>'BOB','public_price'=>'20.00','member_price'=>'12.50','description'=>'QA only','support_information'=>'QA','access_terms'=>'QA','pdf'=>UploadedFile::fake()->createWithContent('QA-ONLY.pdf',$pdf),'cover'=>UploadedFile::fake()->image('QA-ONLY.jpg'),'public_qr'=>UploadedFile::fake()->image('QA-PUBLIC-NOT-BANK.png',10,10),'member_qr'=>UploadedFile::fake()->image('QA-MEMBER-NOT-BANK.png',12,10),'public_qr_confirmed'=>true,'member_qr_confirmed'=>true])->assertRedirect('/admin/productos-digitales');
            $product=DigitalProduct::where('slug','minio-qa')->sole(); $publicKeys[]=$product->cover_path;
            $this->assertSame('s3',config('filesystems.disks.digital-private.driver'));
            $this->assertSame($pdf,$disk->get($product->file_path));
            $this->assertSame(strlen($pdf),$disk->size($product->file_path));
            $this->get('/admin/productos-digitales/minio-qa/pdf')->assertOk()->assertStreamedContent($pdf);
            $this->assertTrue($media->exists($product->cover_path));
            $this->assertSame(200,Http::timeout(15)->get(app(MediaUrl::class)->url($product->cover_path,'thumbnail'))->status());
            $this->assertSame(200,Http::timeout(15)->get(app(MediaUrl::class)->url(config('media.social_fallback'),'thumbnail'))->status());
            $qrs=app(DigitalProductQr::class)->settings($product);
            foreach(['public','member'] as $tier) {
                $this->assertTrue($disk->exists($qrs[$tier]['qr_path']));
                $this->assertSame($qrs[$tier]['qr_sha256'],hash('sha256',$disk->get($qrs[$tier]['qr_path'])));
            }
            $checkout=app(DigitalCheckout::class); $order=$checkout->start($buyer,$product);
            $this->assertSame('public',$order->collection_snapshot['qr_tier']);
            $this->actingAs($buyer)->get('/mi-jakawi/productos-digitales/pedidos/'.$order->reference.'/qr')->assertOk()->assertStreamedContent($disk->get($qrs['public']['qr_path']));
            $member=User::factory()->create(); Membership::create(['user_id'=>$member->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'amount_paid'=>'100.00']);
            $memberOrder=$checkout->start($member,$product);
            $this->assertSame('12.50',$memberOrder->amount);
            $this->actingAs($member)->get('/mi-jakawi/productos-digitales/pedidos/'.$memberOrder->reference.'/qr')->assertOk()->assertStreamedContent($disk->get($qrs['member']['qr_path']));
            // Replacing an Admin QR preserves the exact snapshot used by the pending order.
            app(DigitalProductQr::class)->save($product,'public',UploadedFile::fake()->image('QA-REPLACEMENT.png',14,10),true,$admin->id);
            $this->actingAs($buyer)->get('/mi-jakawi/productos-digitales/pedidos/'.$order->reference.'/qr')->assertOk()->assertStreamedContent($disk->get($qrs['public']['qr_path']));
            $payment=$checkout->report($order,$buyer,UploadedFile::fake()->image('QA-RECEIPT.png',15,10));
            $this->assertDatabaseCount('digital_access_grants',0);
            $replacement=app(DigitalFiles::class)->store(UploadedFile::fake()->createWithContent('QA-V2.pdf',"%PDF-1.4\n% QA VERSION 2\n%%EOF"),'products');
            $product->update(['file_path'=>$replacement['path'],'file_sha256'=>$replacement['sha256']]);
            $checkout->confirm($payment,$admin,['bank_reference'=>'QA-MINIO-ONLY','bank_date'=>now()->timezone('America/La_Paz')->toDateString(),'received_amount'=>'20.00','currency'=>'BOB','account_key'=>app(DigitalCollection::class)->current()['account_key'],'bank_verified'=>true,'statement'=>UploadedFile::fake()->createWithContent('QA-STATEMENT.pdf',"%PDF-1.4\n% QA STATEMENT\n%%EOF")]);
            $grant=$order->access()->sole();
            $this->actingAs($buyer)->get('/mi-jakawi/productos-digitales/accesos/'.$grant->id.'/pdf')->assertOk()->assertContent($pdf);
            $this->get('/mi-jakawi/productos-digitales/accesos/'.$grant->id.'/pdf')->assertOk()->assertContent($pdf);
            $this->actingAs(User::factory()->create())->get('/mi-jakawi/productos-digitales/accesos/'.$grant->id.'/pdf')->assertForbidden();
            $this->assertTrue($disk->exists($order->file_path));
            config(['jakawi.prelaunch_mode'=>true]);
            $this->actingAs($buyer)->postJson('/productos-digitales/minio-qa/comprar')->assertStatus(423);
            // Private objects are not available anonymously through the S3 endpoint.
            $cfg=config('filesystems.disks.digital-private');
            foreach([$order->file_path,$qrs['public']['qr_path'],$qrs['member']['qr_path']] as $key) {
                $url=rtrim($cfg['endpoint'],'/').'/'.$cfg['bucket'].'/'.$cfg['root'].'/'.$key;
                $this->assertContains(Http::timeout(15)->get($url)->status(),[403,404]);
            }
        } finally {
            foreach($disk->allFiles() as $key) $disk->delete($key);
            foreach($publicKeys as $key) $media->delete($key);
        }
    }
}
