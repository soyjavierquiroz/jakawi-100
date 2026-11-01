<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipBankQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConsciousPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('minio-media');
        Storage::fake('membership-private');
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->buyer = User::factory()->create(['name' => 'María Beneficiaria']);
        app(MembershipBankQr::class)->save(UploadedFile::fake()->image('qr.png', 400, 400), $this->admin);
    }

    private function start(): MembershipBankPayment
    {
        $this->actingAs($this->buyer)->get('/membresia/checkout')->assertOk();

        return MembershipBankPayment::sole();
    }

    private function notice(MembershipBankPayment $payment): array
    {
        return ['payment_confirmed' => '1', 'payment_reference' => $payment->reference];
    }

    public function test_confirmation_is_required_server_side_and_bound_to_the_existing_owned_purchase(): void
    {
        $this->actingAs($this->buyer)->postJson('/membresia/checkout/aviso', ['payment_confirmed' => true])->assertUnprocessable();
        $this->assertDatabaseCount('membership_bank_payments', 0);
        $p = $this->start();
        $this->get('/membresia/checkout')->assertSee('JAKAWI de María Beneficiaria.')->assertSee('Ya pagué · Registrar mi aviso')
            ->assertSee('id="report-payment" class="discovery-primary-action" disabled', false)
            ->assertDontSee('checked="checked"', false);
        foreach ([[], ['payment_confirmed' => false], ['payment_confirmed' => '0']] as $data) {
            $this->postJson('/membresia/checkout/aviso', $data + ['payment_reference' => $p->reference])
                ->assertUnprocessable()->assertJsonValidationErrors('payment_confirmed');
        }
        $this->assertNull($p->fresh()->reported_at);
        $this->assertNull($p->fresh()->payment_confirmed_at);
        $this->actingAs(User::factory()->create())->postJson('/membresia/checkout/aviso', $this->notice($p))->assertNotFound();
        $this->assertSame('awaiting_payment', $p->fresh()->status);
        $this->assertDatabaseCount('membership_bank_payments', 1);
    }

    public function test_double_click_refresh_abandonment_and_new_login_keep_one_notice_then_accept_late_receipt(): void
    {
        $p = $this->start();
        $this->post('/membresia/checkout/aviso', $this->notice($p))->assertRedirect('/membresia/checkout');
        $at = $p->fresh()->reported_at;
        $confirmed = $p->fresh()->payment_confirmed_at;
        $this->assertNotNull($confirmed);
        $this->travel(2)->hours();
        $this->post('/membresia/checkout/aviso', $this->notice($p))->assertRedirect();
        $this->get('/membresia/checkout')->assertSee('¡Recibimos tu aviso de pago!')->assertSee('Adjunta tu comprobante')->assertDontSee('Opcional');
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $page) => $page->has('pendingReceipts', 1)
            ->where('pendingReceipts.0.reference', $p->reference)->where('pendingReceipts.0.upload_url', '/membresia/checkout#adjuntar-comprobante'));
        $this->post('/logout')->assertRedirect();
        $this->get('/membresia/checkout')->assertRedirect('/login');
        $this->actingAs($this->buyer->fresh())->get('/membresia/checkout')->assertSee('¡Recibimos tu aviso de pago!');
        $this->assertEquals($at, $p->fresh()->reported_at);
        $this->assertEquals($confirmed, $p->fresh()->payment_confirmed_at);
        $this->assertSame(1, AuditLog::where('action', 'membership_payment_reported')->count());
        $this->assertDatabaseCount('membership_bank_payments', 1);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->actingAs($this->admin)->get('/admin/conciliacion-membresias')->assertSee('Falta adjuntar comprobante');
        $this->actingAs($this->buyer)->post('/membresia/checkout/comprobante', ['receipt' => UploadedFile::fake()->image('tardio.png')])->assertRedirect();
        $this->assertSame('under_review', $p->fresh()->status);
        $this->assertFalse($p->fresh()->needsReceipt());
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $page) => $page->has('pendingReceipts', 0));
        $this->post('/membresia/checkout/aviso', $this->notice($p))->assertRedirect();
        $this->assertSame('under_review', $p->fresh()->status);
        $this->assertEquals($confirmed, $p->fresh()->payment_confirmed_at);
    }

    public function test_bank_confirmation_without_receipt_clears_pending_and_delayed_notice_does_not_create_purchase(): void
    {
        $p = $this->start();
        $this->post('/membresia/checkout/aviso', $this->notice($p))->assertRedirect();
        $at = $p->fresh()->payment_confirmed_at;
        $csv = UploadedFile::fake()->createWithContent('bank.csv', "date,time,reference,description,amount,direction\n2026-10-31,10:00,EXACT,Transferencia identificada,100.00,credit\n");
        app(MembershipBankImport::class)->import($csv, 'TEST', $this->admin);
        $mid = DB::table('membership_bank_movements')->sole()->id;
        app(MembershipBankCheckout::class)->approve($p->id, $mid, $this->admin);
        $this->assertNull($p->fresh()->receipt_path);
        $this->assertFalse($p->fresh()->needsReceipt());
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $page) => $page->has('pendingReceipts', 0));
        $this->actingAs($this->admin)->get('/admin/conciliacion-membresias')->assertDontSee('Falta adjuntar comprobante');
        // Replayed after the membership expires, this POST must still target the old purchase.
        $this->travel(400)->days();
        $this->actingAs($this->buyer)->post('/membresia/checkout/aviso', $this->notice($p))->assertRedirect();
        $this->assertDatabaseCount('membership_bank_payments', 1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('commercial_outbox', 1);
        $this->assertSame('approved', $p->fresh()->status);
        $this->assertEquals($at, $p->fresh()->payment_confirmed_at);
    }

    public function test_legacy_receipt_upload_does_not_invent_an_explicit_checkbox_confirmation(): void
    {
        $p = $this->start();
        $this->post('/membresia/checkout/comprobante', ['receipt' => UploadedFile::fake()->image('directo.png')])->assertRedirect();
        $this->assertNotNull($p->fresh()->reported_at);
        $this->assertNull($p->fresh()->payment_confirmed_at);
        $this->assertDatabaseCount('memberships', 0);
    }
}
