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

class DigitalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $admin;

    private DigitalProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode' => false, 'jakawi.analytics.enabled' => true]);
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

    public function test_order_uses_backend_price_snapshot_and_is_idempotent(): void
    {
        $order = $this->order();
        $this->assertSame('20.00', $order->amount);
        $this->assertFalse($order->member_at_purchase);
        $this->product->update(['public_price' => '30.00']);
        $this->assertSame($order->id, $this->order()->id);
        $this->assertSame('20.00', $order->fresh()->amount);
        $this->actingAs($this->buyer)->post('/productos-digitales/gluteo-perfecto/comprar', ['amount' => '0.01'])->assertSessionHasErrors('amount');
    }

    public function test_active_and_expired_membership_prices_use_real_policy(): void
    {
        $member = Membership::create(['user_id' => $this->buyer->id, 'status' => 'active', 'type' => 'standard', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'amount_paid' => '100.00']);
        $order = $this->order();
        $this->assertSame('12.50', $order->amount);
        $this->assertTrue($order->member_at_purchase);
        $order->update(['status' => 'expired']);
        $member->update(['ends_at' => now()->subHour()]);
        $order = $this->order();
        $this->assertSame('20.00', $order->amount);
        $this->assertFalse($order->member_at_purchase);
    }

    public function test_pioneer_membership_uses_effective_end_and_missing_member_price_blocks_new_sales(): void
    {
        config(['jakawi.membership.official_launch_date' => null]);
        Membership::create(['user_id' => $this->buyer->id, 'status' => 'active', 'type' => 'pioneer', 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDay(), 'amount_paid' => '100.00']);
        $this->product->update(['member_price' => null]);
        try { $this->order(); $this->fail('Member price is required for fixed QR sales'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('product', $e->errors()); }
        $this->product->update(['member_price'=>'12.50']);
        $this->collection();
        $order = $this->order();
        $this->assertTrue($order->member_at_purchase);
        $this->assertSame('12.50', $order->amount);
        $this->assertSame('member', $order->collection_snapshot['qr_tier']);
    }

    public function test_premium_detail_preserves_both_fields_and_admin_preview_cannot_collect(): void
    {
        $this->product->update(['short_description'=>'Promesa breve', 'description'=>"Descripción completa.\n\nSegundo párrafo.", 'commercial_content'=>"Beneficios prácticos.\n\nInvitación comercial.", 'gallery_paths'=>[], 'cover_path'=>'digital-products/22/image/00000000-0000-4000-8000-000000000002.webp']);
        config(['media.imgproxy_url'=>'https://images.example.test', 'media.imgproxy_key'=>str_repeat('ab',32), 'media.imgproxy_salt'=>str_repeat('cd',32)]);
        $before = $this->product->fresh()->getAttributes();
        $this->get('/productos-digitales/gluteo-perfecto')->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('digital/show')->where('memberActive',false)->where('canCollect',true)
            ->where('pendingOrderPrice',null)->where('product.member_saving',['amount'=>'7.50','percent'=>38])->where('product.description',$this->product->description)
            ->where('product.commercial_content',$this->product->commercial_content)->has('product.gallery_urls',0)
            ->where('product.cover_url',fn ($url) => str_contains($url,'/rs:fit:1200:1500/'))
            ->has('product.cover_srcset',4));
        $this->actingAs($this->admin)->get('/admin/productos-digitales/gluteo-perfecto/preview')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('digital/show')->where('preview',true)->where('canCollect',false)->where('product.member_saving',['amount'=>'7.50','percent'=>38]));
        $this->assertSame($before,$this->product->fresh()->getAttributes());
        $this->assertDatabaseCount('digital_orders',0);
    }

    public function test_member_saving_is_a_display_projection_without_catalog_writes(): void
    {
        $before = $this->product->fresh()->getAttributes();
        foreach ([
            ['49.00','29.00',['amount'=>'20.00','percent'=>41]],
            ['59.90','39.50',['amount'=>'20.40','percent'=>34]],
            ['49.00','49.00',null],
            ['49.00',null,null],
            ['49.00','59.00',null],
        ] as [$public,$member,$expected]) {
            $this->product->forceFill(['public_price'=>$public,'member_price'=>$member]);
            $this->assertSame($expected,app(\App\Services\DigitalCatalog::class)->present($this->product)['member_saving']);
        }
        $this->assertSame($before,$this->product->fresh()->getAttributes());
        $this->assertDatabaseCount('digital_orders',0);
    }

    public function test_premium_purchase_rechecks_membership_after_view_and_continues_pending_amount(): void
    {
        $this->actingAs($this->buyer)->get('/productos-digitales/gluteo-perfecto')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('memberActive',false)->where('pendingOrderPrice',null));
        Membership::create(['user_id'=>$this->buyer->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'amount_paid'=>'100.00']);
        $this->post('/productos-digitales/gluteo-perfecto/comprar')->assertRedirect();
        $order = DigitalOrder::sole();
        $this->assertSame('12.50',$order->amount);
        $this->assertTrue($order->member_at_purchase);
        $this->get('/productos-digitales/gluteo-perfecto')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('memberActive',true)->where('pendingOrderPrice','12.50'));
        $this->post('/productos-digitales/gluteo-perfecto/comprar')->assertRedirect();
        $this->assertDatabaseCount('digital_orders',1);
    }

    public function test_unconfigured_qr_prevents_order_without_writes(): void
    {
        AppSetting::where('key', DigitalCollection::KEY)->delete();
        $this->actingAs($this->buyer)->post('/productos-digitales/gluteo-perfecto/comprar')->assertSessionHasErrors('product');
        $this->assertDatabaseCount('digital_orders', 0);
        $this->actingAs($this->admin)->get('/admin/digital/cobro')->assertOk();
    }

    public function test_account_and_currency_must_match(): void
    {
        $this->product->update(['currency' => 'USD']);
        $this->actingAs($this->buyer)->post('/productos-digitales/gluteo-perfecto/comprar')->assertSessionHasErrors('product');
    }

    public function test_pending_has_no_access_then_confirm_creates_one_grant_and_download_survives_expiry(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $this->assertDatabaseCount('digital_access_grants', 0);
        app(DigitalCheckout::class)->confirm($payment, $this->admin, $this->evidence());
        app(DigitalCheckout::class)->confirm($payment, $this->admin, $this->evidence());
        $this->assertDatabaseCount('digital_access_grants', 1);
        $grant = $order->access()->first();
        $this->assertSame('ready', $grant->delivery_status);
        $this->actingAs($this->buyer)->get('/mi-jakawi/productos-digitales/accesos/'.$grant->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('membership_bank_payments', 0);
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->assertDatabaseCount('conversions', 0);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'digital_purchase_confirmed')->count());
        $this->actingAs(User::factory()->create())->get('/mi-jakawi/productos-digitales/accesos/'.$grant->id.'/pdf')->assertForbidden();
        $this->get('/mi-jakawi/productos-digitales/pedidos/'.$order->reference)->assertNotFound();
    }

    public function test_receipt_does_not_confirm_payment_and_mismatched_bank_evidence_is_rejected(): void
    {
        $p = $this->payment();
        $this->assertSame('under_review', $p->status);
        $bad = $this->evidence();
        $bad['received_amount'] = '19.99';
        try {
            app(DigitalCheckout::class)->confirm($p, $this->admin, $bad);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('digital_access_grants', 0);
        $this->assertSame('pending', $p->order->status);
        $bad = $this->evidence();
        unset($bad['statement']);
        try {
            app(DigitalCheckout::class)->confirm($p, $this->admin, $bad);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_reject_retry_and_receipt_duplicate_do_not_create_duplicate_payment(): void
    {
        $order = $this->order();
        $p = $this->payment($order);
        $this->assertSame($p->id, $this->payment($order)->id);
        app(DigitalCheckout::class)->reject($p, $this->admin, 'No consta el abono');
        $p2 = $this->payment($order, 'receipt-corrected');
        $this->assertNotSame($p->id, $p2->id);
        $order->update(['status' => 'expired']);
        $other = $this->order();
        try {
            $this->payment($other, 'receipt-corrected');
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_bank_credit_cannot_pay_two_orders_even_after_refund(): void
    {
        $first = $this->order();
        $p = $this->payment($first);
        app(DigitalCheckout::class)->confirm($p, $this->admin, $this->evidence());
        app(DigitalCheckout::class)->refund($first, $this->admin, 'REFUND-QA', 'QA only');
        $second = $this->order();
        $p2 = $this->payment($second, 'new-receipt');
        try {
            app(DigitalCheckout::class)->confirm($p2, $this->admin, $this->evidence());
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('digital_access_grants', 1);
    }

    public function test_refund_revokes_download_preserves_history_and_allows_later_purchase(): void
    {
        $order = $this->order();
        $p = $this->payment($order);
        app(DigitalCheckout::class)->confirm($p, $this->admin, $this->evidence());
        $id = $order->access()->value('id');
        app(DigitalCheckout::class)->refund($order, $this->admin, 'REFUND-QA', 'Devolución QA');
        app(DigitalCheckout::class)->refund($order, $this->admin, 'REFUND-QA', 'Devolución QA');
        $this->actingAs($this->buyer)->get('/mi-jakawi/productos-digitales/accesos/'.$id.'/pdf')->assertForbidden();
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('20.00', $p->fresh()->refund_amount);
    }

    public function test_failed_delivery_does_not_undo_payment_and_retry_is_unique(): void
    {
        $order = $this->order();
        $p = $this->payment($order);
        $bytes = Storage::disk('digital-private')->get($order->file_path);
        Storage::disk('digital-private')->delete($order->file_path);
        app(DigitalCheckout::class)->confirm($p, $this->admin, $this->evidence());
        $grant = $order->access()->first();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('failed', $grant->delivery_status);
        Storage::disk('digital-private')->put($order->file_path, $bytes);
        app(DigitalCheckout::class)->deliver($grant, $this->admin);
        app(DigitalCheckout::class)->deliver($grant, $this->admin);
        $this->assertSame('ready', $grant->fresh()->delivery_status);
        $this->assertDatabaseCount('digital_access_grants', 1);
    }

    public function test_product_file_changes_do_not_replace_purchased_pdf(): void
    {
        $order = $this->order();
        $p = $this->payment($order);
        $old = $order->file_path;
        $new = app(DigitalFiles::class)->store($this->pdf('new-version'), 'products');
        $this->product->update(['file_path' => $new['path'], 'file_sha256' => $new['sha256']]);
        app(DigitalCheckout::class)->confirm($p, $this->admin, $this->evidence());
        $this->assertSame($old, $order->fresh()->file_path);
    }

    public function test_admin_staff_permissions_and_unauthenticated_buy(): void
    {
        $this->post('/productos-digitales/gluteo-perfecto/comprar')->assertRedirect(route('login'));
        $staff = User::factory()->create();
        $staff->partners()->attach($this->product->partner_id,['role' => 'staff']);
        foreach (['/admin/digital/cobro', '/admin/digital/pedidos', '/admin/productos-digitales'] as $url) {
            $this->actingAs($staff)->get($url)->assertForbidden();
        }
        $this->post('/admin/digital/cobro')->assertForbidden();
    }

    public function test_admin_product_publication_and_private_pdf_upload(): void
    {
        $this->actingAs($this->admin)->post('/admin/productos-digitales', [
            'name'=>'Nuevo PDF QA','slug'=>'nuevo-gluteo-perfecto','status'=>'published','currency'=>'BOB','delivery_type'=>'pdf',
            'public_price'=>'15.00','member_price'=>'10.00','description'=>'Solo QA',
            'support_information'=>'QA','access_terms'=>'Solo pruebas','pdf'=>$this->pdf('new-product'),'cover'=>UploadedFile::fake()->image('cover.jpg'),
        ])->assertRedirect('/admin/productos-digitales');
        $p=DigitalProduct::where('slug','nuevo-gluteo-perfecto')->firstOrFail();
        Storage::disk('digital-private')->assertExists($p->file_path);
        $this->get('/admin/productos-digitales/nuevo-gluteo-perfecto/pdf')->assertOk();
    }
    public function test_admin_qr_configuration_accepts_shared_account_and_preserves_membership_settings(): void
    {
        $before=DB::table('app_settings')->where('key','!=',DigitalCollection::KEY)->get()->toJson();
        $this->actingAs($this->admin)->post('/admin/digital/cobro',[
            'bank'=>'Banco QA','account'=>'QA-ONLY','holder'=>'QA','currency'=>'BOB','enabled'=>true,
            'exclusive_account'=>false,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/admin/digital/cobro',[
            'bank'=>'Banco QA','account'=>'QA-ONLY','holder'=>'QA','currency'=>'BOB','enabled'=>true,
            'exclusive_account'=>true,
        ])->assertRedirect();
        $this->assertTrue(app(DigitalCollection::class)->readyFor('BOB'));
        $this->assertSame($before,DB::table('app_settings')->where('key','!=',DigitalCollection::KEY)->get()->toJson());
    }
    public function test_order_price_is_immutable_and_access_does_not_depend_on_membership_renewal(): void
    {
        $m=Membership::create(['user_id'=>$this->buyer->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'amount_paid'=>'100.00']);
        $order=$this->order();$p=$this->payment($order);$e=$this->evidence();$e['received_amount']='12.50';
        app(DigitalCheckout::class)->confirm($p,$this->admin,$e);
        $m->update(['status'=>'cancelled']);$this->assertFalse($this->buyer->hasActiveMembership());
        $this->actingAs($this->buyer)->get('/mi-jakawi/productos-digitales/accesos/'.$order->access()->value('id').'/pdf')->assertOk();
        try{$order->update(['amount'=>'1.00']);$this->fail('Order price changed');}catch(\DomainException){$this->assertSame('12.50',$order->fresh()->amount);}
    }
    public function test_fixed_qr_selection_reuses_membership_policy_and_preserves_valid_order_after_expiry(): void
    {
        $qrs=app(\App\Services\DigitalProductQr::class);
        foreach (['free','standard','expired','pioneer','pioneer_expired'] as $kind) {
            config(['jakawi.membership.official_launch_date'=>$kind==='pioneer_expired' ? now()->subMonths(16)->toDateString() : now()->subMonth()->toDateString()]);
            $buyer=User::factory()->create();
            if ($kind!=='free') Membership::create(['user_id'=>$buyer->id,'status'=>'active','type'=>str_starts_with($kind,'pioneer')?'pioneer':'standard','starts_at'=>now()->subDays(10),'ends_at'=>$kind==='standard'?now()->addMinute():now()->subDay(),'amount_paid'=>'100.00']);
            $active=in_array($kind,['standard','pioneer'],true);
            $this->assertSame($active,$buyer->hasActiveMembership(),$kind);
            $this->assertSame($active,app(\App\Services\MembershipStatus::class)->for($buyer)['is_active']);
            $order=app(DigitalCheckout::class)->start($buyer,$this->product);
            $this->assertSame($active?'12.50':'20.00',$order->amount);
            $this->assertSame($active?'member':'public',$order->collection_snapshot['qr_tier']);
            $this->assertSame($qrs->settings($this->product)[$active?'member':'public']['qr_path'],$order->collection_snapshot['qr_path']);
            $this->assertArrayNotHasKey($active?'public':'member',$order->collection_snapshot);
            $this->actingAs($buyer)->get('/mi-jakawi/productos-digitales/pedidos/'.$order->reference.'/qr')->assertOk()->assertStreamedContent(Storage::disk('digital-private')->get($order->collection_snapshot['qr_path']));
            $this->post('/productos-digitales/gluteo-perfecto/comprar',['qr_tier'=>'member','member_at_purchase'=>true,'amount'=>'0.01'])->assertSessionHasErrors(['qr_tier','member_at_purchase','amount']);
            if ($kind==='standard') {
                $this->travel(2)->minutes();
                $this->assertFalse($buyer->hasActiveMembership());
                $same=app(DigitalCheckout::class)->start($buyer,$this->product);
                $this->assertSame($order->id,$same->id);
                $this->assertSame('12.50',$same->amount);
                $this->assertTrue($qrs->orderReady($same));
                $this->post('/mi-jakawi/productos-digitales/pedidos/'.$order->reference.'/comprobante',['receipt'=>$this->pdf('expired-after-valid-order')])->assertRedirect();
                $this->travelBack();
            }
        }
    }

    public function test_fixed_qr_price_currency_or_account_changes_require_reconfirmation(): void
    {
        $qrs=app(\App\Services\DigitalProductQr::class);
        $this->assertTrue($qrs->verified($this->product,'public'));
        $this->product->update(['public_price'=>'30.00']);
        $this->assertFalse($qrs->verified($this->product,'public'));
        $this->assertTrue($qrs->verified($this->product,'member'));
        $this->product->update(['public_price'=>'20.00']);
        $this->assertFalse($qrs->verified($this->product,'public'),'Reverting price must not silently reverify');
        $qrs->save($this->product,'public',null,true,$this->admin->id);
        $old=app(DigitalCollection::class)->current();
        $data=array_intersect_key($old,array_flip(['bank','account','holder','currency','enabled','exclusive_account']));
        $this->actingAs($this->admin)->post('/admin/digital/cobro',$data)->assertRedirect();
        $this->assertSame($old['version'],app(DigitalCollection::class)->current()['version']);
        $this->assertTrue($qrs->verified($this->product,'public'));
        $data['account']='NEW-QA-ONLY';
        $this->post('/admin/digital/cobro',$data)->assertRedirect();
        foreach(['public','member'] as $tier) $this->assertFalse($qrs->verified($this->product,$tier));
        foreach(['public','member'] as $tier) $qrs->save($this->product,$tier,null,true,$this->admin->id);
        $this->product->update(['currency'=>'USD']);$this->product->update(['currency'=>'BOB']);
        foreach(['public','member'] as $tier) $this->assertFalse($qrs->verified($this->product,$tier));
        $this->actingAs($this->buyer)->post('/productos-digitales/gluteo-perfecto/comprar')->assertSessionHasErrors('product');
        $this->assertDatabaseCount('digital_orders',0);
        $this->actingAs($this->admin)->post('/admin/digital/cobro',$data+['qr'=>UploadedFile::fake()->image('LEGACY-NOT-ALLOWED.png')])->assertSessionHasErrors('qr');
    }

    public function test_checkout_ux_summary_receipt_approval_and_pending_gift_are_preserved(): void
    {
        config(['digital_gifts.enabled' => true]);
        $this->product->update(['public_price'=>'49.00','member_price'=>'29.00','welcome_gift_enabled'=>true,'gift_pdf_sha256'=>$this->product->file_sha256]);
        $this->collection();
        $free = $this->order();
        $this->assertSame('49.00', $free->amount);
        $this->assertSame('public', $free->collection_snapshot['qr_tier']);
        $free->update(['status'=>'expired']);
        Membership::create(['user_id'=>$this->buyer->id,'status'=>'active','type'=>'pioneer','starts_at'=>now()->subDay(),'ends_at'=>now()->subHour(),'amount_paid'=>'100.00']);
        config(['jakawi.membership.official_launch_date'=>null]);
        $gift = app(\App\Services\DigitalGifts::class)->backfillWelcome($this->buyer);
        $this->actingAs($this->buyer)->get('/productos-digitales/gluteo-perfecto')->assertInertia(fn ($p) => $p->where('giftAction.kind','welcome')->where('canCollect',true)->etc());
        $order = $this->order();
        $this->assertSame('29.00',$order->amount);
        $this->assertSame('member',$order->collection_snapshot['qr_tier']);
        $this->assertSame('pending',$gift->fresh()->status);
        $this->get('/mi-jakawi/productos-digitales/pedidos/'.$order->reference)->assertInertia(fn ($p) => $p->where('order.public_price','49.00')->where('order.amount','29.00')->has('order.cover_url')->etc());
        $payment = $this->payment($order);
        $this->get('/mi-jakawi/productos-digitales/pedidos/'.$order->reference)->assertInertia(fn ($p) => $p->where('order.status','pending')->where('order.payments.0.status','under_review')->where('order.access',null)->etc());
        $evidence = $this->evidence(); $evidence['received_amount']='29.00';
        app(DigitalCheckout::class)->confirm($payment,$this->admin,$evidence);
        $this->get('/mi-jakawi/productos-digitales/pedidos/'.$order->reference)->assertInertia(fn ($p) => $p->where('order.status','confirmed')->where('order.access.delivery_status','ready')->etc());
        $this->get('/mi-jakawi/productos-digitales')->assertInertia(fn ($p) => $p->where('orders.data.0.status','confirmed')->etc());
        $this->get('/mi-jakawi/productos-digitales/accesos/'.$order->access()->value('id').'/pdf')->assertOk();
        $this->assertSame('pending',$gift->fresh()->status);
    }

    public function test_guest_login_and_registration_return_to_any_product_purchase(): void
    {
        $this->product->update(['slug'=>'otra-app-qa']);
        $url='/productos-digitales/otra-app-qa/comprar';
        $this->get($url)->assertOk()->assertSessionHas('url.intended', url($url))
            ->assertInertia(fn ($page) => $page->component('digital/purchase')->where('authenticated', false));
        $this->post('/login',['email'=>$this->buyer->email,'password'=>'password'])->assertRedirect(url($url));
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('digital/purchase')->where('authenticated', true));
        $this->post('/logout');
        $this->get($url)->assertOk()->assertSessionHas('url.intended', url($url))
            ->assertInertia(fn ($page) => $page->component('digital/purchase')->where('authenticated', false));
        $this->post('/register',['name'=>'Compra QA','email'=>'checkout-register@qa.invalid','whatsapp'=>'71234567'])->assertRedirect(url($url));
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('digital/purchase')->where('authenticated', true));
        $this->assertDatabaseCount('digital_orders',0);
    }

    public function test_purchase_screen_never_creates_orders_and_keeps_write_routes_protected(): void
    {
        $before = $this->product->fresh()->getAttributes();
        $url = '/productos-digitales/gluteo-perfecto/comprar';
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('digital/purchase')->where('canCollect', true));
        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs($this->buyer)->get($url)->assertOk();
        $this->assertDatabaseCount('digital_orders', 0);
        $this->assertSame($before, $this->product->fresh()->getAttributes());
        $this->product->update(['status' => 'draft']);
        $this->get($url)->assertNotFound();
    }

    public function test_purchase_return_rejects_external_destinations(): void
    {
        $this->withSession(['url.intended' => 'https://other.example/productos-digitales/otra-app/comprar'])
            ->post('/login', ['email' => $this->buyer->email, 'password' => 'password'])
            ->assertRedirect('/mi-jakawi');
        $this->post('/logout');
        $this->withSession(['url.intended' => 'https://other.example/productos-digitales/otra-app/comprar'])
            ->post('/register', ['name' => 'Compra QA', 'email' => 'external-return@qa.invalid', 'whatsapp' => '71234567'])
            ->assertRedirect(config('fortify.home'));
    }

}
