<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportModeTest extends TestCase
{
    private User $admin;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        // No wrapping transaction: PostgreSQL must exercise SET TRANSACTION READ ONLY.
        // Audit migrations intentionally disallow down()/TRUNCATE, so rebuild only this isolated DB.
        if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') {
            throw new \RuntimeException('Support tests require the isolated PostgreSQL database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
        City::updateOrCreate(['slug' => 'cochabamba'], ['name' => 'Cochabamba', 'country_code' => 'BO', 'status' => City::ACTIVE]);
        $this->admin = User::factory()->create(['is_admin' => true, 'is_superadmin' => true]);
        $this->target = User::factory()->create();
        $this->actingAs($this->admin);
        $this->withSession([Auth::guard('web')->getName() => $this->admin->id]);
    }

    private function start(?Partner $partner = null): void
    {
        $this->withSession(['support.reauthenticated_at' => time(), 'support.reauthenticated_user_id' => $this->admin->id])
            ->post('/admin/soporte', ['user_id' => $this->target->id, 'partner_id' => $partner?->id, 'reason' => 'Diagnóstico solicitado de experiencia COM'])->assertRedirect();
        $this->assertDatabaseCount('support_sessions', 1);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_requires_explicit_superadmin_and_recent_reauthentication(): void
    {
        $this->post('/admin/soporte', ['user_id' => $this->target->id, 'reason' => 'Diagnóstico de cuenta'])->assertForbidden();
        $this->admin->forceFill(['is_superadmin' => false, 'email' => 'javierquiroztv@gmail.com'])->save();
        $this->get('/admin/soporte')->assertForbidden();
        $this->withSession(['support.reauthenticated_at' => time(), 'support.reauthenticated_user_id' => $this->admin->id])
            ->post('/admin/soporte', ['user_id' => $this->target->id, 'reason' => 'Diagnóstico de cuenta'])->assertForbidden();
        $this->assertDatabaseCount('support_sessions', 0);
    }

    public function test_password_mfa_and_reason_are_required(): void
    {
        // Browsing the directory must not consume password/MFA attempts.
        for ($i = 0; $i < 6; $i++) {
            $this->get('/admin/soporte')->assertOk();
        }
        $this->post('/admin/soporte/reautenticar', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/admin/soporte/reautenticar', ['password' => 'password'])->assertRedirect()->assertSessionHas('support.reauthenticated_user_id', $this->admin->id);
        $this->post('/admin/soporte', ['user_id' => $this->target->id])->assertSessionHasErrors('reason');
        $this->admin->forceFill(['two_factor_secret' => Crypt::encryptString('TESTSECRET'), 'two_factor_confirmed_at' => now()])->save();
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('verify')->with('TESTSECRET', '')->once()->andReturn(false);
        $provider->shouldReceive('verify')->with('TESTSECRET', '123456')->once()->andReturn(true);
        $this->app->instance(TwoFactorAuthenticationProvider::class, $provider);
        $this->post('/admin/soporte/reautenticar', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->post('/admin/soporte/reautenticar', ['password' => 'password', 'code' => '123456'])->assertRedirect();
    }

    public function test_free_user_sees_real_profile_without_persisting_login_or_secrets(): void
    {
        $this->start();
        $this->get('/mi-jakawi')->assertOk()->assertInertia(fn (Assert $p) => $p->component('mi-jakawi')->where('auth.user.id', $this->target->id)->where('membership', null)->where('supportMode.id', SupportSession::first()->id)->missing('auth.user.password')->missing('auth.user.global_subject_id')->missing('auth.user.two_factor_secret'));
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/perfil')->assertOk()->assertInertia(fn (Assert $p) => $p->component('member-profile')->where('user.email', $this->target->email));
        $this->assertSame($this->admin->id, session(Auth::guard('web')->getName()));
    }

    public function test_pioneer_view_does_not_create_referral_or_change_commercial_data(): void
    {
        Membership::create(['user_id' => $this->target->id, 'type' => 'pioneer', 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'amount_paid' => 100]);
        $before = DB::table('memberships')->get()->toJson();
        $subject = $this->target->global_subject_id;
        $analyticsCount = DB::table('analytics_events')->count();
        $this->start();
        $this->get('/mi-jakawi')->assertOk()->assertInertia(fn (Assert $p) => $p->where('membershipStatus.type', 'pioneer'));
        $this->assertSame($before, DB::table('memberships')->get()->toJson());
        $this->assertNull($this->target->fresh()->referral_code);
        $this->assertSame($subject, $this->target->fresh()->global_subject_id);
        $this->assertDatabaseCount('analytics_events', $analyticsCount);
    }

    public static function partnerRoles(): array
    {
        return ['owner' => ['owner', true], 'manager without economics' => ['manager', false]];
    }

    #[DataProvider('partnerRoles')]
    public function test_existing_partner_representative_preserves_economic_access_and_isolation(string $role, bool $expectedEconomics): void
    {
        $partner = Partner::factory()->published()->create();
        $other = Partner::factory()->published()->create();
        $this->target->partners()->attach($partner, ['role' => $role, 'economic_access' => false]);
        $this->start($partner);
        $this->get('/partner/'.$partner->slug)->assertOk()->assertInertia(fn (Assert $p) => $p->component('partner/dashboard')->where('partnerAccess.can_view_economics', $expectedEconomics));
        $this->get('/partner/'.$partner->slug.'/promociones')->assertOk();
        $this->get('/partner/'.$other->slug)->assertForbidden();
        $this->assertFalse((bool) $this->target->partners()->first()->pivot->economic_access);
        $this->assertDatabaseCount('partner_user', 1);
    }

    public function test_partner_without_existing_authorization_is_rejected(): void
    {
        $partner = Partner::factory()->create();
        $this->withSession(['support.reauthenticated_at' => time(), 'support.reauthenticated_user_id' => $this->admin->id])
            ->post('/admin/soporte', ['user_id' => $this->target->id, 'partner_id' => $partner->id, 'reason' => 'Diagnóstico de Partner'])->assertForbidden();
        $this->assertDatabaseCount('support_sessions', 0);
        $this->assertDatabaseCount('partner_user', 0);
    }

    public function test_mutations_sensitive_gets_and_chaining_fail_closed(): void
    {
        $before = $this->target->fresh()->toArray();
        $this->start();
        foreach (['/admin/soporte', '/logout', '/membresia/solicitar', '/media/uploads', '/comunidades/cochabamba/interaccion'] as $url) {
            $this->post($url, ['user_id' => $this->admin->id])->assertForbidden();
        }
        $this->put('/mi-jakawi/perfil', ['city' => 'La Paz'])->assertForbidden();
        foreach (['/admin', '/settings/security', '/membresia/checkout', '/membresia/checkout/qr/descargar', '/oauth/authorize', '/r/EXAMPLE', '/club/access'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->assertSame($before, $this->target->fresh()->toArray());
        $this->assertDatabaseCount('support_sessions', 1);
    }

    public function test_database_blocks_implicit_get_writes(): void
    {
        Route::middleware('web')->get('/support-write-probe', function () {
            DB::table('users')->where('id', $this->target->id)->update(['name' => 'MUTATED']);

            return 'unsafe';
        })->name('home');
        $this->start();
        $this->get('/support-write-probe')->assertForbidden()->assertSee('Modo de sólo lectura');
        $this->assertSame($this->target->name, $this->target->fresh()->name);
    }

    public function test_exit_expiry_and_revocation_restore_admin(): void
    {
        $this->start();
        $this->post('/soporte/salir')->assertRedirect('/admin')->assertSessionMissing('support_session_id');
        $this->assertSame('exit', SupportSession::first()->end_reason);
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/admin')->assertOk();
    }

    public function test_expiry_is_enforced_without_browser_timer(): void
    {
        $this->start();
        $s = SupportSession::first();
        $this->assertLessThanOrEqual(900, $s->started_at->diffInSeconds($s->expires_at));
        $this->travel(16)->minutes();
        $this->put('/mi-jakawi/perfil', ['city' => 'La Paz'])->assertRedirect('/admin')->assertSessionMissing('support_session_id');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertDatabaseCount('user_profiles', 0);
    }

    public function test_permission_revocation_ends_support_without_exposing_target(): void
    {
        $this->start();
        $this->admin->forceFill(['is_superadmin' => false])->save();
        $this->get('/perfil')->assertRedirect('/admin')->assertSessionMissing('support_session_id');
        $this->assertSame('revoked', SupportSession::first()->end_reason);
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_session_binding_rejects_another_browser(): void
    {
        $this->start();
        SupportSession::first()->update(['session_hash' => str_repeat('0', 64)]);
        $this->get('/mi-jakawi')->assertRedirect('/admin')->assertSessionMissing('support_session_id');
    }
}
