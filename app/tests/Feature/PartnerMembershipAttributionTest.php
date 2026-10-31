<?php

namespace Tests\Feature;

use App\Models\MembershipBankPayment;
use App\Models\Partner;
use App\Models\ReferralRelationship;
use App\Models\RewardRule;
use App\Models\User;
use App\Services\ClubPurchaseIdentity;
use App\Services\Commercial\ComOriginProof;
use App\Services\Commercial\MembershipAcquisition;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipPurchaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnerMembershipAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commercial.secret' => str_repeat('a', 64), 'club_purchases.secret' => str_repeat('p', 40)]);
        Http::preventStrayRequests();
    }

    public function test_direct_profile_visit_is_only_a_candidate_then_actual_registration_fixes_partner_and_emits_one_lead(): void
    {
        $partner = $this->partner();
        $this->get('/dossis43')->assertOk();
        $this->assertDatabaseCount('membership_acquisition_origins', 0);
        $this->assertDatabaseCount('referral_relationships', 0);
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->assertDatabaseCount('attribution_touches', 0);
        $user = $this->signup();
        $origin = app(MembershipAcquisition::class)->forUser($user);
        $this->assertSame('partner', $origin['kind']);
        $this->assertSame($partner->id, $origin['partner_id']);
        $this->assertSame('jakawi.com', $origin['source']);
        $event = $this->event('member.registered');
        $this->assertSame($user->global_subject_id, $event['global_subject_id']);
        $this->assertEquals($origin, $event['acquisition']);
        app(MembershipAcquisition::class)->lead($user, $origin);
        $this->assertDatabaseCount('commercial_outbox', 1);
        $raw = DB::table('commercial_outbox')->value('raw_body');
        $this->assertStringNotContainsString($user->email, $raw);
        $this->assertStringNotContainsString('password', $raw);
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_explicit_club_code_before_registration_beats_profile_and_keeps_fallback_for_club_validity_resolution(): void
    {
        $partner = $this->partner();
        $this->get('/dossis43');
        $this->get('/dossis43?club_ref=AFFILIATE123');
        $user = $this->signup();
        $origin = app(MembershipAcquisition::class)->forUser($user);
        $this->assertSame('club_referral', $origin['kind']);
        $this->assertSame('AFFILIATE123', $origin['club_referral_code']);
        $this->assertSame($partner->id, $origin['fallback_partner_id']);
        $this->assertSame('CLUB', $origin['beneficiary_resolution']);
        $this->assertDatabaseCount('referral_relationships', 0);
    }

    public function test_malformed_club_code_does_not_override_partner_or_consume_another_explicit_code(): void
    {
        $this->partner();
        $this->get('/dossis43?club_ref=invalid');
        $user = $this->signup();
        $this->assertSame('partner', app(MembershipAcquisition::class)->forUser($user)['kind']);
    }

    public function test_current_profile_is_only_a_candidate_until_registration_then_original_cannot_change(): void
    {
        $a = $this->partner();
        $b = Partner::factory()->published()->create(['slug' => 'other-partner']);
        $this->get('/dossis43');
        $this->get('/other-partner');
        $user = $this->signup();
        $origin = app(MembershipAcquisition::class)->forUser($user);
        $this->assertSame($b->id, $origin['partner_id']);
        $this->actingAs($user)->get('/dossis43?club_ref=AFFILIATE123');
        $payment = app(MembershipBankCheckout::class)->start($user);
        $this->assertSame($origin['attribution_id'], $payment->acquisition_origin_id);
        $this->assertSame($b->id, $this->event('membership.attribution_fixed')['acquisition']['partner_id']);
        $this->assertDatabaseCount('membership_acquisition_origins', 1);
    }

    public function test_established_legacy_referral_even_expired_wins_and_login_does_not_emit_lead(): void
    {
        $profile = $this->partner();
        $original = Partner::factory()->published()->create();
        $user = User::factory()->create();
        $relationship = $this->legacy($user, $original);
        $relationship->update(['expires_at' => now()->subDay(), 'status' => 'expired']);
        $this->get('/dossis43?club_ref=AFFILIATE123');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->get('/membresia/checkout')->assertOk();
        $origin = app(MembershipAcquisition::class)->forUser($user);
        $this->assertSame($original->id, $origin['partner_id']);
        $this->assertSame((string) $relationship->id, $origin['com_referral_id']);
        $this->assertSame($original->id, $relationship->fresh()->acquisition_partner_id);
        $this->assertNotSame($profile->id, $origin['partner_id']);
        $this->assertSame('membership.attribution_fixed', $this->event('membership.attribution_fixed')['event_type']);
    }

    public function test_com_checkout_uses_first_intent_and_cannot_be_overwritten_by_later_query_or_raw_partner_id(): void
    {
        $partner = $this->partner();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dossis43');
        $this->get('/membresia/checkout?partner_id=9999')->assertOk();
        $payment = MembershipBankPayment::sole();
        $this->get('/dossis43?club_ref=AFFILIATE123');
        $this->get('/membresia/checkout')->assertOk();
        $this->assertDatabaseCount('membership_bank_payments', 1);
        $this->assertDatabaseCount('commercial_outbox', 1);
        $this->assertSame($partner->id, $this->event('membership.attribution_fixed')['acquisition']['partner_id']);
        $this->assertSame($payment->acquisition_origin_id, $payment->fresh()->acquisition_origin_id);
    }

    public function test_com_to_club_proof_is_signed_by_resolved_partner_not_numeric_browser_parameter_and_does_not_fix_on_click(): void
    {
        $partner = $this->partner();
        $response = $this->get('/p/dossis43/club/checkout?partner_id=9999');
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://jakawi.club/activar?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $candidate = app(ComOriginProof::class)->read($query['com_origin']);
        $this->assertSame($partner->id, $candidate['partner_id']);
        $this->assertArrayNotHasKey('attribution_id', $candidate);
        $this->assertDatabaseCount('membership_acquisition_origins', 0);
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->get('/p/dossis43/club/carta')->assertRedirectContains('https://jakawi.club/membresia?com_origin=');
    }

    public function test_guest_club_intent_freezes_signed_origin_and_duplicate_preserves_it_after_token_expiration(): void
    {
        $partner = $this->partner();
        $token = app(ComOriginProof::class)->issue(['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $partner->id]);
        $intent = $this->intent(['com_origin' => $token]);
        $this->signed('/internal/club/purchases', $intent)->assertCreated()->assertJsonPath('acquisition.partner_id', $partner->id);
        $payment = MembershipBankPayment::sole();
        $this->travel(31)->days();
        $this->signed('/internal/club/purchases', $intent)->assertOk();
        $this->assertDatabaseCount('membership_acquisition_origins', 1);
        $this->assertDatabaseCount('commercial_outbox', 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame($payment->reference, $this->event('membership.attribution_fixed')['payment_reference']);
    }

    public function test_tampered_or_expired_proof_is_rejected_without_creating_purchase_or_origin(): void
    {
        $this->partner();
        $token = app(ComOriginProof::class)->issue(['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => 1]);
        $this->signed('/internal/club/purchases', $this->intent(['com_origin' => $token.'x']))->assertUnprocessable();
        $this->travel(31)->days();
        $this->signed('/internal/club/purchases', $this->intent(['com_origin' => $token]))->assertUnprocessable();
        $this->assertDatabaseCount('membership_bank_payments', 0);
        $this->assertDatabaseCount('membership_acquisition_origins', 0);
    }

    public function test_existing_authorized_buyer_original_wins_over_signed_club_purchase_context(): void
    {
        $a = $this->partner();
        $b = Partner::factory()->published()->create();
        $user = User::factory()->create();
        $this->legacy($user, $a);
        $club = (string) Str::uuid();
        $this->link($club, $user);
        $token = app(ComOriginProof::class)->issue(['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $b->id]);
        $this->signed('/internal/club/purchases', $this->intent(['com_origin' => $token, 'club_user_uuid' => $club]))
            ->assertCreated()->assertJsonPath('acquisition.partner_id', $a->id);
    }

    public function test_guest_later_identity_link_preserves_purchase_snapshot_and_an_existing_user_original(): void
    {
        $a = $this->partner();
        $b = Partner::factory()->published()->create();
        $user = User::factory()->create();
        $this->legacy($user, $a);
        $token = app(ComOriginProof::class)->issue(['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $b->id]);
        $intent = $this->intent(['com_origin' => $token]);
        $this->signed('/internal/club/purchases', $intent)->assertCreated();
        $payment = MembershipBankPayment::sole();
        $purchaseOrigin = $payment->acquisition_origin_id;
        $club = (string) Str::uuid();
        $this->link($club, $user);
        $this->signed('/internal/club/purchases/'.$payment->club_reference.'/identity', ['club_user_uuid' => $club, 'claim_secret' => $intent['claim_secret']])->assertOk();
        app(ClubPurchaseIdentity::class)->attach($payment->fresh());
        $event = $this->event('membership.attribution_identity_linked');
        $this->assertSame($a->id, $event['acquisition']['partner_id']);
        $this->assertSame($b->id, $event['purchase_acquisition']['partner_id']);
        $this->assertSame($purchaseOrigin, $payment->fresh()->acquisition_origin_id);
        $this->assertSame($user->global_subject_id, $event['global_subject_id']);
        $this->assertSame(1, DB::table('commercial_outbox')->where('event_id', 'like', '%:attribution-identity-linked')->count());
    }

    public function test_partner_membership_attribution_never_reactivates_com_cash_commission(): void
    {
        $partner = $this->partner();
        $this->get('/dossis43');
        $user = $this->signup();
        RewardRule::create(['name' => 'Legacy', 'participant_type' => 'PARTNER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 10, 'currency' => 'BOB', 'status' => 'active']);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($user, User::factory()->create(['is_admin' => true]), null, 'QA-10B', (string) Str::uuid());
        $this->assertSame($partner->id, $sale->conversion->attribution_snapshot['membership_acquisition']['partner_id']);
        $this->assertTrue($user->fresh()->hasActiveMembership());
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_resolve_requires_backend_authentication_and_does_not_fix_browsing_context(): void
    {
        $partner = $this->partner();
        $token = app(ComOriginProof::class)->issue(['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $partner->id]);
        $this->postJson('/internal/club/origins/resolve', ['com_origin' => $token])->assertUnauthorized();
        $this->signed('/internal/club/origins/resolve', ['com_origin' => $token])->assertOk()
            ->assertJsonPath('source', 'jakawi.com')->assertJsonPath('acquisition.partner_id', $partner->id);
        $this->assertDatabaseCount('membership_acquisition_origins', 0);
        $this->assertDatabaseCount('commercial_outbox', 0);
    }

    public function test_backend_established_club_referrer_wins_over_unfixed_partner_proof_and_raw_ids_are_not_proof(): void
    {
        $partner = $this->partner();
        $token = app(ComOriginProof::class)->issue(['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $partner->id]);
        $this->signed('/internal/club/purchases', $this->intent(['com_origin' => $token, 'original_club_referral_code' => 'AFFILIATE123']))
            ->assertCreated()->assertJsonPath('acquisition.kind', 'club_referral')->assertJsonPath('acquisition.club_referral_code', 'AFFILIATE123');
        $this->signed('/internal/club/purchases', $this->intent(['partner_id' => $partner->id]))
            ->assertCreated()->assertJsonPath('acquisition.kind', 'none');
    }

    public function test_database_prevents_replacing_frozen_origin_and_purchase_pointer(): void
    {
        $this->partner();
        $this->get('/dossis43');
        $user = $this->signup();
        $origin = app(MembershipAcquisition::class)->forUser($user);
        $payment = app(MembershipBankCheckout::class)->start($user);
        foreach ([fn () => DB::table('membership_acquisition_origins')->where('id', $origin['attribution_id'])->update(['attribution' => '{}']),
            fn () => $payment->update(['acquisition_origin_id' => null])] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Frozen origin was replaced.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('immutable', $e->getMessage());
            }
        }
        $this->assertEquals($origin, app(MembershipAcquisition::class)->forUser($user));
        $this->assertSame($origin['attribution_id'], $payment->fresh()->acquisition_origin_id);
    }

    public function test_prefetch_and_invalid_registration_do_not_fix_or_emit_a_lead(): void
    {
        $this->partner();
        $this->withHeader('Purpose', 'prefetch')->get('/dossis43?club_ref=AFFILIATE123');
        $this->post('/register', ['name' => 'Invalid', 'email' => 'invalid', 'whatsapp' => '71234567'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('membership_acquisition_origins', 0);
        $this->assertDatabaseCount('commercial_outbox', 0);
    }

    public function test_guest_activation_and_later_identity_keep_existing_v1_events_and_frozen_purchase_origin(): void
    {
        $partner = $this->partner();
        $token = app(ComOriginProof::class)->issue(['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $partner->id]);
        $intent = $this->intent(['com_origin' => $token]);
        $this->signed('/internal/club/purchases', $intent)->assertCreated();
        $payment = MembershipBankPayment::sole();
        $originId = $payment->acquisition_origin_id;
        $admin = User::factory()->create(['is_admin' => true]);
        Storage::fake('membership-private');
        $csv = UploadedFile::fake()->createWithContent('bank.csv', "date,time,reference,description,amount,direction\n2026-10-31,10:00,PHASE10B,Abono,100.00,credit\n");
        app(MembershipBankImport::class)->import($csv, 'PHASE10B', $admin);
        $movement = DB::table('membership_bank_movements')->where('reference', 'PHASE10B')->value('id');
        $payment->update(['status' => 'under_review']);
        app(MembershipBankCheckout::class)->approve($payment->id, $movement, $admin);
        app(MembershipBankCheckout::class)->approve($payment->id, $movement, $admin);
        $activation = $this->event('membership.activated');
        $this->assertNull($activation['global_subject_id']);
        $this->assertSame($payment->reference, $activation['payment_reference']);
        $this->assertArrayNotHasKey('acquisition', $activation);
        $this->assertSame($originId, $payment->fresh()->acquisition_origin_id);
        $user = User::factory()->create();
        $club = (string) Str::uuid();
        $this->link($club, $user);
        $link = ['club_user_uuid' => $club, 'claim_secret' => $intent['claim_secret']];
        $this->signed('/internal/club/purchases/'.$payment->club_reference.'/identity', $link)->assertOk();
        $this->signed('/internal/club/purchases/'.$payment->club_reference.'/identity', $link)->assertOk();
        $identity = $this->event('membership.identity_linked');
        $this->assertSame($user->global_subject_id, $identity['global_subject_id']);
        $this->assertArrayNotHasKey('acquisition', $identity);
        $this->assertSame($partner->id, $this->event('membership.attribution_identity_linked')['acquisition']['partner_id']);
        $this->assertSame($originId, $payment->fresh()->acquisition_origin_id);
        $this->assertSame(1, DB::table('commercial_outbox')->where('event_id', $activation['event_id'])->count());
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_checkout_cta_resolves_its_partner_after_navigation_in_another_tab(): void
    {
        $a = $this->partner(); $b = Partner::factory()->published()->create(['slug' => 'other-partner']);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dossis43');
        $this->get('/other-partner');
        $this->get('/p/dossis43/membership')->assertRedirect('/membresia/checkout');
        $this->get('/membresia/checkout')->assertOk();
        $this->assertSame($a->id, app(MembershipAcquisition::class)->forUser($user)['partner_id']);
        $this->assertNotSame($b->id, app(MembershipAcquisition::class)->forUser($user)['partner_id']);
    }

    private function partner(): Partner
    {
        return Partner::factory()->published()->create(['slug' => 'dossis43', 'name' => 'Dossis 43 QA']);
    }

    private function signup(): User
    {
        $this->post('/register', ['name' => 'Nuevo QA', 'email' => 'new-'.Str::random(5).'@example.test', 'whatsapp' => '71234567'])->assertRedirect();

        return User::latest('id')->firstOrFail();
    }

    private function event(string $type): array
    {
        return DB::table('commercial_outbox')->get()->map(fn ($r) => json_decode($r->raw_body, true))->firstOrFail(fn ($e) => $e['event_type'] === $type);
    }

    private function legacy(User $user, Partner $partner): ReferralRelationship
    {
        return ReferralRelationship::create(['acquisition_partner_id' => $partner->id, 'referred_user_id' => $user->id, 'referral_code' => 'LEGACY', 'attributed_at' => now()->subDays(40), 'status' => 'active']);
    }

    private function intent(array $extra = []): array
    {
        return array_replace(['club_reference' => (string) Str::uuid(), 'guest_purchase_id' => (string) Str::uuid(), 'name' => 'Invitada QA', 'email' => 'guest@example.test', 'phone' => '+59170000000', 'amount' => '100.00', 'currency' => 'BOB', 'origin' => 'CLUB', 'claim_secret' => str_repeat('s', 48)], $extra);
    }

    private function signed(string $path, array $data)
    {
        $raw = json_encode($data, JSON_THROW_ON_ERROR);
        $ts = (string) time();
        $nonce = (string) Str::uuid();
        $canonical = implode("\n", ['jakawi-club-purchases-v1', 'club-purchases-v1', $ts, $nonce, 'POST', $path, hash('sha256', $raw)]);

        return $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_JAKAWI_KEY_ID' => 'club-purchases-v1', 'HTTP_X_JAKAWI_TIMESTAMP' => $ts, 'HTTP_X_JAKAWI_NONCE' => $nonce, 'HTTP_X_JAKAWI_SIGNATURE' => hash_hmac('sha256', $canonical, str_repeat('p', 40))], $raw);
    }

    private function link(string $club, User $user): void
    {
        $handoff = (string) Str::uuid();
        DB::table('identity_handoffs')->insert(['handoff_id' => $handoff, 'source_app' => 'club', 'club_user_id' => $club, 'payload_hash' => str_repeat('0', 64), 'status' => 'LINKED', 'result' => '{}', 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('identity_links')->insert(['handoff_id' => $handoff, 'source_app' => 'club', 'club_user_id' => $club, 'global_subject_id' => $user->global_subject_id, 'user_id' => $user->id, 'status' => 'LINKED', 'method' => 'COM_AUTHENTICATED_CONSENT', 'authorized_at' => now()]);
    }
}
