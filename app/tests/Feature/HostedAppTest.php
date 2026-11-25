<?php

namespace Tests\Feature;
use App\Models\AnalyticsEvent;
use App\Models\AppSetting;
use App\Models\DigitalOrder;
use App\Models\DigitalPayment;
use App\Models\DigitalProduct;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\User;
use App\Services\DigitalCheckout;
use App\Services\DigitalCollection;
use App\Services\DigitalFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HostedAppTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $admin;

    private DigitalProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hosted_apps.secret' => str_repeat('a',64), 'jakawi.prelaunch_mode' => false, 'jakawi.analytics.enabled' => true]);
        Storage::fake('digital-private');
        $this->buyer = User::factory()->create();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $pdf = app(DigitalFiles::class)->store($this->pdf('product'), 'products');
        $this->product = DigitalProduct::create(['partner_id' => Partner::where('slug', 'jakawi-digital')->value('id'), 'name' => 'PDF QA', 'slug' => 'gluteo-perfecto', 'status' => 'published', 'public_price' => '20.00', 'member_price' => '12.50', 'currency' => 'BOB', 'file_path' => $pdf['path'], 'file_sha256' => $pdf['sha256']]);
        $this->collection();
        app(\App\Services\DigitalSaleAvailability::class)->configure($this->product,true,true,$this->admin->id);
    }

    private function pdf(string $text = 'statement'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($text.'.pdf', "%PDF-1.4\n1 0 obj <</Type /Catalog>> endobj\n% ".$text."\n%%EOF");
    }

    private function collection(): void
    {
        AppSetting::updateOrCreate(['key' => DigitalCollection::KEY], ['value' => ['enabled' => true, 'exclusive_account' => true, 'bank' => 'BANCO QA NO REAL', 'account' => 'QA-000', 'holder' => 'QA', 'currency' => 'BOB', 'version' => 'qa-v1', 'account_key' => hash('sha256', 'BANCO QA NO REAL|QA000')]]);
        foreach (['public','member'] as $tier) app(\App\Services\DigitalProductQr::class)->save($this->product, $tier, UploadedFile::fake()->image('QA-ONLY-'.$tier.'.png', $tier === 'public' ? 10 : 12, 10), true, $this->admin->id);
    }

    private function order(): DigitalOrder
    {
        return app(DigitalCheckout::class)->start($this->buyer, $this->product);
    }

    private function payment(?DigitalOrder $order = null, string $receipt = 'receipt'): DigitalPayment
    {
        return app(DigitalCheckout::class)->report($order ?? $this->order(), $this->buyer, $this->pdf($receipt));
    }

    private function evidence(string $ref = 'BANK-QA-1234'): array
    {
        return ['bank_reference' => $ref, 'bank_date' => now()->timezone('America/La_Paz')->toDateString(), 'received_amount' => '20.00', 'currency' => 'BOB', 'account_key' => hash('sha256', 'BANCO QA NO REAL|QA000'), 'bank_verified' => true, 'statement' => $this->pdf()];
    }


    private function hosted(): void
    {
        \Illuminate\Support\Facades\Http::fake(['jakawi.store/*' => \Illuminate\Support\Facades\Http::response(['protected'=>true,'functional'=>true])]);
        $this->product->update(['delivery_type'=>'hosted_app','app_key'=>'gluteos','file_path'=>null,'file_sha256'=>null,'public_price'=>'49.00','member_price'=>'29.00']);
        $this->collection();
        app(\App\Services\DigitalSaleAvailability::class)->configure($this->product,true,true,$this->admin->id);
    }

    public function test_purchase_both_tiers_receipt_approval_pass_and_refund_without_pdf(): void
    {
        $this->hosted();
        $apps=app(\App\Services\HostedApps::class);
        foreach ([false,true] as $member) {
            $this->buyer=User::factory()->create();
            if ($member) Membership::create(['user_id'=>$this->buyer->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'amount_paid'=>'100.00']);
            $order=$this->order();
            $this->assertSame($member?'29.00':'49.00',$order->amount);
            $this->assertSame($member?'member':'public',$order->collection_snapshot['qr_tier']);
            $payment=$this->payment($order,'receipt-'.(int)$member);
            $this->assertNull($order->access);
            $e=$this->evidence('QA-APP-'.(int)$member);$e['received_amount']=$order->amount;
            app(DigitalCheckout::class)->confirm($payment,$this->admin,$e);
            $grant=$order->access()->sole();
            $this->assertSame('hosted_app',$grant->delivery_type);
            $this->assertSame('ready',$grant->delivery_status);
            $url='/mi-jakawi/productos-digitales/accesos/'.$grant->id.'/app';
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
            $this->actingAs($this->buyer)->get($url)->assertOk()->assertHeader('Referrer-Policy','no-referrer')->assertSee('method="post"',false);
            $token=$apps->issue($grant);
            $credential=$apps->consume($token,'gluteos')['credential'];
            $this->assertTrue($apps->check($credential,'gluteos'));
            try {$apps->consume($token,'gluteos');$this->fail('Pass replay accepted');} catch (HttpException $e) {$this->assertSame(403,$e->getStatusCode());}
            $pending=$apps->issue($grant);
            app(DigitalCheckout::class)->refund($order,$this->admin,'QA-REFUND','Isolated QA');
            $this->assertFalse($apps->check($credential,'gluteos'));
            $this->get($url)->assertForbidden();
            try {$apps->consume($pending,'gluteos');$this->fail('Revoked pending pass accepted');} catch (HttpException $e) {$this->assertSame(403,$e->getStatusCode());}
        }
    }

    public function test_existing_gift_and_pending_gift_survive_switch_and_new_gifts_need_no_pdf(): void
    {
        config(['digital_gifts.enabled'=>true]);
        $gifts=app(\App\Services\DigitalGifts::class);
        $this->product->update(['welcome_gift_enabled'=>true,'gift_pdf_sha256'=>$this->product->file_sha256]);
        Membership::create(['user_id'=>$this->buyer->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'amount_paid'=>'100.00']);
        $gift=$gifts->backfillWelcome($this->buyer);
        $grant=$gifts->claim($this->buyer,$this->product);
        $before=$grant->fresh()->getAttributes();
        $this->hosted();
        $this->assertSame($before,$grant->fresh()->getAttributes());
        $apps=app(\App\Services\HostedApps::class);
        $this->assertTrue($apps->check($apps->consume($apps->issue($grant),'gluteos')['credential'],'gluteos'));
        $this->actingAs($this->buyer)->get('/mi-jakawi/productos-digitales')->assertInertia(fn($p)=>$p->where('giftAccess.data.0.delivery_type','hosted_app')->etc());
        $next=User::factory()->create();Membership::create(['user_id'=>$next->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'amount_paid'=>'100.00']);
        $pending=$gifts->backfillWelcome($next);
        $this->assertTrue($gifts->eligible($this->product,'welcome'));
        $this->actingAs($next)->get('/productos-digitales/gluteo-perfecto')->assertOk();
        $this->assertSame('pending',$pending->fresh()->status);
        $this->assertNull($pending->digital_product_id);
        $new=$gifts->claim($next,$this->product);
        $this->assertSame('hosted_app',$new->delivery_type);
        $this->assertTrue($apps->authorized($new));
        $this->assertSame('claimed',$gift->fresh()->status);
        $token=$apps->issue($new);$this->travel(61)->seconds();
        try {$apps->consume($token,'gluteos');$this->fail('Expired pass accepted');} catch (HttpException $e) {$this->assertSame(403,$e->getStatusCode());}
    }

    public function test_admin_saves_app_without_pdf_and_rejects_unregistered_app(): void
    {
        $this->hosted();
        $this->product->update(['cover_path'=>'qa-cover.png','description'=>'QA APP','support_information'=>'QA','access_terms'=>'QA']);
        $data=['name'=>$this->product->name,'slug'=>$this->product->slug,'status'=>'published','currency'=>'BOB','public_price'=>'49.00','member_price'=>'29.00','description'=>'QA APP','support_information'=>'QA','access_terms'=>'QA','delivery_type'=>'hosted_app','app_key'=>'gluteos','sale_enabled'=>true,'pdf_final'=>true];
        $this->actingAs($this->admin)->post('/admin/productos-digitales/gluteo-perfecto',$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($this->product->fresh()->file_path);
        $data['app_key']='arbitrary-url';
        $this->post('/admin/productos-digitales/gluteo-perfecto',$data)->assertSessionHasErrors('app_key');
    }

    public function test_bridge_rejects_bad_server_credentials_and_unhealthy_delivery(): void
    {
        $this->postJson('/internal/hosted-app/exchange',['action'=>'check','app'=>'gluteos','credential'=>'bad'])->assertUnauthorized();
        $this->withToken(config('hosted_apps.secret'))->postJson('/internal/hosted-app/exchange',['action'=>'check','app'=>'gluteos','credential'=>'bad'])->assertOk()->assertJson(['authorized'=>false]);
        $this->hosted();
        \Illuminate\Support\Facades\Cache::forget('hosted-app-health:gluteos');
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory);
        \Illuminate\Support\Facades\Http::fake(['jakawi.store/*'=>\Illuminate\Support\Facades\Http::response(['protected'=>false,'functional'=>true])]);
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($this->product));
    }
}
