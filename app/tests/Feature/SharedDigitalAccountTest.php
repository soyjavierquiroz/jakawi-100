<?php
namespace Tests\Feature;

use App\Models\{AppSetting,DigitalProduct,MembershipBankPayment,Partner,User};
use App\Services\{DigitalCheckout,DigitalCollection,DigitalProductQr,MembershipBankCheckout};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedDigitalAccountTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private DigitalProduct $product;
    private function settings(): array { return ['bank'=>'BANCO QA','account'=>'123456789','holder'=>'SOLO QA','currency'=>'BOB','enabled'=>true]; }
    protected function setUp(): void
    {
        parent::setUp();config(['jakawi.prelaunch_mode'=>false,'mail.default'=>'array']);Storage::fake('digital-private');
        $this->admin=User::factory()->create(['is_admin'=>true]);
        $bytes="%PDF-1.4\n% QA ONLY\n%%EOF";Storage::disk('digital-private')->put('products/shared-qa.pdf',$bytes);
        $this->product=DigitalProduct::create(['partner_id'=>Partner::where('slug','jakawi-digital')->value('id'),'slug'=>'gluteo-perfecto','name'=>'Glúteo Perfecto QA','status'=>'published','public_price'=>'49.00','member_price'=>'29.00','currency'=>'BOB','file_path'=>'products/shared-qa.pdf','file_sha256'=>hash('sha256',$bytes)]);
    }
    private function movement(string $reference): int
    {
        $import=DB::table('membership_bank_imports')->insertGetId(['sha256'=>hash('sha256',Str::uuid()),'account_key'=>'11A','bank'=>'BANCO QA','path'=>'qa-statement.pdf','admin_id'=>$this->admin->id,'document_metadata'=>json_encode(['account'=>'123456789','currency'=>'BOB','balance_verified'=>true]),'processing_status'=>'processed','created_at'=>now(),'updated_at'=>now()]);
        return DB::table('membership_bank_movements')->insertGetId(['import_id'=>$import,'account_key'=>'11A','fingerprint'=>hash('sha256',Str::uuid()),'paid_date'=>now()->toDateString(),'reference'=>$reference,'description'=>'JAKAWI QA','amount'=>'100.00','credit'=>'100.00','debit'=>'0.00','reconciliation_hold'=>false,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function memberPayment(?int $movement=null): MembershipBankPayment
    {
        $user=User::factory()->create(['global_subject_id'=>(string)Str::uuid()]);
        return MembershipBankPayment::create(['user_id'=>$user->id,'reference'=>(string)Str::uuid(),'checkout_event_id'=>(string)Str::uuid(),'checkout_at'=>now(),'status'=>$movement?'approved':'under_review','amount'=>'100.00','bank_movement_id'=>$movement]);
    }
    private function prepare(): void
    {
        $this->actingAs($this->admin)->post('/admin/digital/cobro',$this->settings())->assertSessionHasNoErrors();
        foreach(['public','member'] as $tier)app(DigitalProductQr::class)->save($this->product,$tier,UploadedFile::fake()->image('QA-'.$tier.'.png'),true,$this->admin->id);
    }
    private function digital(): array
    {
        $buyer=User::factory()->create();$checkout=app(DigitalCheckout::class);$order=$checkout->start($buyer,$this->product);
        $payment=$checkout->report($order,$buyer,UploadedFile::fake()->createWithContent('QA-receipt.pdf',"%PDF-1.4\n% QA ".Str::uuid()."\n%%EOF"));
        return [$order,$payment];
    }
    private function evidence(string $reference): array
    {
        return ['bank_reference'=>$reference,'bank_date'=>now()->timezone('America/La_Paz')->toDateString(),'received_amount'=>'49.00','currency'=>'BOB','account_key'=>app(DigitalCollection::class)->current()['account_key'],'bank_verified'=>true,'statement'=>UploadedFile::fake()->createWithContent('qa.pdf',"%PDF-1.4\n% QA ONLY\n%%EOF")];
    }
    public function test_account_already_used_for_memberships_is_accepted_with_one_authorization_and_no_exclusivity(): void
    {
        $this->movement('SHARED-QA-001');
        $before=DB::table('membership_bank_imports')->get()->toJson();
        $this->actingAs($this->admin)->from('/admin/digital/cobro')->post('/admin/digital/cobro',$this->settings()+['exclusive_account'=>false])->assertRedirect('/admin/digital/cobro')->assertSessionHasNoErrors();
        $saved=app(DigitalCollection::class)->current();$this->assertNotNull($saved);$this->assertArrayNotHasKey('exclusive_account',$saved);$this->assertArrayNotHasKey('exclusive_account',app(DigitalCollection::class)->settings());
        $version=$saved['version'];$this->post('/admin/digital/cobro',$this->settings())->assertSessionHasNoErrors();$this->assertSame($version,app(DigitalCollection::class)->current()['version']);$this->assertSame($before,DB::table('membership_bank_imports')->get()->toJson());
        config(['jakawi.prelaunch_mode'=>true]);$this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->canSell($this->product));
        $this->assertDatabaseCount('digital_orders',0);
    }
    public function test_legacy_false_exclusivity_does_not_block_an_authorized_account_and_disabled_account_stays_blocked(): void
    {
        $this->prepare();$s=app(DigitalCollection::class)->settings();$s['exclusive_account']=false;AppSetting::where('key',DigitalCollection::KEY)->sole()->update(['value'=>$s]);$this->assertTrue(app(DigitalCollection::class)->readyFor('BOB'));
        $s['enabled']=false;AppSetting::where('key',DigitalCollection::KEY)->sole()->update(['value'=>$s]);$this->assertNull(app(DigitalCollection::class)->current());
    }
    public function test_qrs_keep_individual_amount_account_currency_and_backend_selection(): void
    {
        $this->prepare();$qrs=app(DigitalProductQr::class);$this->assertTrue($qrs->verified($this->product,'public'));$this->assertTrue($qrs->verified($this->product,'member'));
        $this->assertSame('49.00',$qrs->settings($this->product)['public']['amount']);$this->assertSame('29.00',$qrs->settings($this->product)['member']['amount']);
        [$order]=$this->digital();$this->assertSame('public',$order->collection_snapshot['qr_tier']);$this->assertSame('49.00',$order->amount);
        $this->product->update(['currency'=>'USD']);$this->assertFalse($qrs->verified($this->product,'public'));$this->assertFalse($qrs->verified($this->product,'member'));$this->assertTrue($qrs->orderReady($order));
    }
    public function test_membership_transaction_cannot_be_applied_to_a_digital_order(): void
    {
        $this->prepare();$this->memberPayment($this->movement('SHARED-QA-USED'));[$order,$payment]=$this->digital();
        try{app(DigitalCheckout::class)->confirm($payment,$this->admin,$this->evidence('shared-qa-used'));$this->fail('Double use accepted');}catch(ValidationException $e){$this->assertArrayHasKey('bank_reference',$e->errors());}
        $this->assertSame('under_review',$payment->fresh()->status);$this->assertDatabaseCount('digital_access_grants',0);
    }
    public function test_digital_transaction_cannot_pay_membership_or_a_second_order_even_after_refund(): void
    {
        $this->prepare();[$order,$payment]=$this->digital();$service=app(DigitalCheckout::class);$service->confirm($payment,$this->admin,$this->evidence('SHARED-QA-DIGITAL'));$service->confirm($payment,$this->admin,$this->evidence('SHARED-QA-DIGITAL'));
        $member=$this->memberPayment();$movement=$this->movement(' shared-qa-digital ');
        try{app(MembershipBankCheckout::class)->approve($member->id,$movement,$this->admin);$this->fail('Double use accepted');}catch(ValidationException $e){$this->assertArrayHasKey('movement_id',$e->errors());}
        $service->refund($order,$this->admin,'QA-REFUND','QA only');
        try{app(MembershipBankCheckout::class)->approve($member->id,$movement,$this->admin);$this->fail('Refund allowed reference reuse');}catch(ValidationException $e){$this->assertArrayHasKey('movement_id',$e->errors());}
        [$other,$otherPayment]=$this->digital();
        try{$service->confirm($otherPayment,$this->admin,$this->evidence('SHARED-QA-DIGITAL'));$this->fail('Second digital use accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
        $approved=app(MembershipBankCheckout::class)->approve($member->id,$this->movement('DIFFERENT-QA-REFERENCE'),$this->admin);$this->assertSame('approved',$approved->status);$this->assertDatabaseCount('digital_access_grants',1);
    }
}
