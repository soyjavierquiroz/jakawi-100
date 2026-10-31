<?php

namespace Tests\Feature;

use App\Jobs\ExtractMembershipReceipt;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipBankQr;
use App\Services\MembershipReceiptVision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptExtractionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio-media');
        Http::preventStrayRequests();
        config(['receipt_ai.api_key' => 'isolated-test-key', 'receipt_ai.model' => 'gpt-4.1-mini']);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->buyer = User::factory()->create();
        app(MembershipBankQr::class)->save(UploadedFile::fake()->image('qr.png', 400, 400), $this->admin);
    }

    private function submit(?UploadedFile $file = null): MembershipBankPayment
    {
        $this->actingAs($this->buyer)->get('/membresia/checkout')->assertOk();
        $this->post('/membresia/checkout/comprobante', ['receipt' => $file ?? UploadedFile::fake()->image('receipt.png')])->assertRedirect();

        return MembershipBankPayment::sole();
    }

    private function data(array $override = []): array
    {
        return [...array_fill_keys(MembershipReceiptVision::FIELDS, null), 'reference' => 'REF-100', 'amount' => '100.00', 'currency' => 'BOB', 'date' => '2026-10-29', 'time' => '10:20:00', 'recipient' => 'Javier Enrrique Marcos Quiroz Sueldo', 'confidence' => 'high', 'evidence' => ['reference' => 'REF-100', 'amount' => 'Bs 100.00'], ...$override];
    }

    private function response(array $data): array
    {
        return ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
    }

    private function extractReceipt(MembershipBankPayment $p): void
    {
        (new ExtractMembershipReceipt($p->id, $p->extraction_version))->handle(app(MembershipReceiptVision::class));
    }

    private function bank(string $rows = "2026-10-29,10:20,REF-100,Ingreso,100.00,credit\n"): int
    {
        app(MembershipBankImport::class)->import(UploadedFile::fake()->createWithContent('bank.csv', "date,time,reference,description,amount,direction\n".$rows), 'ACCOUNT', $this->admin);

        return DB::table('membership_bank_movements')->latest('id')->value('id');
    }

    public function test_single_file_submission_is_immediate_private_and_queue_is_not_executed_on_request_or_reload(): void
    {
        $p = $this->submit();
        Http::assertNothingSent();
        $this->assertSame('under_review', $p->status);
        $this->assertSame('pending', $p->extraction_status);
        $this->assertNull($p->declared_reference);
        $this->assertNull($p->paid_date);
        $this->assertSame('minio-media', $p->receipt_disk);
        Storage::disk('minio-media')->assertExists($p->receipt_path);
        $this->assertSame('private', Storage::disk('minio-media')->getVisibility($p->receipt_path));
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('membership-receipts', DB::table('jobs')->sole()->queue);
        $this->get('/membresia/checkout')->assertSee('¡Recibimos tu comprobante!');
        $this->get('/membresia/checkout');
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('membership_bank_payments', 1);
        Http::assertNothingSent();
    }

    public function test_exact_extraction_matches_bank_and_explicit_approval_remains_unique(): void
    {
        Http::fake(['*' => Http::response($this->response($this->data()))]);
        $p = $this->submit();
        $this->extractReceipt($p);
        $this->extractReceipt($p->fresh());
        Http::assertSentCount(1);
        $p->refresh();
        $this->assertSame('completed', $p->extraction_status);
        $this->assertSame('REF-100', $p->declared_reference);
        $this->assertNotNull($p->extraction_processed_at);
        $mid = $this->bank();
        $this->assertSame('strong_unique', app(MembershipBankCheckout::class)->match($p)['classification']);
        $this->assertDatabaseCount('memberships', 0);
        app(MembershipBankCheckout::class)->approve($p->id, $mid, $this->admin, true);
        app(MembershipBankCheckout::class)->approve($p->id, $mid, $this->admin, true);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('commercial_outbox', 1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses' && $request['store'] === false && ! str_contains(json_encode($request->data()), $this->buyer->email) && ! str_contains(json_encode($request->data()), $this->buyer->global_subject_id));
    }

    public function test_reference_absent_and_two_similar_bank_credits_stay_ambiguous(): void
    {
        Http::fake(['*' => Http::response($this->response($this->data(['reference' => null])))]);
        $p = $this->submit();
        $this->extractReceipt($p);
        $this->bank("2026-10-29,10:20,A,Ingreso,100.00,credit\n2026-10-29,10:20,B,Ingreso,100.00,credit\n");
        $m = app(MembershipBankCheckout::class)->match($p->fresh());
        $this->assertSame('ambiguous', $m['classification']);
        $this->assertCount(2, $m['candidates']);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_blurry_fields_stay_null_without_filling_expected_amount_or_recipient(): void
    {
        Http::fake(['*' => Http::response($this->response([...array_fill_keys(MembershipReceiptVision::FIELDS, null), 'confidence' => 'low', 'evidence' => []]))]);
        $p = $this->submit();
        $this->extractReceipt($p);
        $p->refresh();
        $this->assertNull($p->extraction_json['amount']);
        $this->assertNull($p->extraction_json['recipient']);
        $this->assertNull($p->declared_reference);
        $this->assertSame('under_review', $p->status);
    }

    public function test_pdf_is_transmitted_inline_without_public_url_or_provider_files_upload(): void
    {
        Http::fake(['*' => Http::response($this->response($this->data()))]);
        $pdf = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF");
        $p = $this->submit($pdf);
        $this->extractReceipt($p);
        Http::assertSent(fn ($r) => $r['input'][0]['content'][0]['type'] === 'input_file' && str_starts_with($r['input'][0]['content'][0]['file_data'], 'data:application/pdf;base64,') && ! isset($r['input'][0]['content'][0]['file_url']));
    }

    public function test_different_amount_currency_recipient_or_low_confidence_never_becomes_strong(): void
    {
        $p = $this->submit();
        $this->bank();
        $vision = app(MembershipReceiptVision::class);
        foreach ([['amount' => '99.00'], ['currency' => 'USD'], ['recipient' => 'Otra Persona'], ['confidence' => 'low'], ['amount' => null]] as $override) {
            $data = $vision->normalize($this->data($override));
            $p->update([...$vision->paymentFields($data), 'extraction_json' => $data, 'extraction_status' => 'completed']);
            $this->assertSame('ambiguous', app(MembershipBankCheckout::class)->match($p->fresh())['classification']);
        }
        $this->actingAs($this->admin)->post('/admin/conciliacion-membresias/lote', ['payments' => [$p->id]])->assertStatus(422);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_missing_key_preserves_receipt_and_manual_correction_can_reconcile(): void
    {
        config(['receipt_ai.api_key' => null]);
        $p = $this->submit();
        $this->extractReceipt($p);
        Http::assertNothingSent();
        $p->refresh();
        $this->assertSame('unconfigured', $p->extraction_status);
        Storage::disk('minio-media')->assertExists($p->receipt_path);
        $this->bank();
        $this->actingAs($this->admin)->post('/admin/conciliacion-membresias/'.$p->id.'/extraccion', $this->data())->assertRedirect();
        $this->assertSame('manual', $p->fresh()->extraction_status);
        $this->assertSame('strong_unique', app(MembershipBankCheckout::class)->match($p->fresh())['classification']);
    }

    public function test_retryable_failure_then_success_preserves_original_and_retries_safely(): void
    {
        Http::fake(['*' => Http::sequence()->push([], 503)->push($this->response($this->data()))]);
        $p = $this->submit();
        try {
            $this->extractReceipt($p);
            $this->fail('Expected retry');
        } catch (\RuntimeException $e) {
            $this->assertSame('receipt_extraction_retry', $e->getMessage());
        }
        $this->assertSame('pending', $p->fresh()->extraction_status);
        $this->assertSame('under_review', $p->fresh()->status);
        Storage::disk('minio-media')->assertExists($p->receipt_path);
        $this->extractReceipt($p->fresh());
        $this->assertSame('completed', $p->fresh()->extraction_status);
        Http::assertSentCount(2);
    }

    public function test_exhausted_retries_allow_manual_review_without_requesting_another_upload(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $p = $this->submit();
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->extractReceipt($p->fresh());
            } catch (\RuntimeException) {
            }
        }
        $p->refresh();
        $this->assertSame('failed', $p->extraction_status);
        $this->assertSame('under_review', $p->status);
        $this->extractReceipt($p);
        Http::assertSentCount(3);
        Storage::disk('minio-media')->assertExists($p->receipt_path);
    }

    public function test_owner_only_receipt_access_and_no_changes_from_wrong_user(): void
    {
        $p = $this->submit();
        $this->get('/membresia/checkout/comprobante/'.$p->id)->assertOk()->assertDownload();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/membresia/checkout/comprobante/'.$p->id)->assertForbidden();
        $this->post('/membresia/checkout/comprobante', ['receipt' => UploadedFile::fake()->image('x.png'), 'payment_id' => $p->id])->assertStatus(422);
        $this->post('/admin/conciliacion-membresias/'.$p->id.'/extraccion', $this->data())->assertForbidden();
        $this->assertSame($this->buyer->id, $p->fresh()->user_id);
    }

    public function test_inflight_result_cannot_overwrite_admin_correction_and_stale_job_cannot_touch_resubmission(): void
    {
        $p = $this->submit();
        $version = $p->extraction_version;
        Http::fake(function () use ($p) {
            $this->actingAs($this->admin)->post('/admin/conciliacion-membresias/'.$p->id.'/extraccion', $this->data(['reference' => 'MANUAL']))->assertRedirect();

            return Http::response($this->response($this->data()));
        });
        $this->extractReceipt($p);
        $this->assertSame('MANUAL', $p->fresh()->declared_reference);
        $this->assertSame('manual', $p->fresh()->extraction_status);
        $p->update(['status' => 'rejected']);
        $this->actingAs($this->buyer)->post('/membresia/checkout/comprobante', ['receipt' => UploadedFile::fake()->image('new.png')])->assertRedirect();
        (new ExtractMembershipReceipt($p->id,$version))->handle(app(MembershipReceiptVision::class));
        Http::assertSentCount(1);
        $this->assertNull($p->fresh()->declared_reference);
    }
}
