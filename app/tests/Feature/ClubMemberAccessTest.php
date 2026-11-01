<?php

namespace Tests\Feature;

use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\Identity\IdentityAuthority;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClubMemberAccessTest extends TestCase
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
        app(MembershipBankCheckout::class)->report($p, confirmed: true);
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

    private function accessFixture(bool $linked = true): array
    {
        $d = $this->intent();
        $p = $this->approve($this->create($d));
        $user = User::factory()->create(['email' => $d['email']]);
        $club = (string) Str::uuid();
        if ($linked) {
            $this->link($club, $user);
        }

        return [$p, $user, $club, $d];
    }

    private function issueTicket(array $f, array $extra = [])
    {
        [$p, $u, $club, $d] = $f;

        return $this->signed('POST', '/internal/club/purchases/'.$p->club_reference.'/access-ticket',
            array_replace(['club_user_uuid' => $club, 'claim_secret' => $d['claim_secret']], $extra));
    }

    private function stageTicket(string $token): void
    {
        $this->post('/club/access/stage', ['ticket' => $token])->assertRedirect('/club/access/continue');
    }

    public function test_guest_authorized_link_transfers_existing_membership_and_opens_session_without_password(): void
    {
        $f = $this->accessFixture();
        [$p, $u] = $f;
        $custody = $p->user_id;
        $result = $this->issueTicket($f)->assertOk()->assertJsonPath('destination', '/beneficios');
        $token = $result->json('ticket');
        $this->assertSame(64, strlen($token));
        $this->assertDatabaseHas('club_access_tickets', ['token_hash' => hash('sha256', $token), 'user_id' => $u->id]);
        $this->assertDatabaseMissing('club_access_tickets', ['token_hash' => $token]);
        $this->assertSame($u->id, $p->fresh()->membership->user_id);
        $this->assertNotSame($custody, $u->id);
        $users = User::count();
        $events = DB::table('commercial_outbox')->count();
        $this->get('/club/access')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee($token);
        $this->assertGuest();
        $this->stageTicket($token);
        $this->get('/club/access/continue')->assertOk()->assertSee('¡Tu membresía está lista!');
        $before = session()->getId();
        $csrfBefore = session()->token();
        $this->post('/club/access/consume', ['action' => 'continue'])->assertRedirect('/beneficios');
        $this->assertAuthenticatedAs($u);
        $this->assertNotSame($before, session()->getId());
        $this->assertNotSame($csrfBefore, session()->token());
        $this->get('/beneficios')->assertOk();
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('users', $users);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertDatabaseCount('commercial_outbox', $events);
        $this->issueTicket($f)->assertOk();
        $this->assertDatabaseCount('commercial_outbox', $events);
    }

    public function test_pending_identity_never_links_by_email_or_changes_custody(): void
    {
        $f = $this->accessFixture(false);
        [$p, $u] = $f;
        $custody = $p->user_id;
        $this->issueTicket($f)->assertConflict()->assertSee('IDENTITY_LINK_REQUIRED');
        $this->assertSame($custody, $p->fresh()->membership->user_id);
        $this->assertDatabaseCount('identity_links', 0);
        $this->assertDatabaseCount('club_access_tickets', 0);
        $this->link($f[2], $u);
        $this->issueTicket($f)->assertOk();
    }

    public function test_claim_required_even_when_linked_and_hmac_alone_cannot_login(): void
    {
        $f = $this->accessFixture();
        $this->issueTicket($f, ['claim_secret' => str_repeat('x', 48)])->assertForbidden();
        $this->issueTicket($f, ['claim_secret' => null])->assertUnprocessable();
        $this->postJson('/internal/club/purchases/'.$f[0]->club_reference.'/access-ticket', [])->assertUnauthorized();
        $this->assertDatabaseCount('club_access_tickets', 0);
        $this->assertGuest();
    }

    public function test_another_purchase_and_arbitrary_subject_cannot_be_used(): void
    {
        $f = $this->accessFixture();
        $this->issueTicket($f)->assertOk();
        $this->issueTicket($f, ['club_user_uuid' => (string) Str::uuid()])->assertConflict();
        $this->issueTicket($f, ['global_subject_id' => $f[1]->global_subject_id])->assertUnprocessable();
        $this->issueTicket($f, ['redirect' => 'https://example.com'])->assertUnprocessable();
        $other = $this->approve($this->create($this->intent(['claim_secret' => str_repeat('z', 48)])));
        $f[0] = $other;
        $this->issueTicket($f)->assertForbidden();
    }

    public function test_expired_and_replayed_tickets_fail_and_can_be_reissued(): void
    {
        $f = $this->accessFixture();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $this->stageTicket($token);
        $this->travel(91)->seconds();
        $this->post('/club/access/consume', ['action' => 'continue'])->assertStatus(410)->assertSee('Tu acceso expiró. Vuelve a intentarlo.');
        $this->assertGuest();
        $this->travelBack();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $this->stageTicket($token);
        $this->post('/club/access/consume')->assertRedirect('/beneficios');
        $this->post('/club/access/stage', ['ticket' => $token])->assertStatus(410);
        $this->withSession(['club_access_hash' => hash('sha256', $token)])->post('/club/access/consume')->assertStatus(410);
    }

    public function test_different_open_account_requires_explicit_switch_and_supports_cancel(): void
    {
        $f = $this->accessFixture();
        $other = User::factory()->create();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $this->actingAs($other);
        $this->stageTicket($token);
        $this->get('/club/access/continue')->assertSee('Estás usando otra cuenta JAKAWI.');
        $this->post('/club/access/consume')->assertConflict()->assertSee('Vuelve a CLUB para intentarlo de nuevo.');
        $this->assertAuthenticatedAs($other);
        $this->assertDatabaseHas('club_access_tickets', ['token_hash' => hash('sha256', $token), 'consumed_at' => null]);
        $this->post('/club/access/consume', ['action' => 'cancel'])->assertRedirect('https://jakawi.club');
        $this->assertAuthenticatedAs($other);
        $this->stageTicket($token);
        $this->post('/club/access/consume', ['switch_account' => 'yes'])->assertRedirect('/beneficios');
        $this->assertAuthenticatedAs($f[1]);
    }

    public function test_prefetch_and_get_cannot_consume_ticket_or_expose_it(): void
    {
        $f = $this->accessFixture();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $this->get('/club/access?ticket='.$token)->assertStatus(400);
        $this->get('/club/access', ['Sec-Purpose' => 'prefetch'])->assertOk()->assertDontSee($token)->assertDontSee('analytics');
        $this->get('/club/access/consume')->assertStatus(405);
        $this->assertDatabaseHas('club_access_tickets', ['token_hash' => hash('sha256', $token), 'consumed_at' => null]);
        $this->assertGuest();
        $this->post('/club/access/stage', ['ticket' => 'bad'])->assertStatus(410)->assertSessionMissing('_old_input.ticket');
    }

    public function test_revoked_link_or_inactive_membership_after_issuance_blocks_access(): void
    {
        $f = $this->accessFixture();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $this->stageTicket($token);
        DB::table('identity_links')->where('club_user_id', $f[2])->update(['status' => 'REVOKED']);
        $this->post('/club/access/consume')->assertConflict();
        $this->assertGuest();
        DB::table('identity_links')->where('club_user_id', $f[2])->update(['status' => 'LINKED']);
        $f[0]->fresh()->membership->update(['status' => 'cancelled']);
        $this->post('/club/access/consume')->assertConflict();
        $this->assertGuest();
    }

    public function test_existing_authorized_com_user_reuses_membership(): void
    {
        $f = $this->accessFixture();
        [$p, $u, $club, $d] = $f;
        $this->signed('POST', '/internal/club/purchases/'.$p->club_reference.'/identity', ['club_user_uuid' => $club, 'claim_secret' => $d['claim_secret']])->assertOk();
        $events = DB::table('commercial_outbox')->count();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $this->actingAs($u);
        $this->stageTicket($token);
        $this->post('/club/access/consume')->assertRedirect('/beneficios');
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('commercial_outbox', $events);
    }

    public function test_new_com_identity_provisioned_by_existing_authority_enters_without_knowing_random_password(): void
    {
        config(['identity.club_email_proof_secret' => str_repeat('p', 64), 'identity.issuer' => 'https://jakawi.com']);
        $d = $this->intent();
        $p = $this->approve($this->create($d));
        $club = (string) Str::uuid();
        $id = (string) Str::uuid();
        $claims = ['iss' => 'https://jakawi.club', 'aud' => 'https://jakawi.com', 'sub' => $club, 'email' => $d['email'],
            'handoff_id' => $id, 'jti' => (string) Str::uuid(), 'method' => 'email_activation_token',
            'activation_ref' => 'activation:test:1', 'activated_at' => now()->subHour()->toISOString(), 'iat' => time(), 'exp' => time() + 240];
        $encoded = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $data = ['handoff_id' => $id, 'club_user_id' => $club, 'name' => 'Compradora legítima', 'email' => $d['email'],
            'email_activation_proof' => $encoded.'.'.hash_hmac('sha256', "jakawi-email-activation-v1\n".$encoded, config('identity.club_email_proof_secret')),
            'consent' => ['purpose' => 'identity_link', 'version' => '1', 'accepted_at' => now()->toISOString()]];
        $authority = app(IdentityAuthority::class);
        $result = $authority->handoff($data, hash('sha256', json_encode($data)));
        $this->assertSame('PROVISIONED', $result['status']);
        $u = User::findOrFail($authority->link($club)->user_id);
        $count = User::count();
        $token = $this->issueTicket([$p, $u, $club, $d])->assertOk()->json('ticket');
        $this->stageTicket($token);
        $this->post('/club/access/consume')->assertRedirect('/beneficios');
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseCount('users', $count);
        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_background_stage_only_inspects_and_refresh_keeps_one_confirmation(): void
    {
        $f = $this->accessFixture();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $this->postJson('/club/access/stage', ['ticket' => $token])->assertOk()
            ->assertExactJson(['different' => false, 'destination' => null]);
        $this->assertGuest();
        $this->assertDatabaseHas('club_access_tickets', ['token_hash' => hash('sha256', $token), 'consumed_at' => null]);
        $this->get('/club/access/continue')->assertOk()->assertSee('¡Tu membresía está lista!')
            ->assertSee('Ya puedes disfrutar tus beneficios.')->assertSee('Entrar a mis beneficios');
        $this->get('/club/access/continue')->assertOk();
        $this->assertGuest();
        $this->post('/club/access/consume')->assertRedirect('/beneficios');
        $this->assertAuthenticatedAs($f[1]);
        // Duplicate submits and refresh cannot replay authentication.
        $this->post('/club/access/consume')->assertStatus(410);
        $this->get('/club/access/continue')->assertStatus(410);
        $this->assertAuthenticatedAs($f[1]);
        $this->get('/beneficios')->assertOk();
    }

    public function test_same_holder_session_goes_directly_to_benefits_without_reauthentication(): void
    {
        $f = $this->accessFixture();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $this->actingAs($f[1]);
        $this->get('/club/access')->assertOk();
        $this->withSession(['club_access_test_marker' => 'preserved']);
        $this->postJson('/club/access/stage', ['ticket' => $token])->assertOk()
            ->assertExactJson(['different' => false, 'destination' => '/beneficios']);
        $this->get('/club/access/continue')->assertRedirect('/beneficios');
        $this->assertSame('preserved', session('club_access_test_marker'));
        $this->assertAuthenticatedAs($f[1]);
        $this->assertDatabaseHas('club_access_tickets', ['token_hash' => hash('sha256', $token), 'consumed_at' => null]);
        $this->get('/beneficios')->assertOk();
        $f[0]->fresh()->membership->update(['status' => 'cancelled']);
        $this->postJson('/club/access/stage', ['ticket' => $token])->assertConflict()
            ->assertJsonPath('message', 'No pudimos confirmar tu acceso. Vuelve a CLUB para intentarlo de nuevo.');
        $this->get('/club/access/continue')->assertConflict();
    }

    public function test_background_stage_never_switches_accounts_and_expired_json_is_safe(): void
    {
        $f = $this->accessFixture();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->postJson('/club/access/stage', ['ticket' => $token])->assertOk()
            ->assertExactJson(['different' => true, 'destination' => null]);
        $this->assertAuthenticatedAs($other);
        $this->post('/club/access/consume')->assertConflict();
        $this->assertDatabaseHas('club_access_tickets', ['token_hash' => hash('sha256', $token), 'consumed_at' => null]);
        $this->travel(91)->seconds();
        $this->postJson('/club/access/stage', ['ticket' => $token])->assertStatus(410)
            ->assertExactJson(['message' => 'Tu acceso expiró. Vuelve a intentarlo.'])->assertDontSee($token);
        $this->assertAuthenticatedAs($other);
    }

    public function test_browser_continuation_requires_csrf(): void
    {
        $f = $this->accessFixture();
        $token = $this->issueTicket($f)->assertOk()->json('ticket');
        $middleware = new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $this->app->instance(PreventRequestForgery::class, $middleware);
        $this->post('/club/access/stage', ['ticket' => $token])->assertStatus(419);
        $this->post('/club/access/consume')->assertStatus(419);
        $this->assertGuest();
        $this->assertDatabaseHas('club_access_tickets', ['token_hash' => hash('sha256', $token), 'consumed_at' => null]);
    }

    public function test_inactive_or_conflicting_membership_never_issues_ticket_or_changes_custody(): void
    {
        $f = $this->accessFixture();
        [$p, $u] = $f;
        $custody = $p->user_id;
        $other = app(MembershipService::class)->activate($u, User::factory()->create(['is_admin' => true]));
        $this->issueTicket($f)->assertConflict();
        $this->assertSame($custody, $p->fresh()->membership->user_id);
        $this->assertDatabaseCount('club_access_tickets', 0);
        app(MembershipService::class)->cancel($other);
        $p->membership->update(['status' => 'cancelled']);
        $this->issueTicket($f)->assertConflict();
        $this->assertDatabaseCount('club_access_tickets', 0);
        $this->assertSame($custody, $p->fresh()->membership->user_id);
    }
}
