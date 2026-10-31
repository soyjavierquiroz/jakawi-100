<?php

namespace Tests\Feature;

use App\Jobs\ExtractMembershipReceipt;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipReceiptVision;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClubDirectPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'purchase-test-credential-separate-123456789';

    private function intent(array $extra = []): array
    {
        return array_replace(['club_reference' => (string) Str::uuid(), 'guest_purchase_id' => (string) Str::uuid(),
            'name' => 'Invitada CLUB', 'email' => 'guest@example.com', 'phone' => '+59170000000', 'amount' => '100.00',
            'currency' => 'BOB', 'origin' => 'CLUB', 'claim_secret' => str_repeat('s', 48)], $extra);
    }

    private function signed(string $method, string $path, ?array $data = null, array $headers = [])
    {
        config(['club_purchases.secret' => $this->secret]);
        $body = $data === null ? '' : json_encode($data, JSON_THROW_ON_ERROR);
        $ts = (string) time();
        $nonce = (string) Str::uuid();
        $canonical = implode("\n", ['jakawi-club-purchases-v1', 'club-purchases-v1', $ts, $nonce, $method, $path, hash('sha256', $body)]);

        return $this->call($method, $path, [], [], [], array_replace([
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_JAKAWI_KEY_ID' => 'club-purchases-v1', 'HTTP_X_JAKAWI_TIMESTAMP' => $ts, 'HTTP_X_JAKAWI_NONCE' => $nonce,
            'HTTP_X_JAKAWI_SIGNATURE' => hash_hmac('sha256', $canonical, $this->secret)], $headers), $body);
    }

    private function create(array $d): MembershipBankPayment
    {
        $this->signed('POST', '/internal/club/purchases', $d)->assertCreated();

        return MembershipBankPayment::where('club_reference', $d['club_reference'])->sole();
    }

    private function receipt(MembershipBankPayment $p, ?string $bytes = null)
    {
        Storage::fake('minio-media');
        $file = UploadedFile::fake()->image('receipt.png');
        $bytes ??= file_get_contents($file->getRealPath());

        return $this->signed('POST', '/internal/club/purchases/'.$p->club_reference.'/receipt', [
            'content_base64' => base64_encode($bytes), 'sha256' => hash('sha256', $bytes)]);
    }

    private function approve(MembershipBankPayment $p): MembershipBankPayment
    {
        Storage::fake('membership-private');
        $admin = User::factory()->create(['is_admin' => true]);
        $csv = UploadedFile::fake()->createWithContent('bank.csv', "date,time,reference,description,amount,direction\n2026-10-31,10:00,REF-".$p->id.",Abono,100.00,credit\n");
        app(MembershipBankImport::class)->import($csv, 'TEST', $admin);
        $mid = DB::table('membership_bank_movements')->where('reference', 'REF-'.$p->id)->value('id');
        $a = app(MembershipBankCheckout::class)->approve($p->id, $mid, $admin);
        app(MembershipBankCheckout::class)->approve($p->id, $mid, $admin);

        return $a;
    }

    private function link(string $club, User $user): void
    {
        $handoff = (string) Str::uuid();
        DB::table('identity_handoffs')->insert(['handoff_id' => $handoff, 'source_app' => 'club', 'club_user_id' => $club,
            'payload_hash' => str_repeat('0', 64), 'status' => 'LINKED', 'result' => '{}', 'expires_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('identity_links')->insert(['handoff_id' => $handoff, 'source_app' => 'club', 'club_user_id' => $club,
            'global_subject_id' => $user->global_subject_id, 'user_id' => $user->id, 'status' => 'LINKED',
            'method' => 'COM_AUTHENTICATED_CONSENT', 'authorized_at' => now()]);
    }

    public function test_guest_intent_is_stable_without_session_verification_or_account(): void
    {
        $d = $this->intent();
        $p = $this->create($d);
        $this->signed('POST', '/internal/club/purchases', $d)->assertOk()->assertJsonPath('payment_reference', $p->reference);
        $this->signed('GET', '/internal/club/purchases/'.$p->club_reference)->assertOk()->assertJsonPath('status', 'awaiting_payment');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('membership_bank_payments', 1);
        $this->signed('POST', '/internal/club/purchases', array_replace($d, ['email' => 'other@example.com']))->assertConflict();
    }

    public function test_authentication_rejects_bad_signature_old_timestamp_and_query(): void
    {
        $this->signed('POST', '/internal/club/purchases', $this->intent(), ['HTTP_X_JAKAWI_SIGNATURE' => str_repeat('0', 64)])->assertUnauthorized();
        $this->signed('POST', '/internal/club/purchases', $this->intent(), ['HTTP_X_JAKAWI_TIMESTAMP' => '1'])->assertUnauthorized();
        $this->signed('GET', '/internal/club/purchases/qr?x=1')->assertUnauthorized();
        $this->assertDatabaseCount('membership_bank_payments', 0);
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $nonce = (string) Str::uuid();
        $p = '/internal/club/purchases/qr';
        $ts = (string) time();
        $sig = hash_hmac('sha256', implode("\n", ['jakawi-club-purchases-v1', 'club-purchases-v1', $ts, $nonce, 'GET', $p, hash('sha256', '')]), $this->secret);
        $h = ['HTTP_X_JAKAWI_NONCE' => $nonce, 'HTTP_X_JAKAWI_TIMESTAMP' => $ts, 'HTTP_X_JAKAWI_SIGNATURE' => $sig];
        $this->signed('GET', $p, null, $h)->assertNotFound();
        $this->signed('GET', $p, null, $h)->assertConflict();
    }

    public function test_guest_private_receipt_approval_and_event_are_idempotent(): void
    {
        $p = $this->create($this->intent());
        $file = UploadedFile::fake()->image('receipt.png');
        $bytes = file_get_contents($file->getRealPath());
        $this->receipt($p, $bytes)->assertCreated();
        $this->signed('POST', '/internal/club/purchases/'.$p->club_reference.'/receipt', [
            'content_base64' => base64_encode($bytes), 'sha256' => hash('sha256', $bytes)])->assertOk();
        $p->refresh();
        Storage::disk('minio-media')->assertExists($p->receipt_path);
        $this->assertDatabaseCount('jobs', 1);
        $p = $this->approve($p);
        $this->assertTrue($p->membership->isActive());
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('commercial_outbox', 1);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertDatabaseCount('conversions', 0);
        $body = json_decode(DB::table('commercial_outbox')->sole()->raw_body, true);
        $this->assertSame($p->club_reference, $body['club_reference']);
        $this->assertNull($body['global_subject_id']);
        $this->assertArrayNotHasKey('buyer_email', $body);
        $this->assertNull($p->user->email_verified_at);
        $this->assertStringEndsWith('@purchases.invalid', $p->user->email);
        $this->assertDatabaseCount('crm_deliveries', 0);
    }

    public function test_email_collision_does_not_link_or_block_payment_and_can_link_after_consent(): void
    {
        $existing = User::factory()->create(['email' => 'guest@example.com']);
        $d = $this->intent();
        $p = $this->create($d);
        $this->receipt($p)->assertCreated();
        $p = $this->approve($p);
        $this->assertNotSame($existing->id, $p->user_id);
        $this->assertFalse($existing->hasActiveMembership());
        $club = (string) Str::uuid();
        $url = '/internal/club/purchases/'.$p->club_reference.'/identity';
        $claim = ['club_user_uuid' => $club, 'claim_secret' => $d['claim_secret']];
        $this->signed('POST', $url, $claim)->assertOk()->assertJsonPath('identity_status', 'pending');
        $this->link($club, $existing);
        $this->signed('POST', $url, $claim)->assertOk()->assertJsonPath('global_subject_id', $existing->global_subject_id);
        $this->signed('POST', $url, $claim)->assertOk();
        $this->assertTrue($existing->hasActiveMembership());
        $this->assertSame($existing->id, $p->fresh()->membership->user_id);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('commercial_outbox', 2);
    }

    public function test_linked_club_unverified_com_account_can_pay(): void
    {
        $u = User::factory()->unverified()->create();
        $club = (string) Str::uuid();
        $this->link($club, $u);
        $p = $this->create($this->intent(['club_user_uuid' => $club]));
        $this->receipt($p)->assertCreated();
        $p = $this->approve($p);
        $this->assertSame($u->id, $p->user_id);
        $this->assertTrue($u->hasActiveMembership());
        $this->assertSame($u->global_subject_id, json_decode(DB::table('commercial_outbox')->sole()->raw_body, true)['global_subject_id']);
    }

    public function test_active_identity_collision_retains_paid_membership_and_receipt(): void
    {
        $u = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $other = app(MembershipService::class)->activate($u, $admin);
        $club = (string) Str::uuid();
        $this->link($club, $u);
        $d = $this->intent(['club_user_uuid' => $club]);
        $p = $this->create($d);
        $this->receipt($p)->assertCreated();
        $p = $this->approve($p);
        $path = $p->receipt_path;
        $this->assertSame('conflict', $p->identity_status);
        $this->assertNotSame($u->id, $p->user_id);
        $claim = ['club_user_uuid' => $club, 'claim_secret' => $d['claim_secret']];
        $this->signed('POST', '/internal/club/purchases/'.$p->club_reference.'/identity', $claim)->assertConflict();
        $this->assertSame($path, $p->fresh()->receipt_path);
        app(MembershipService::class)->cancel($other);
        $this->signed('POST', '/internal/club/purchases/'.$p->club_reference.'/identity', $claim)->assertOk()->assertJsonPath('identity_status', 'linked');
    }

    public function test_wrong_claim_or_reassignment_cannot_steal_guest_purchase(): void
    {
        $d = $this->intent();
        $p = $this->create($d);
        $url = '/internal/club/purchases/'.$p->club_reference.'/identity';
        $club = (string) Str::uuid();
        $this->signed('POST', $url, ['club_user_uuid' => $club, 'claim_secret' => 'wrong'])->assertForbidden();
        $this->signed('POST', $url, ['club_user_uuid' => $club, 'claim_secret' => $d['claim_secret']])->assertOk();
        $this->signed('POST', $url, ['club_user_uuid' => (string) Str::uuid(), 'claim_secret' => $d['claim_secret']])->assertConflict();
    }

    public function test_bad_price_and_receipt_integrity_never_create_activation(): void
    {
        $this->signed('POST', '/internal/club/purchases', $this->intent(['amount' => '99']))->assertUnprocessable();
        $p = $this->create($this->intent());
        $this->signed('POST', '/internal/club/purchases/'.$p->club_reference.'/receipt', [
            'content_base64' => base64_encode('fake'), 'sha256' => str_repeat('0', 64)])->assertUnprocessable();
        $this->receipt($p, 'not an image')->assertUnprocessable();
        $this->assertSame('awaiting_payment', $p->fresh()->status);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_same_administration_renders_club_and_com_and_private_receipt_requires_admin(): void
    {
        $p = $this->create($this->intent());
        $this->receipt($p)->assertCreated();
        $buyer = User::factory()->create();
        app(MembershipBankCheckout::class)->start($buyer);
        $this->actingAs($buyer)->get('/admin/conciliacion-membresias/'.$p->id.'/comprobante')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/conciliacion-membresias')->assertOk()->assertSee($p->club_reference)->assertSee('Origen: COM')->assertSee('Origen: CLUB');
    }

    public function test_mixed_club_com_csv_batch_reuses_one_reconciliation(): void
    {
        $p = $this->create($this->intent());
        $this->receipt($p)->assertCreated();
        $p->update(['declared_reference' => 'CLUB-ONE', 'extraction_status' => 'manual',
            'extraction_json' => ['amount' => '100.00', 'currency' => 'BOB']]);
        $u = User::factory()->create();
        $com = app(MembershipBankCheckout::class)->start($u);
        $com->update(['status' => 'under_review', 'declared_reference' => 'COM-ONE']);
        $admin = User::factory()->create(['is_admin' => true]);
        Storage::fake('membership-private');
        app(MembershipBankImport::class)->import(UploadedFile::fake()->createWithContent('batch.csv',
            "date,time,reference,description,amount,direction\n2026-10-31,10:00,CLUB-ONE,Ingreso,100.00,credit\n2026-10-31,10:01,COM-ONE,Ingreso,100.00,credit\n"), 'TEST', $admin);
        $this->actingAs($admin)->post('/admin/conciliacion-membresias/lote', ['payments' => [$p->id, $com->id]])->assertRedirect();
        $this->assertSame('approved', $p->fresh()->status);
        $this->assertSame('approved', $com->fresh()->status);
        $this->assertDatabaseCount('memberships', 2);
        $this->assertDatabaseCount('commercial_outbox', 2);
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_rejected_receipt_can_be_replaced_but_reviewed_receipt_is_locked(): void
    {
        $p = $this->create($this->intent());
        $this->receipt($p)->assertCreated();
        $p->refresh();
        $old = $p->receipt_path;
        $file = UploadedFile::fake()->image('new.jpg', 20, 20);
        $bytes = file_get_contents($file->getRealPath());
        $d = ['content_base64' => base64_encode($bytes), 'sha256' => hash('sha256', $bytes)];
        $url = '/internal/club/purchases/'.$p->club_reference.'/receipt';
        $this->signed('POST', $url, $d)->assertConflict();
        $p->update(['status' => 'rejected', 'review_note' => 'Archivo ilegible']);
        $this->signed('POST', $url, $d)->assertCreated();
        Storage::disk('minio-media')->assertExists($old);
        Storage::disk('minio-media')->assertExists($p->fresh()->receipt_path);
        $this->assertNotSame($old, $p->fresh()->receipt_path);
        $this->assertNull($p->fresh()->review_note);
        $this->assertDatabaseCount('membership_bank_payments', 1);
    }

    public function test_approval_failure_rolls_back_custody_without_losing_receipt_and_missing_ai_remains_reviewable(): void
    {
        $p = $this->create($this->intent());
        $this->receipt($p)->assertCreated();
        $p->refresh();
        config(['receipt_ai.api_key' => '']);
        (new ExtractMembershipReceipt($p->id, $p->extraction_version))->handle(app(MembershipReceiptVision::class));
        $this->assertSame('unconfigured', $p->fresh()->extraction_status);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/conciliacion-membresias/aprobar', [
            'payment_id' => $p->id, 'movement_id' => 999999])->assertStatus(422);
        $this->assertNull($p->fresh()->user_id);
        $this->assertNull($p->fresh()->custody_user_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('commercial_outbox', 0);
        Storage::disk('minio-media')->assertExists($p->fresh()->receipt_path);
    }

    public function test_existing_open_com_checkout_is_preserved_while_club_purchase_activates_and_links(): void
    {
        $u = User::factory()->create();
        $com = app(MembershipBankCheckout::class)->start($u);
        $club = (string) Str::uuid();
        $this->link($club, $u);
        $p = $this->create($this->intent(['club_user_uuid' => $club]));
        $this->receipt($p)->assertCreated();
        $p = $this->approve($p);
        $this->assertSame($u->id, $p->user_id);
        $this->assertSame('linked', $p->identity_status);
        $this->assertTrue($u->hasActiveMembership());
        $this->assertSame('awaiting_payment', $com->fresh()->status);
        $this->assertDatabaseCount('membership_bank_payments', 2);
        $this->assertDatabaseCount('memberships', 1);
    }
}
