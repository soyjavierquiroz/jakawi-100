<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AppSetting;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembershipPaymentExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function configuredQr(): array
    {
        Storage::fake('minio-media');
        $admin = User::factory()->create(['is_admin' => true]);
        $file = UploadedFile::fake()->image('bank.png', 600, 700);
        $bytes = file_get_contents($file->getRealPath());
        app(MembershipBankQr::class)->save($file, $admin);

        return [$admin, $bytes];
    }

    public function test_buyer_is_session_identity_query_parameters_cannot_replace_them_and_intent_stays_stable(): void
    {
        $buyer = User::factory()->create(['name' => 'Miembro Real', 'email' => 'miembro@example.test']);
        $stranger = User::factory()->create(['name' => 'Otra Cuenta', 'email' => 'ajena@example.test']);
        $this->actingAs($buyer)->get('/membresia/checkout?user_id='.$stranger->id.'&email=ajena@example.test')->assertOk()->assertSee('Esta membresía será para')->assertSee('Miembro Real')->assertSee('miembro@example.test')->assertDontSee('ajena@example.test')->assertSee('Pendiente de pago')->assertDontSee('awaiting_payment');
        $p = MembershipBankPayment::sole();
        $reference = $p->reference;
        $this->get('/membresia/checkout')->assertOk()->assertSee($reference);
        $this->assertDatabaseCount('membership_bank_payments', 1);
        $this->assertSame($buyer->id, $p->user_id);
        $this->assertSame($buyer->global_subject_id, $p->user->global_subject_id);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'initiate_checkout')->count());
        $this->actingAs($stranger)->get('/membresia/checkout?payment_id='.$p->id)->assertOk()->assertDontSee($reference)->assertSee('Otra Cuenta');
        Storage::fake('membership-private');
        $this->post('/membresia/checkout/comprobante', ['payment_id' => $p->id])->assertStatus(422);
        $this->get('/admin/conciliacion-membresias/'.$p->id.'/comprobante')->assertForbidden();
        $this->assertSame('awaiting_payment', $p->fresh()->status);
    }

    public function test_original_qr_download_is_attachment_and_same_bytes_with_no_new_intent(): void
    {
        [, $bytes] = $this->configuredQr();
        $this->get('/membresia/checkout/qr/descargar')->assertRedirect('/login');
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->get('/membresia/checkout')->assertOk()->assertSee('Descargar QR')->assertDontSee('Abrir QR en tamaño original')->assertSee('Javier Enrrique Marcos Quiroz Sueldo');
        $this->get('/membresia/checkout/qr/descargar')->assertOk()->assertContent($bytes)->assertHeader('Content-Type', 'image/png')->assertHeader('Content-Disposition', 'attachment; filename="qr-membresia.png"');
        $this->assertDatabaseCount('membership_bank_payments', 1);
    }

    public function test_recipient_can_be_saved_without_replacing_or_modifying_minio_qr(): void
    {
        [$admin,$bytes] = $this->configuredQr();
        $before = AppSetting::where('key', 'membership_bank_qr')->sole()->value;
        $this->actingAs($admin)->post('/admin/conciliacion-membresias/qr', ['recipient_name' => 'Titular bancario actualizado'])->assertRedirect();
        $this->assertSame($before, AppSetting::where('key', 'membership_bank_qr')->sole()->value);
        $this->assertSame($bytes, Storage::disk('minio-media')->get($before['path']));
        $this->actingAs(User::factory()->create())->get('/membresia/checkout')->assertSee('Titular bancario actualizado');
        $this->post('/admin/conciliacion-membresias/qr', ['recipient_name' => 'Intruso'])->assertForbidden();
    }

    public function test_other_payer_preserves_member_receipt_and_intent_without_advertising_personal_data(): void
    {
        $this->configuredQr();
        Storage::fake('membership-private');
        $buyer = User::factory()->create(['name' => 'Titular Real']);
        $this->actingAs($buyer)->get('/membresia/checkout');
        $p = MembershipBankPayment::sole();
        $this->post('/membresia/checkout/comprobante', ['payer_name' => 'Pagador Distinto', 'receipt' => UploadedFile::fake()->image('receipt.png'), 'paid_date' => '2026-10-29', 'paid_time' => '10:20', 'declared_reference' => 'REF123', 'origin_bank' => 'Banco declarado'])->assertRedirect();
        $p->refresh();
        $this->assertNull($p->payer_name);
        $this->assertSame($buyer->id, $p->user_id);
        $this->assertSame('Titular Real', $p->user->name);
        $this->assertNull($p->declared_reference);
        $this->assertNotNull($p->submitted_at);
        Storage::disk('minio-media')->assertExists($p->receipt_path);
        $event = AnalyticsEvent::where('event_name', 'initiate_checkout')->sole();
        $this->assertStringNotContainsString('Pagador Distinto', json_encode($event->metadata));
        $this->assertStringNotContainsString($buyer->email, json_encode($event->metadata));
        $this->get('/membresia/checkout')->assertSee('Estamos verificando tu pago')->assertDontSee('Descargar QR')->assertDontSee('Ya pagué');
    }

    public function test_rejected_view_invites_correction_without_asking_for_another_payment_and_missing_name_links_profile(): void
    {
        $this->configuredQr();
        $buyer = User::factory()->create(['name' => 'Nombre']);
        $p = app(MembershipBankCheckout::class)->start($buyer);
        $p->update(['status' => 'rejected', 'review_note' => 'Necesitamos comprobante legible']);
        $this->actingAs($buyer)->get('/membresia/checkout')->assertSee('Comprobante observado')->assertSee('Necesitamos comprobante legible')->assertSee('Corregir comprobante')->assertSee('/settings/profile',false)->assertDontSee('Descargar QR')->assertDontSee('rejected');
    }
}
