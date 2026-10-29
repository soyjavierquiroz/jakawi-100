<?php

namespace Tests\Feature\Identity;

use App\Models\Partner;
use App\Models\User;
use App\Services\Identity\IdentityAuthority;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['identity.club_secret' => str_repeat('s', 64), 'identity.club_email_proof_secret' => str_repeat('p', 64),
            'crm.enabled' => false, 'identity.issuer' => 'https://jakawi.com']);
    }

    private function payload(string $email = 'new-identity@example.test', ?string $club = null): array
    {
        $id = (string) Str::uuid();
        $club ??= (string) Str::uuid();
        $claims = ['iss' => 'https://jakawi.club', 'aud' => 'https://jakawi.com', 'sub' => $club, 'email' => strtolower(trim($email)),
            'handoff_id' => $id, 'jti' => (string) Str::uuid(), 'method' => 'email_activation_token',
            'activation_ref' => 'activation:test:1', 'activated_at' => now()->subHour()->toISOString(), 'iat' => time(), 'exp' => time() + 240];
        $encoded = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        return ['contract' => IdentityAuthority::CONTRACT, 'source_app' => 'club', 'handoff_id' => $id, 'club_user_id' => $club,
            'name' => 'Identity Test', 'email' => $email, 'whatsapp' => '+59170000001',
            'email_activation_proof' => $encoded.'.'.hash_hmac('sha256', "jakawi-email-activation-v1\n".$encoded, config('identity.club_email_proof_secret')),
            'consent' => ['purpose' => 'identity_link', 'version' => '1', 'accepted_at' => now()->toISOString()]];
    }

    private function signed(string $method, string $path, ?array $data = null, ?array $overrides = null)
    {
        $body = $data === null ? '' : json_encode($data);
        $key = config('identity.club_key_id');
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $canonical = implode("\n", ['jakawi-identity-v1', $key, $timestamp, $nonce, $method, $path, hash('sha256', $body)]);
        $headers = array_replace(['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_X_JAKAWI_KEY_ID' => $key, 'HTTP_X_JAKAWI_TIMESTAMP' => $timestamp, 'HTTP_X_JAKAWI_NONCE' => $nonce,
            'HTTP_X_JAKAWI_SIGNATURE' => hash_hmac('sha256', $canonical, config('identity.club_secret'))], $overrides ?? []);

        return $this->call($method, $path, [], [], [], $headers, $body);
    }

    public function test_provisioning_is_idempotent_and_never_grants_business_privileges(): void
    {
        $data = $this->payload();
        $first = $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk()->assertJsonPath('status', 'PROVISIONED');
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertExactJson($first->json());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('identity_links', 1);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('partner_user', 0);
        $this->assertDatabaseCount('program_enrollments', 0);
        $this->assertDatabaseCount('referral_relationships', 0);
        $this->assertDatabaseCount('conversions', 0);
        $this->assertDatabaseCount('crm_deliveries', 0);
        $user = User::firstOrFail();
        $this->assertFalse((bool) $user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue(Str::isUuid($user->global_subject_id));
        $this->assertArrayNotHasKey('user_id', $first->json('link'));
        $this->signed('GET', '/internal/identity/links/'.$data['club_user_id'])->assertJsonPath('global_subject_id', $user->global_subject_id);
        $this->signed('GET', '/internal/identity/handoffs/'.$data['handoff_id'])->assertExactJson($first->json());
    }

    public function test_existing_account_requires_real_com_login_and_explicit_consent(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.test', 'is_admin' => true]);
        $partner = Partner::factory()->create();
        $user->partners()->attach($partner, ['role' => 'manager']);
        $before = $user->refresh()->getRawOriginal();
        $data = $this->payload(' EXISTING@example.test ');
        // Transport validators accept a normalized input from CLUB.
        $data['email'] = trim($data['email']);
        $response = $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk()->assertJsonPath('status', 'AUTH_REQUIRED');
        $this->assertNull($response->json('link'));
        $path = '/identity/club/link/'.$data['handoff_id'];
        $this->get($path)->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($path);
        $this->get($path)->assertOk()->assertSee('Autorizar vinculación con CLUB');
        $this->assertDatabaseCount('identity_links', 0);
        $this->post($path, ['authorize_link' => '1'])->assertRedirect($path);
        $this->signed('GET', '/internal/identity/links/'.$data['club_user_id'])->assertJsonPath('global_subject_id', $user->global_subject_id);
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertJsonPath('status', 'LINKED');
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertDatabaseHas('partner_user', ['user_id' => $user->id, 'partner_id' => $partner->id, 'role' => 'manager']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_authenticated_session_without_a_fresh_real_login_cannot_link(): void
    {
        $user = User::factory()->create();
        $data = $this->payload($user->email);
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertJsonPath('status', 'AUTH_REQUIRED');
        $this->actingAs($user)->post('/identity/club/link/'.$data['handoff_id'], ['authorize_link' => 1])->assertForbidden();
        $this->assertDatabaseCount('identity_links', 0);
    }

    public function test_wrong_account_and_missing_consent_cannot_link(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();
        $data = $this->payload($target->email);
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk();
        $path = '/identity/club/link/'.$data['handoff_id'];
        $this->post('/login', ['email' => $other->email, 'password' => 'password']);
        $this->post($path, ['authorize_link' => 1])->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['email' => $target->email, 'password' => 'password']);
        $this->post($path)->assertSessionHasErrors('authorize_link');
        $this->assertDatabaseCount('identity_links', 0);
    }

    public function test_two_factor_challenge_must_complete_before_consent(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $data = $this->payload($user->email);
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->post('/identity/club/link/'.$data['handoff_id'], ['authorize_link' => 1])->assertRedirect('/login');
        $this->assertDatabaseCount('identity_links', 0);
    }

    public function test_email_evidence_is_required_and_must_match_the_handoff(): void
    {
        foreach (['', 'true', $this->payload('someone-else@example.test')['email_activation_proof']] as $proof) {
            $data = $this->payload();
            $data['email_activation_proof'] = $proof;
            $this->signed('POST', '/internal/identity/handoffs', $data)->assertJsonPath('status', 'VERIFICATION_REQUIRED');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_changed_payload_conflicts_without_creating_another_user(): void
    {
        $data = $this->payload();
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk();
        $data['name'] = 'Changed';
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertStatus(409)->assertJsonPath('error', 'IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_new_handoff_for_same_email_requires_authentication_and_does_not_duplicate(): void
    {
        $this->signed('POST', '/internal/identity/handoffs', $this->payload())->assertJsonPath('status', 'PROVISIONED');
        $this->signed('POST', '/internal/identity/handoffs', $this->payload('NEW-IDENTITY@example.test'))->assertJsonPath('status', 'AUTH_REQUIRED');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unsigned_bad_signature_stale_timestamp_and_transport_replay_are_rejected(): void
    {
        $this->postJson('/internal/identity/handoffs', $this->payload())->assertUnauthorized();
        $this->signed('POST', '/internal/identity/handoffs', $this->payload(), ['HTTP_X_JAKAWI_SIGNATURE' => str_repeat('0', 64)])->assertUnauthorized();
        $this->signed('POST', '/internal/identity/handoffs', $this->payload(), ['HTTP_X_JAKAWI_TIMESTAMP' => (string) (time() - 3600)])->assertUnauthorized();
        $data = $this->payload();
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk();
        // Reuse the exact signed request captured by the test kernel.
        $request = app('request');
        $this->call('POST', '/internal/identity/handoffs', [], [], [], $request->server->all(), $request->getContent())->assertStatus(409)->assertJsonPath('error', 'REQUEST_REPLAY');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_privilege_and_subject_injection_is_rejected(): void
    {
        $data = $this->payload();
        $data['global_subject_id'] = (string) Str::uuid();
        $data['is_admin'] = true;
        $data['password'] = 'stolen-club-password';
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertStatus(422);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_revocation_is_idempotent_and_does_not_delete_or_reassign_identity(): void
    {
        $data = $this->payload();
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk();
        $before = User::firstOrFail()->getRawOriginal();
        $path = '/internal/identity/links/'.$data['club_user_id'].'/revoke';
        $first = $this->signed('POST', $path)->assertOk()->assertJsonPath('status', 'REVOKED');
        $this->signed('POST', $path)->assertExactJson($first->json());
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertJsonPath('status', 'REVOKED');
        $this->signed('POST', '/internal/identity/handoffs', $this->payload('new-identity@example.test', $data['club_user_id']))->assertStatus(409);
        $this->assertSame($before, User::firstOrFail()->getRawOriginal());
    }

    public function test_subject_is_unique_immutable_and_survives_deletion(): void
    {
        $first = User::factory()->create()->refresh();
        $second = User::factory()->create()->refresh();
        $this->assertNotSame($first->global_subject_id, $second->global_subject_id);
        $subject = $first->global_subject_id;
        $first->delete();
        $this->assertDatabaseHas('identity_subjects', ['global_subject_id' => $subject]);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('users')->where('id', $second->id)->update(['global_subject_id' => $subject]));
    }

    public function test_completed_two_factor_login_can_authorize_link(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $data = $this->payload($user->email);
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk();
        $path = '/identity/club/link/'.$data['handoff_id'];
        $this->get($path)->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertRedirect($path);
        $this->assertAuthenticatedAs($user);
        $this->post($path, ['authorize_link' => 1])->assertRedirect($path);
        $this->assertDatabaseCount('identity_links', 1);
    }

    public function test_expired_handoff_and_expired_authentication_cannot_authorize_link(): void
    {
        $user = User::factory()->create();
        $data = $this->payload($user->email);
        $this->signed('POST', '/internal/identity/handoffs', $data)->assertOk();
        $path = '/identity/club/link/'.$data['handoff_id'];
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->withSession(['identity.authenticated_at' => time() - 3600])->post($path, ['authorize_link' => 1])->assertForbidden();
        $this->withSession(['identity.authenticated_at' => time()]);
        DB::table('identity_handoffs')->where('handoff_id', $data['handoff_id'])->update(['expires_at' => now()->subMinute()]);
        $this->post($path, ['authorize_link' => 1])->assertStatus(410);
        $this->assertDatabaseCount('identity_links', 0);
    }

    public function test_only_one_club_account_can_link_to_a_com_subject(): void
    {
        $user = User::factory()->create();
        $first = $this->payload($user->email);
        $second = $this->payload($user->email);
        foreach ([$first, $second] as $data) {
            $this->signed('POST', '/internal/identity/handoffs', $data)->assertJsonPath('status', 'AUTH_REQUIRED');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/identity/club/link/'.$first['handoff_id'], ['authorize_link' => 1])->assertRedirect();
        $this->post('/identity/club/link/'.$second['handoff_id'], ['authorize_link' => 1])->assertStatus(409);
        $this->assertDatabaseCount('identity_links', 1);
    }

    public function test_duplicate_normalized_email_is_blocked_even_outside_identity_service(): void
    {
        User::factory()->create(['email' => 'normal@example.test']);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => User::factory()->create(['email' => ' NORMAL@example.test ']));
    }

    public function test_deleted_subject_cannot_be_reissued(): void
    {
        $user = User::factory()->create();
        $subject = $user->global_subject_id;
        $user->delete();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('users')->insert(['global_subject_id' => $subject,
            'name' => 'Attempted reuse', 'email' => 'reuse@example.test', 'password' => 'unused']));
    }

    public function test_shared_crm_or_missing_identity_credentials_fail_closed(): void
    {
        config(['crm.secret' => config('identity.club_secret')]);
        $this->signed('POST', '/internal/identity/handoffs', $this->payload())->assertStatus(503);
        config(['crm.secret' => '', 'identity.club_secret' => '']);
        $this->postJson('/internal/identity/handoffs', $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_migration_preserves_existing_user_values_and_memberships(): void
    {
        // An isolated schema models the pre-migration state inside the disposable test DB.
        DB::statement('CREATE SCHEMA identity_migration_probe');
        DB::statement('SET LOCAL search_path TO identity_migration_probe, public');
        DB::statement('CREATE TABLE users (id bigint PRIMARY KEY, name text, email text, password text, is_admin boolean, updated_at timestamp)');
        DB::statement('CREATE TABLE memberships (id bigint PRIMARY KEY, user_id bigint REFERENCES users(id), status text)');
        DB::table('users')->insert(['id' => 71, 'name' => 'Preserved Admin', 'email' => 'Pilot@Example.test', 'password' => 'unchanged-hash', 'is_admin' => true, 'updated_at' => '2026-10-01 10:00:00']);
        DB::table('memberships')->insert(['id' => 81, 'user_id' => 71, 'status' => 'active']);
        $before = (array) DB::table('users')->first();
        $membership = (array) DB::table('memberships')->first();
        $migration = require database_path('migrations/2026_10_29_230000_create_global_identity_authority.php');
        $migration->up();
        $after = (array) DB::table('users')->first();
        $subject = $after['global_subject_id'];
        unset($after['global_subject_id']);
        $this->assertSame($before, $after);
        $this->assertTrue(Str::isUuid($subject));
        $this->assertSame($membership, (array) DB::table('memberships')->first());
        DB::statement('SET LOCAL search_path TO public');
    }
}
