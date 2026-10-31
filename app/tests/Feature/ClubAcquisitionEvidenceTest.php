<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\Commercial\MembershipEvidence;
use App\Services\MembershipService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClubAcquisitionEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/internal/club/acquisitions/evidence';

    private function instant(): CarbonImmutable
    {
        return CarbonImmutable::parse(DB::selectOne('SELECT clock_timestamp() AS at')->at);
    }

    private function membership(User $user, ?CarbonImmutable $start = null, ?CarbonImmutable $end = null, string $status = 'active', string $amount = '100'): Membership
    {
        if ($start === null) {
            // Production activation columns have second precision. Use distinct real seconds
            // so cancellations and subsequent purchases have an unambiguous ordering.
            $instant = $this->instant();
            DB::select('SELECT pg_sleep(?)', [$instant->diffInSeconds($instant->addSecond()->startOfSecond()) + 0.01]);
            $start = $this->instant()->startOfSecond();
        }
        $start = $start->startOfSecond();
        $end = $end?->startOfSecond();
        $id = DB::table('memberships')->insertGetId([
            'user_id' => $user->id, 'status' => $status, 'starts_at' => $start->format('Y-m-d H:i:s.uP'),
            'ends_at' => ($end ?? $start->addYear())->format('Y-m-d H:i:s.uP'), 'amount_paid' => $amount,
            'payment_method' => 'bank_qr', 'payment_reference' => (string) Str::uuid(),
            'activated_by' => $user->id, 'created_at' => $start, 'updated_at' => $start,
        ]);

        return Membership::findOrFail($id);
    }

    private function purchase(User $user, ?Membership $membership = null, array $extra = []): MembershipBankPayment
    {
        $membership ??= $this->membership($user);
        $id = DB::table('membership_bank_payments')->insertGetId(array_replace([
            'user_id' => $user->id, 'origin' => 'COM', 'reference' => $membership->payment_reference,
            'status' => 'approved', 'amount' => '100.00', 'membership_id' => $membership->id,
            'activated_at' => $membership->getRawOriginal('starts_at'), 'checkout_at' => now(),
            'checkout_event_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ], $extra));

        return MembershipBankPayment::findOrFail($id);
    }

    private function evidence(MembershipBankPayment $p, ?User $affiliate = null): array
    {
        return app(MembershipEvidence::class)->resolve($p->reference, $affiliate?->global_subject_id);
    }

    private function signed(array $data, array $headers = [], ?string $nonce = null, ?string $signaturePath = null, ?int $timestamp = null)
    {
        config(['club_purchases.secret' => str_repeat('e', 64)]);
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $ts = (string) ($timestamp ?? time());
        $nonce ??= (string) Str::uuid();
        $canonical = implode("\n", ['jakawi-club-purchases-v1', 'club-purchases-v1', $ts, $nonce, 'POST', $signaturePath ?? self::PATH, hash('sha256', $body)]);

        return $this->call('POST', self::PATH, [], [], [], array_replace([
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_JAKAWI_KEY_ID' => 'club-purchases-v1', 'HTTP_X_JAKAWI_TIMESTAMP' => $ts,
            'HTTP_X_JAKAWI_NONCE' => $nonce, 'HTTP_X_JAKAWI_SIGNATURE' => hash_hmac('sha256', $canonical, config('club_purchases.secret')),
        ], $headers), $body);
    }

    public function test_first_paid_purchase_is_certified_from_complete_com_history_without_domain_writes(): void
    {
        $buyer = User::factory()->create();
        $p = $this->purchase($buyer);
        $before = DB::table('membership_evidence_journal')->count();
        $this->signed(['purchase_reference' => $p->reference])->assertOk()
            ->assertJsonPath('contract', MembershipEvidence::CONTRACT)->assertJsonPath('first_paid_acquisition', true)
            ->assertJsonPath('global_subject_id', $buyer->global_subject_id)->assertJsonPath('membership_reference', (string) $p->membership_id)
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('Set-Cookie');
        $this->assertSame($before, DB::table('membership_evidence_journal')->count());
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_renewal_reactivation_and_previous_historical_memberships_are_not_first(): void
    {
        foreach (['active', 'cancelled'] as $status) {
            $buyer = User::factory()->create();
            $this->membership($buyer, $this->instant()->subYears(2), $this->instant()->subYear(), $status);
            $this->assertFalse($this->evidence($this->purchase($buyer))['first_paid_acquisition']);
        }
    }

    public function test_manual_purchase_and_orphaned_historical_purchase_prevent_certification(): void
    {
        $buyer = User::factory()->create();
        DB::table('membership_purchases')->insert([
            'reference' => 'MS-HISTORICAL', 'idempotency_key' => Str::uuid(), 'beneficiary_user_id' => $buyer->id,
            'recorded_by_user_id' => $buyer->id, 'payment_channel' => 'manual_cash', 'status' => 'refunded',
            'amount' => 100, 'currency' => 'BOB', 'duration_days' => 365, 'product_key' => 'jakawi_annual',
            'manual_reference' => 'HISTORICAL', 'paid_at' => now()->subYear(), 'created_at' => now()->subYear(), 'updated_at' => now(),
        ]);
        $this->assertSame('PRIOR_PAID_PURCHASE', $this->evidence($this->purchase($buyer))['reason']);
    }

    public function test_guest_custody_is_unknown_then_authorized_late_link_uses_original_activation(): void
    {
        $custody = User::factory()->create();
        $buyer = User::factory()->create();
        $club = (string) Str::uuid();
        $p = $this->purchase($custody, null, ['origin' => 'CLUB', 'club_reference' => Str::uuid(), 'guest_purchase_id' => Str::uuid(),
            'custody_user_id' => $custody->id, 'club_user_uuid' => $club, 'identity_status' => 'pending']);
        $initial = $this->evidence($p);
        $this->assertSame('unknown', $initial['first_paid_acquisition']);
        $this->assertNull($initial['global_subject_id']);
        $handoff = (string) Str::uuid();
        DB::table('identity_handoffs')->insert(['handoff_id' => $handoff, 'source_app' => 'club', 'club_user_id' => $club,
            'payload_hash' => str_repeat('0', 64), 'status' => 'LINKED', 'result' => '{}', 'expires_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('identity_links')->insert(['handoff_id' => $handoff, 'source_app' => 'club', 'club_user_id' => $club,
            'global_subject_id' => $buyer->global_subject_id, 'user_id' => $buyer->id, 'status' => 'LINKED',
            'method' => 'COM_AUTHENTICATED_CONSENT', 'authorized_at' => now()]);
        $otherPurchase = $this->purchase(User::factory()->create());
        $this->assertSame('UNKNOWN', $this->evidence($otherPurchase, $buyer)['affiliate']['state']);
        // Same operations as the authorized claim; no second membership or activation.
        $p->membership->update(['user_id' => $buyer->id]);
        $p->update(['user_id' => $buyer->id, 'identity_status' => 'linked']);
        $linked = $this->evidence($p->fresh());
        $this->assertTrue($linked['first_paid_acquisition']);
        $this->assertSame('PAID', $this->evidence($otherPurchase, $buyer)['affiliate']['state']);
        $this->assertSame($initial['first_activated_at'], $linked['first_activated_at']);
        $this->membership($buyer, $this->instant()->subYear(), $this->instant()->subMonth(), 'cancelled');
        $this->assertFalse($this->evidence($p)['first_paid_acquisition']);
        DB::table('identity_links')->where('club_user_id', $club)->update(['status' => 'REVOKED', 'revoked_at' => now()]);
        $this->assertSame('BUYER_LINK_UNAUTHORIZED', $this->evidence($p)['reason']);
    }

    public function test_authoritative_free_paid_future_upgrade_and_expiry_at_original_instant(): void
    {
        $free = User::factory()->create();
        $paid = User::factory()->create();
        $expired = User::factory()->create();
        $this->membership($paid);
        $this->membership($expired, $this->instant()->subYear(), $this->instant()->subSecond());
        $buyer = User::factory()->create();
        $p = $this->purchase($buyer);
        $this->assertSame('FREE', $this->evidence($p, $free)['affiliate']['state']);
        $this->assertSame('PAID', $this->evidence($p, $paid)['affiliate']['state']);
        $this->assertSame('FREE', $this->evidence($p, $expired)['affiliate']['state']);
        $this->membership($free, $this->instant()->addHour());
        $historical = $this->evidence($p, $free);
        $this->assertSame('FREE', $historical['affiliate']['state']);
        $this->assertSame($historical, $this->evidence($p, $free));
    }

    public function test_historical_paid_survives_later_cancellation_and_deletion(): void
    {
        $affiliate = User::factory()->create();
        $membership = $this->membership($affiliate);
        $p = $this->purchase(User::factory()->create());
        $before = $this->evidence($p, $affiliate);
        $this->assertSame('PAID', $before['affiliate']['state']);
        app(MembershipService::class)->cancel($membership);
        $this->assertSame('PAID', $this->evidence($p, $affiliate)['affiliate']['state']);
        $membership->delete();
        $this->assertSame('PAID', $this->evidence($p, $affiliate)['affiliate']['state']);
        $later = $this->purchase(User::factory()->create());
        $this->assertSame('FREE', $this->evidence($later, $affiliate)['affiliate']['state']);
    }

    public function test_unknown_identity_unobserved_history_conflict_and_unconfirmed_purchase(): void
    {
        $buyer = User::factory()->create();
        $old = $this->membership($buyer, $this->instant()->subYears(2));
        $p = $this->purchase($buyer, $old);
        $r = app(MembershipEvidence::class)->resolve($p->reference, (string) Str::uuid());
        $this->assertSame('unknown', $r['first_paid_acquisition']);
        $this->assertSame('UNKNOWN', $r['affiliate']['state']);
        $p->update(['status' => 'under_review']);
        $this->assertSame('PURCHASE_OR_ACTIVATION_UNCERTIFIED', $this->evidence($p)['reason']);
        $p->update(['status' => 'approved']);
        $old->update(['starts_at' => now()]);
        $this->assertSame('PURCHASE_OR_ACTIVATION_UNCERTIFIED', $this->evidence($p)['reason']);
        $this->signed(['purchase_reference' => 'missing'])->assertOk()->assertJsonPath('reason', 'PURCHASE_NOT_FOUND');
        $current = $this->purchase(User::factory()->create());
        $r = app(MembershipEvidence::class)->resolve($current->reference, (string) Str::uuid());
        $this->assertSame('IDENTITY_UNKNOWN', $r['affiliate']['reason']);
        $historical = $this->purchase(User::factory()->create(), $this->membership(User::factory()->create(), $this->instant()->subYears(3)));
        $this->assertSame('HISTORICAL_STATE_NOT_OBSERVED', $this->evidence($historical, $buyer)['affiliate']['reason']);
    }

    public function test_existing_identity_baseline_never_implies_first_and_prior_purchase_still_disproves_it(): void
    {
        DB::statement('ALTER TABLE users DISABLE TRIGGER users_membership_evidence');
        try {
            $user = User::factory()->create();
        } finally {
            DB::statement('ALTER TABLE users ENABLE TRIGGER users_membership_evidence');
        }
        DB::statement("INSERT INTO membership_evidence_journal(entity,row_id,operation,fact) SELECT 'users',id,'BASELINE',jakawi_membership_evidence_fact('users',to_jsonb(u)) FROM users u WHERE id = ?", [$user->id]);
        $p = $this->purchase($user);
        $this->assertSame('HISTORY_BEFORE_OBSERVATION_BOUNDARY', $this->evidence($p)['reason']);
        $this->membership($user, $this->instant()->subYear(), $this->instant()->subMonth(), 'cancelled');
        $this->assertFalse($this->evidence($p)['first_paid_acquisition']);
    }

    public function test_manual_reference_is_supported_and_backdated_late_fact_is_uncertain(): void
    {
        $buyer = User::factory()->create();
        $m = $this->membership($buyer);
        DB::table('membership_purchases')->insert([
            'reference' => $m->payment_reference, 'idempotency_key' => Str::uuid(), 'beneficiary_user_id' => $buyer->id,
            'recorded_by_user_id' => $buyer->id, 'payment_channel' => 'manual_cash', 'status' => 'confirmed',
            'amount' => 100, 'currency' => 'BOB', 'duration_days' => 365, 'product_key' => 'jakawi_annual',
            'manual_reference' => 'TEST-ONLY', 'membership_id' => $m->id, 'paid_at' => $m->starts_at,
            'processed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $r = app(MembershipEvidence::class)->resolve($m->payment_reference, null);
        $this->assertTrue($r['first_paid_acquisition']);
        $this->assertSame('membership_purchases', $r['purchase_source']);
        $affiliate = User::factory()->create();
        $p = $this->purchase(User::factory()->create());
        DB::select('SELECT pg_sleep(1.1)');
        $this->membership($affiliate, $this->instant()->subHour());
        $this->assertSame('UNKNOWN', $this->evidence($p, $affiliate)['affiliate']['state']);
    }

    public function test_journal_is_append_only_contains_no_email_or_credentials_and_covers_raw_sql_changes(): void
    {
        $u = User::factory()->create();
        $m = $this->membership($u);
        DB::table('memberships')->where('id', $m->id)->update(['status' => 'cancelled']);
        $this->assertSame(2, DB::table('membership_evidence_journal')->where('entity', 'memberships')->where('row_id', $m->id)->count());
        $json = json_encode(DB::table('membership_evidence_journal')->get());
        $this->assertStringNotContainsString($u->email, $json);
        $this->assertStringNotContainsString('password', $json);
        foreach (['UPDATE membership_evidence_journal SET operation = operation', 'DELETE FROM membership_evidence_journal', 'TRUNCATE membership_evidence_journal'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail('Evidence mutation was accepted');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
    }

    public function test_hmac_binds_endpoint_body_key_timestamp_and_nonce_and_browser_cannot_query(): void
    {
        $data = ['purchase_reference' => 'missing'];
        $this->signed($data, ['HTTP_X_JAKAWI_SIGNATURE' => str_repeat('0', 64)])->assertUnauthorized();
        $this->signed($data, [], null, '/internal/club/partners/access')->assertUnauthorized();
        $this->signed($data, [], null, null, time() - 301)->assertUnauthorized();
        $this->signed($data, ['HTTP_X_JAKAWI_KEY_ID' => 'other'])->assertUnauthorized();
        $nonce = (string) Str::uuid();
        $this->signed($data, [], $nonce)->assertOk();
        $this->signed($data, [], $nonce)->assertConflict();
        $this->actingAs(User::factory()->create())->postJson(self::PATH, $data)->assertUnauthorized();
        $this->signed([])->assertUnprocessable();
        $this->signed(['purchase_reference' => 'x', 'affiliate_global_subject_id' => 'email@example.com'])->assertUnprocessable();
        $this->signed($data, ['CONTENT_TYPE' => 'text/plain'])->assertUnprocessable();
        $this->postJson(self::PATH.'?x=1', $data)->assertUnauthorized();
    }
}
