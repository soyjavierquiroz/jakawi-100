<?php

namespace Tests\Feature;

use App\Models\{AppSetting, DigitalAccessGrant, DigitalOrder, DigitalProduct, LandingPresentation, Membership, User};
use App\Services\{DigitalCollection, DigitalFiles, DigitalSaleAvailability, GluteoPerfectoDraft, GluteoPerfectoVisibility};
use App\Support\PrelaunchOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalSaleActivationTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name.'.pdf', "%PDF-1.4\n% QA ONLY - NOT FOR SALE - $name\n1 0 obj <</Type /Catalog>> endobj\n%%EOF");
    }

    private function product(): DigitalProduct
    {
        config(['jakawi.prelaunch_mode' => true]);
        Storage::fake('digital-private');
        app(GluteoPerfectoDraft::class)->create(false);
        app(GluteoPerfectoVisibility::class)->publish(false);
        return DigitalProduct::where('slug', 'gluteo-perfecto')->sole();
    }

    private function prepare(DigitalProduct $p, User $admin): void
    {
        $pdf = app(DigitalFiles::class)->store($this->pdf('DELIVERY-QA'), 'products');
        $p->update(['public_price'=>'20.00', 'member_price'=>'12.50', 'file_path'=>$pdf['path'], 'file_sha256'=>$pdf['sha256']]);
        Storage::disk('digital-private')->put('qr/qa.png', 'QA ONLY');
        AppSetting::updateOrCreate(['key'=>DigitalCollection::KEY], ['value'=>['enabled'=>true,'exclusive_account'=>true,'variable_amount_qr'=>true,'bank'=>'QA BANK SIMULATED','holder'=>'QA ONLY','account'=>'QA-NOT-REAL','currency'=>'BOB','version'=>'qa-1','account_key'=>hash('sha256','qa-only'),'qr_path'=>'qr/qa.png','qr_sha256'=>hash('sha256','QA ONLY')]]);
        foreach (['public','member'] as $tier) app(\App\Services\DigitalProductQr::class)->save($p, $tier, UploadedFile::fake()->image('QA-ONLY-'.$tier.'.png'), true, $admin->id);
        app(DigitalSaleAvailability::class)->configure($p, true, true, $admin->id);
    }

    public function test_prelaunch_purchase_approval_library_and_repeat_download_for_free_and_active_member(): void
    {
        $p=$this->product(); $admin=User::factory()->create(['is_admin'=>true]);
        $this->prepare($p,$admin);
        $blocks=LandingPresentation::where('slug','gluteo-perfecto')->value('editorial_blocks');
        $this->get('/l/gluteo-perfecto')->assertOk()->assertInertia(fn(Assert $a)=>$a->where('canBuy',true)->where('product.coming_soon',false)->where('copy.eyebrow','Disponible')->has('blocks',11));
        $this->get('/productos-digitales/gluteo-perfecto/comprar')->assertRedirect('/login');
        $this->assertStringEndsWith('/productos-digitales/gluteo-perfecto/comprar',session('url.intended'));
        $loginBuyer=User::factory()->create();
        $this->post('/login',['email'=>$loginBuyer->email,'password'=>'password'])->assertRedirect('/productos-digitales/gluteo-perfecto/comprar');
        foreach ([false,true] as $member) {
            $buyer=User::factory()->create();
            if ($member) {
                config(['jakawi.prelaunch_mode'=>false]);
                Membership::create(['user_id'=>$buyer->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addMinute(),'amount_paid'=>'100.00']);
                config(['jakawi.prelaunch_mode'=>true]);
            }
            $this->actingAs($buyer)->get('/productos-digitales/gluteo-perfecto/comprar')->assertOk()->assertInertia(fn(Assert $a)=>$a->where('canCollect',true)->where('memberActive',$member));
            $this->post('/productos-digitales/gluteo-perfecto/comprar')->assertRedirect();
            $o=DigitalOrder::where('user_id',$buyer->id)->sole(); $base='/mi-jakawi/productos-digitales/pedidos/'.$o->reference;
            $this->assertSame($member?'12.50':'20.00',$o->amount);
            if ($member) { $this->travel(2)->minutes(); $this->assertFalse($buyer->hasActiveMembership()); $this->assertSame('12.50',$o->fresh()->amount); }
            $this->get($base)->assertInertia(fn(Assert $a)=>$a->where('prelaunch',false));
            $this->get($base.'/qr')->assertOk();
            $this->post($base.'/comprobante',['receipt'=>$this->pdf('receipt-'.$buyer->id)])->assertRedirect();
            $this->post($base.'/comprobante',['receipt'=>$this->pdf('receipt-'.$buyer->id)])->assertRedirect();
            $this->assertSame(1,$o->payments()->count());
            $this->assertSame(0,$o->access()->count());
            $payment=$o->payments()->sole();
            $e=['bank_verified'=>true,'bank_reference'=>'QA-SIMULATED-'.$buyer->id,'bank_date'=>now()->timezone('America/La_Paz')->toDateString(),'received_amount'=>$o->amount,'currency'=>'BOB','account_key'=>hash('sha256','qa-only'),'statement'=>$this->pdf('BANK-STATEMENT-SIMULATED')];
            $this->actingAs($admin)->get('/admin/digital/pedidos/'.$o->reference)->assertInertia(fn(Assert $a)=>$a->where('prelaunch',false)->where('refundBlocked',true));
            $this->post('/admin/digital/pagos/'.$payment->id.'/confirmar',array_replace($e,['bank_verified'=>false]))->assertSessionHasErrors('bank_verified');
            $this->assertSame(0,$o->access()->count());
            $this->post('/admin/digital/pagos/'.$payment->id.'/confirmar',$e)->assertRedirect();
            $this->post('/admin/digital/pagos/'.$payment->id.'/confirmar',$e)->assertRedirect();
            $grant=$o->access()->sole();$this->assertSame('ready',$grant->delivery_status);
            $this->actingAs($buyer)->get('/mi-jakawi/productos-digitales')->assertInertia(fn(Assert $a)=>$a->where('orders.data.0.digital_allowed',true)->where('orders.data.0.access.id',$grant->id));
            $url='/mi-jakawi/productos-digitales/accesos/'.$grant->id.'/pdf';
            foreach ([1,2] as $_) $this->get($url)->assertOk()->assertHeader('Content-Type','application/pdf')->assertSee('QA ONLY - NOT FOR SALE',false);
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
            $this->travelBack();
        }
        $this->assertDatabaseCount('digital_access_grants',2);
        $this->assertEquals($blocks,LandingPresentation::where('slug','gluteo-perfecto')->value('editorial_blocks'));
        $this->assertTrue(PrelaunchOperations::blocked());
        $this->expectException(\App\Exceptions\PrelaunchOperationBlocked::class);
        Membership::create(['user_id'=>$admin->id,'status'=>'active','type'=>'standard','starts_at'=>now(),'ends_at'=>now()->addDay()]);
    }

    public function test_activation_is_default_off_requires_every_prerequisite_and_is_product_specific(): void
    {
        $p=$this->product();$admin=User::factory()->create(['is_admin'=>true]);$buyer=User::factory()->create();$sale=app(DigitalSaleAvailability::class);
        $this->assertFalse($sale->exceptionAllowed($p));
        $this->actingAs($admin)->post('/admin/productos-digitales/gluteo-perfecto',['name'=>$p->name,'slug'=>$p->slug,'status'=>'published','currency'=>'BOB','pdf_final'=>true,'sale_enabled'=>true])->assertSessionHasErrors('sale_enabled');
        $this->assertDatabaseMissing('app_settings',['key'=>DigitalSaleAvailability::KEY]);
        $this->prepare($p,$admin);
        $other=DigitalProduct::create(['partner_id'=>$p->partner_id,'slug'=>'other-pdf','name'=>'Other QA','status'=>'published','public_price'=>'20.00','currency'=>'BOB','file_path'=>$p->file_path,'file_sha256'=>$p->file_sha256]);
        $this->actingAs($buyer)->post('/productos-digitales/other-pdf/comprar')->assertStatus(423);
        foreach (['price','pdf','final','collection','publication','authorization'] as $missing) {
            $this->prepare($p,$admin);
            match ($missing) {
                'price'=>$p->update(['public_price'=>null]),
                'pdf'=>Storage::disk('digital-private')->put($p->file_path,'CORRUPT QA'),
                'final'=>AppSetting::where('key',DigitalSaleAvailability::KEY)->update(['value'=>json_encode(['product_id'=>$p->id,'enabled'=>true,'pdf_sha256'=>'wrong'])]),
                'collection'=>AppSetting::where('key',DigitalCollection::KEY)->delete(),
                'publication'=>$p->update(['status'=>'draft']),
                'authorization'=>AppSetting::where('key',DigitalSaleAvailability::KEY)->delete(),
            };
            $this->assertFalse($sale->exceptionAllowed($p->fresh()),$missing);
            $this->post('/productos-digitales/gluteo-perfecto/comprar')->assertStatus(423);
            $p->update(['status'=>'published']);
        }
        $this->assertDatabaseCount('digital_orders',0);
    }
}
