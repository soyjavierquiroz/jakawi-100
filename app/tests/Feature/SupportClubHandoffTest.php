<?php

namespace Tests\Feature;

use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportClubHandoffTest extends TestCase
{
    private User $admin;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') {
            throw new \RuntimeException('Isolated PostgreSQL required');
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->admin = User::factory()->create(['name' => 'Javier QA', 'is_admin' => true, 'is_superadmin' => true]);
        $this->target = User::factory()->create(['name' => 'Drex QA']);
        $this->actingAs($this->admin)->withSession([Auth::guard('web')->getName() => $this->admin->id]);
    }

    private function start(?User $target = null): void
    {
        $this->withSession(['support.reauthenticated_at' => time(), 'support.reauthenticated_user_id' => $this->admin->id])
            ->post('/admin/soporte', ['user_id' => ($target ?? $this->target)->id, 'reason' => 'Diagnóstico COM y transición a CLUB'])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_drex_exit_uses_fixed_club_url_and_keeps_actor_identity_without_creating_accounts(): void
    {
        $this->start();
        $before = DB::table('users')->get()->toJson();
        $this->post('/soporte/club', ['subject' => $this->admin->global_subject_id, 'redirect' => 'https://evil.example'])->assertStatus(303)
            ->assertRedirect('https://jakawi.club/admin/soporte?subject='.$this->target->global_subject_id)->assertSessionMissing('support_session_id');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertSame('club', SupportSession::first()->end_reason);
        $this->assertSame($before, DB::table('users')->get()->toJson());
        $this->assertDatabaseCount('oauth_auth_codes', 0);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_club_return_selects_subject_without_starting_support_and_still_requires_reauthentication(): void
    {
        $url = '/admin/soporte?subject='.$this->target->global_subject_id;
        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.id', $this->target->id)->where('fresh', false));
        $this->assertDatabaseCount('support_sessions', 0);
        $this->post('/admin/soporte', ['user_id' => $this->target->id, 'reason' => 'Retorno desde CLUB'])->assertForbidden();
        $this->get('/admin/soporte?subject=invalid')->assertSessionHasErrors('subject');
    }

    public function test_javier_can_observe_only_himself_among_admins_and_exit_to_club(): void
    {
        $this->get('/admin/soporte?subject='.$this->admin->global_subject_id)->assertInertia(fn (Assert $p) => $p->where('users.data.0.id', $this->admin->id));
        $other = User::factory()->create(['is_admin' => true]);
        $this->withSession(['support.reauthenticated_at' => time(), 'support.reauthenticated_user_id' => $this->admin->id])
            ->post('/admin/soporte', ['user_id' => $other->id, 'reason' => 'No observar a otro administrador'])->assertForbidden();
        $this->start($this->admin);
        $this->get('/perfil')->assertOk();
        $this->post('/soporte/club')->assertRedirect('https://jakawi.club/admin/soporte?subject='.$this->admin->global_subject_id);
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_expired_club_session_oidc_is_paused_then_authorizes_only_the_real_admin(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $private);
        config(['oidc.club_client_id' => '11111111-1111-4111-8111-111111111111', 'oidc.club_client_secret' => str_repeat('s', 64), 'oidc.club_redirect_uri' => 'https://jakawi.club/auth/jakawi/callback',
            'passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        $this->artisan('oidc:register-club')->assertSuccessful();
        $query = ['client_id' => '11111111-1111-4111-8111-111111111111', 'redirect_uri' => 'https://jakawi.club/auth/jakawi/callback', 'response_type' => 'code', 'scope' => 'openid profile email',
            'state' => str_repeat('s', 43), 'nonce' => str_repeat('n', 43), 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256'];
        $this->start();
        $this->get('/oauth/authorize?'.http_build_query($query), ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location');
        $this->get('/oauth/authorize?'.http_build_query($query))->assertForbidden()->assertSee('Salir del modo soporte y continuar en CLUB');
        $this->assertDatabaseCount('oauth_auth_codes', 0);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
        $id = array_key_first(session('support.oidc_continuations'));
        $resume = $this->post('/soporte/continuar-club', ['continuation_id' => $id, 'redirect' => 'https://evil.example'])->assertStatus(303)->assertSessionMissing('support_session_id');
        $this->assertSame('oidc_resume', SupportSession::first()->end_reason);
        $this->assertAuthenticatedAs($this->admin);
        $location = $resume->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $resumed);
        $this->assertSame($query, $resumed);
        $this->get($location)->assertRedirect();
        $this->assertSame((string) $this->admin->id, (string) DB::table('oauth_auth_codes')->sole()->user_id);
        $this->assertDatabaseMissing('oauth_auth_codes', ['user_id' => $this->target->id]);
    }

    public function test_invalid_oidc_no_dead_end_resume_and_no_arbitrary_redirect_or_replay(): void
    {
        $this->start();
        $this->get('/oauth/authorize?redirect_uri=https://evil.example')->assertForbidden()->assertDontSee('Salir del modo soporte y continuar en CLUB');
        $this->post('/soporte/continuar-club', ['continuation_id' => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa'])->assertStatus(409);
        $this->assertSame(SupportSession::first()->id, session('support_session_id'));
        $this->assertDatabaseCount('oauth_auth_codes', 0);
        $this->travel(16)->minutes();
        $this->post('/soporte/club')->assertRedirect('/admin');
        $this->assertSame('expired', SupportSession::first()->end_reason);
    }

    public function test_normal_admin_rejected_and_csrf_required_for_exit(): void
    {
        $this->admin->forceFill(['is_superadmin' => false])->save();
        $this->get('/admin/soporte?subject='.$this->target->global_subject_id)->assertForbidden();
        $this->post('/soporte/club')->assertForbidden();
        $this->assertDatabaseCount('support_sessions', 0);
        $this->admin->forceFill(['is_superadmin' => true])->save();
        $this->start();
        $this->app['env'] = 'local';
        $this->post('/soporte/club')->assertStatus(419);
        $this->assertNotNull(session('support_session_id'));
        $this->assertDatabaseCount('oauth_auth_codes', 0);
    }
}
