<?php

namespace Tests\Feature;

use App\Enums\AcquisitionProvider;
use App\Models\AnalyticsEvent;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\Commercial\CommercialDispatcher;
use App\Services\GrowthMeasurementService;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipService;
use App\Services\Meta\MetaEventPayloadFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembershipBankCheckoutTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['cities'];

    private function buyer(): User
    {
        return User::factory()->create();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function qr(): void
    {
        Storage::fake('membership-private');
        Storage::disk('membership-private')->put('qr/bank.png', 'real-configured-image');
        config(['membership_checkout.qr_path' => 'qr/bank.png']);
    }

    private function payment(?User $u = null, ?string $ref = 'ABC123'): MembershipBankPayment
    {
        $p = app(MembershipBankCheckout::class)->start($u ?? $this->buyer());
        $p->update(['status' => 'under_review', 'declared_reference' => $ref, 'paid_date' => '2026-10-29', 'paid_time' => '14:30:00']);

        return $p;
    }

    private function csv(string $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('statement.csv', "date,time,reference,description,amount,direction\n".$rows);
    }

    private function import(string $rows): array
    {
        Storage::fake('membership-private');

        return app(MembershipBankImport::class)->import($this->csv($rows), 'ACCOUNT', $this->admin());
    }

    private function movement(string $ref = 'ABC123'): int
    {
        $this->import("2026-10-29,14:30,$ref,Abono,100.00,credit\n");

        return DB::table('membership_bank_movements')->where('reference', $ref)->value('id');
    }

    public function test_checkout_is_protected_stable_and_keeps_internal_initiate_event_without_qr(): void
    {
        config(['membership_checkout.qr_path' => null]);
        $u = $this->buyer();
        $this->get('/membresia/checkout')->assertRedirect('/login');
        $this->actingAs($u)->get('/membresia/checkout')->assertOk()->assertSee('Aún no habilitamos los cobros')->assertDontSee('Ya pagué');
        $this->get('/membresia/checkout')->assertOk();
        $this->assertDatabaseCount('membership_bank_payments', 1);
        $p = MembershipBankPayment::first();
        $event = AnalyticsEvent::where('event_name', 'initiate_checkout')->sole();
        $this->assertSame($p->checkout_event_id, $event->event_id);
        $this->assertEquals(100, $event->metadata['value']);
        $this->assertSame('BOB', $event->metadata['currency']);
        $this->assertSame($p->reference, $event->metadata['payment_reference']);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->get('/membresia/checkout/qr')->assertNotFound();
        $this->post('/membresia/checkout/comprobante')->assertStatus(422);
    }

    public function test_declaration_private_access_rejection_and_resubmission_reuse_payment(): void
    {
        $this->qr();
        $u = $this->buyer();
        $this->actingAs($u)->get('/membresia/checkout');
        $data = ['receipt' => UploadedFile::fake()->image('receipt.png'), 'declared_reference' => ' abc123 ', 'paid_date' => '2026-10-29', 'paid_time' => '14:30'];
        $this->post('/membresia/checkout/comprobante', $data)->assertRedirect('/membresia/checkout');
        $p = MembershipBankPayment::first();
        $this->assertSame('under_review', $p->status);
        $this->assertSame('ABC123', $p->declared_reference);
        Storage::disk('membership-private')->assertExists($p->receipt_path);
        $this->get('/membresia/checkout')->assertSee('Recibimos tu comprobante.');
        $this->get('/admin/conciliacion-membresias/'.$p->id.'/comprobante')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/conciliacion-membresias/'.$p->id.'/comprobante')->assertOk()->assertDownload();
        $this->post('/admin/conciliacion-membresias/'.$p->id.'/revision', ['decision' => 'rejected', 'note' => 'Corrige referencia'])->assertRedirect();
        $this->actingAs($u)->get('/membresia/checkout')->assertSee('Corrige referencia');
        $this->post('/membresia/checkout/comprobante', $data)->assertRedirect();
        $this->assertSame('under_review', $p->fresh()->status);
        $this->assertDatabaseCount('membership_bank_payments', 1);
    }

    public function test_exact_unique_requires_explicit_approval_and_emits_atomic_outbox_without_rewards(): void
    {
        $p = $this->payment();
        $mid = $this->movement();
        $svc = app(MembershipBankCheckout::class);
        $this->assertSame('strong_unique', $svc->match($p)['classification']);
        $this->assertDatabaseCount('memberships', 0);
        $a = $this->admin();
        $svc->approve($p->id, $mid, $a, true);
        $svc->approve($p->id, $mid, $a, true);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('commercial_outbox', 1);
        $this->assertDatabaseCount('reward_transactions', 0);
        $p->refresh();
        $this->assertSame('approved', $p->status);
        $this->assertTrue($p->membership->isActive());
        $body = json_decode(DB::table('commercial_outbox')->sole()->raw_body, true);
        $this->assertSame('membership.activated', $body['event_type']);
        $this->assertSame($p->user->global_subject_id, $body['global_subject_id']);
        $this->assertSame($p->reference, $body['payment_reference']);
        $this->assertSame($p->membership->starts_at->toRfc3339String(), $body['occurred_at']);
        $this->assertArrayNotHasKey('receipt_path', $body);
        $this->assertArrayNotHasKey('declared_reference', $body);
    }

    public function test_same_time_without_reference_is_ambiguous_and_batch_cannot_approve(): void
    {
        $p = $this->payment(null, null);
        $this->movement('FIRST');
        $this->movement('SECOND');
        $m = app(MembershipBankCheckout::class)->match($p);
        $this->assertSame('ambiguous', $m['classification']);
        $this->assertCount(2, $m['candidates']);
        $this->actingAs($this->admin())->post('/admin/conciliacion-membresias/lote', ['payments' => [$p->id]])->assertStatus(422);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_duplicate_claims_and_duplicate_bank_reference_are_ambiguous(): void
    {
        $p = $this->payment();
        $this->payment();
        $this->movement();
        $this->assertSame('ambiguous', app(MembershipBankCheckout::class)->match($p)['classification']);
    }

    public function test_missing_reference_never_strong_even_with_one_candidate(): void
    {
        $p = $this->payment(null, null);
        $this->movement();
        $this->assertSame('ambiguous', app(MembershipBankCheckout::class)->match($p)['classification']);
    }

    public function test_conflicting_reference_is_not_time_match(): void
    {
        $p = $this->payment();
        $this->movement('OTHER');
        $this->assertSame('no_match', app(MembershipBankCheckout::class)->match($p)['classification']);
    }

    public function test_duplicate_statement_overlapping_export_and_debits(): void
    {
        Storage::fake('membership-private');
        $svc = app(MembershipBankImport::class);
        $a = $this->admin();
        $rows = "2026-10-29,14:30,ABC,Abono,100.00,credit\n2026-10-29,14:31,DEBIT,Salida,100.00,debit\n";
        $this->assertSame(1, $svc->import($this->csv($rows), 'ACCOUNT', $a)['imported']);
        $this->assertTrue($svc->import($this->csv($rows), 'ACCOUNT', $a)['duplicate']);
        $this->assertSame(0, $svc->import($this->csv("2026-10-29,14:30,ABC,Reexportado,100.00,credit\n"), 'ACCOUNT', $a)['imported']);
        $this->assertDatabaseCount('membership_bank_movements', 1);
    }

    public function test_invalid_import_is_atomic(): void
    {
        Storage::fake('membership-private');
        $this->actingAs($this->admin())->post('/admin/conciliacion-membresias/importar', ['account_key' => 'ACCOUNT', 'statement' => $this->csv("2026-10-29,14:30,A,Good,100.00,credit\n2026-99-99,14:30,B,Bad,100.00,credit\n")])->assertSessionHasErrors('statement');
        $this->assertDatabaseCount('membership_bank_imports', 0);
        $this->assertDatabaseCount('membership_bank_movements', 0);
    }

    public function test_used_movement_cannot_activate_second_membership(): void
    {
        $p = $this->payment();
        $p2 = $this->payment(null, 'OTHER');
        $mid = $this->movement();
        $a = $this->admin();
        app(MembershipBankCheckout::class)->approve($p->id, $mid, $a);
        $this->actingAs($a)->post('/admin/conciliacion-membresias/aprobar', ['payment_id' => $p2->id, 'movement_id' => $mid])->assertStatus(422);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertSame('under_review', $p2->fresh()->status);
    }

    public function test_batch_approves_only_selected_unique_payments(): void
    {
        $p = $this->payment();
        $q = $this->payment(null, 'SECOND');
        $this->movement();
        $this->movement('SECOND');
        $this->actingAs($this->admin())->get('/admin/conciliacion-membresias')->assertOk()->assertSee('Fuerte y única');
        $this->post('/admin/conciliacion-membresias/lote', ['payments' => [$p->id, $q->id]])->assertRedirect();
        $this->assertDatabaseCount('memberships', 2);
        $this->assertDatabaseCount('commercial_outbox', 2);
    }

    public function test_unauthorized_admin_actions_are_forbidden(): void
    {
        $p = $this->payment();
        $this->actingAs($this->buyer());
        foreach (['importar', 'aprobar', 'lote', $p->id.'/revision'] as $path) {
            $this->post('/admin/conciliacion-membresias/'.$path)->assertForbidden();
        }
        $this->get('/admin/conciliacion-membresias')->assertForbidden();
    }

    public function test_no_match_review_does_not_activate(): void
    {
        $p = $this->payment();
        $this->actingAs($this->admin())->post('/admin/conciliacion-membresias/'.$p->id.'/revision', ['decision' => 'no_match', 'note' => 'Esperar siguiente extracto'])->assertRedirect();
        $this->assertSame('under_review', $p->fresh()->status);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_meta_uses_existing_outbox_and_canonical_id(): void
    {
        $u = $this->buyer();
        $p = app(MembershipBankCheckout::class)->start($u);
        $e = AnalyticsEvent::where('event_id', $p->checkout_event_id)->sole();
        $payload = app(MetaEventPayloadFactory::class)->server($e);
        $this->assertSame('InitiateCheckout', $payload['event_name']);
        $this->assertSame($e->event_id, $payload['event_id']);
        $this->assertSame(100, $payload['custom_data']['value']);
    }

    public function test_unsupported_club_event_retains_body_and_retries(): void
    {
        $p = $this->payment();
        app(MembershipBankCheckout::class)->approve($p->id, $this->movement(), $this->admin());
        $raw = DB::table('commercial_outbox')->sole()->raw_body;
        config(['commercial.enabled' => true, 'commercial.secret' => str_repeat('a', 64)]);
        Http::fake(['*' => Http::response(['error' => 'unsupported_event'], 422)]);
        app(CommercialDispatcher::class)->run(1);
        $row = DB::table('commercial_outbox')->sole();
        $this->assertSame('RETRY', $row->status);
        $this->assertSame($raw, $row->raw_body);
        $this->assertSame(1, $row->attempts);
    }

    public function test_configured_meta_reuses_one_delivery_and_one_checkout_event(): void
    {
        $u = $this->buyer();
        config(['meta.enabled' => true, 'meta.capi_enabled' => true, 'meta.browser_enabled' => true, 'meta.pixel_id' => '123456', 'meta.access_token' => 'test-only', 'meta.api_version' => 'v25.0']);
        $this->mock(GrowthMeasurementService::class)->shouldReceive('context')->once()->with($u->id)->andReturn(['user_id' => $u->id, 'acquisition_provider' => AcquisitionProvider::META]);
        $this->actingAs($u)->get('/membresia/checkout')->assertOk();
        $this->get('/membresia/checkout')->assertOk();
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'initiate_checkout')->count());
        $this->assertDatabaseCount('growth_provider_deliveries', 1);
    }

    public function test_activation_failure_leaves_bank_payment_and_outbox_untouched(): void
    {
        $p = $this->payment();
        $mid = $this->movement();
        $admin = $this->admin();
        app(MembershipService::class)->activate($p->user, $admin);
        $this->actingAs($admin)->post('/admin/conciliacion-membresias/aprobar', ['payment_id' => $p->id, 'movement_id' => $mid])->assertStatus(422);
        $this->assertSame('under_review', $p->fresh()->status);
        $this->assertNull($p->fresh()->bank_movement_id);
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_activation_queues_notification_once_in_existing_database_queue(): void
    {
        $p = $this->payment();
        $mid = $this->movement();
        $admin = $this->admin();
        app(MembershipBankCheckout::class)->approve($p->id, $mid, $admin);
        app(MembershipBankCheckout::class)->approve($p->id, $mid, $admin);
        $this->assertDatabaseCount('jobs',1);
    }
}
