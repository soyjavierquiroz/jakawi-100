<?php
namespace Tests\Feature;

use App\Models\{AuditLog, Benefit, City, Experience, Partner, SupportSession, User};
use Illuminate\Support\Facades\{Auth, DB};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerManagementTest extends TestCase
{
    private User $admin;
    private User $owner;
    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') throw new \RuntimeException('Isolated database required');
        $this->artisan('migrate:fresh', ['--force' => true]);
        City::updateOrCreate(['slug' => 'cochabamba'], ['name' => 'Cochabamba', 'country_code' => 'BO', 'status' => City::ACTIVE]);
        $this->admin = User::factory()->create(['name' => 'Javier QA', 'is_admin' => true, 'is_superadmin' => true]);
        $this->owner = User::factory()->create();
        $this->partner = Partner::factory()->published()->create(['name' => 'Dossis 43 QA', 'slug' => 'dossis-qa']);
        $this->partner->users()->attach($this->owner, ['role' => 'owner', 'economic_access' => false]);
        $this->actingAs($this->admin)->withSession([Auth::guard('web')->getName() => $this->admin->id]);
    }

    private function start(string $mode = 'manage'): void
    {
        $this->withSession(['support.reauthenticated_at' => time(), 'support.reauthenticated_user_id' => $this->admin->id])
            ->post('/admin/soporte', ['user_id' => $this->owner->id, 'partner_id' => $this->partner->id, 'mode' => $mode, 'reason' => 'Configuración editorial solicitada'])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_management_saves_profile_promotion_experience_and_session_as_real_admin(): void
    {
        $this->start();
        $base = '/partner/dossis-qa';
        $this->get($base)->assertOk()->assertInertia(fn (Assert $p) => $p->where('auth.user.id', $this->admin->id)->where('supportMode.mode', 'manage')->where('partnerAccess.can_operate', false));
        $this->get($base.'/perfil')->assertOk()->assertInertia(fn (Assert $p) => $p->component('partner/business')->missing('item.internal_notes'));
        $this->put($base.'/perfil', ['name' => 'Dossis editado', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'archived', 'internal_notes' => 'Forbidden'])->assertRedirect($base.'/perfil')->assertSessionHas('success');
        $this->assertSame('Dossis editado', $this->partner->fresh()->name);
        $this->assertSame('published', $this->partner->fresh()->status);
        $this->post($base.'/promociones', ['title' => 'Promo café QA', 'location_scope' => 'all'])->assertRedirect();
        $benefit = Benefit::where('title', 'Promo café QA')->firstOrFail();
        $this->assertSame($this->admin->id, $benefit->created_by_user_id);
        $this->put($base.'/promociones/'.$benefit->slug, ['title' => 'Promo editada', 'location_scope' => 'all'])->assertRedirect();
        $this->post($base.'/promociones/'.$benefit->slug.'/enviar')->assertRedirect();
        $this->assertSame('submitted', $benefit->fresh()->review_status);
        $this->assertSame($this->admin->id, $benefit->fresh()->submitted_by_user_id);
        $this->post($base.'/experiencias', ['title' => 'Taller café QA', 'reservation_method' => 'none'])->assertRedirect();
        $experience = Experience::where('title', 'Taller café QA')->firstOrFail();
        $this->post($base.'/experiencias/'.$experience->slug.'/sessions', ['starts_at' => now()->addDay()->toDateTimeString(), 'capacity' => 10])->assertRedirect();
        $this->assertSame(1, $experience->sessions()->count());
        $audit = AuditLog::where('action', 'support_partner_record_saved')->where('subject_type', Benefit::class)->firstOrFail();
        $this->assertSame($this->admin->id, $audit->actor_user_id);
        $this->assertSame($this->partner->id, $audit->metadata['partner_id']);
        $this->assertSame('Promo café QA', $audit->metadata['after']['title']);
        $this->post($base.'/desbloqueos', ['title' => 'Campaña promocional QA', 'type' => 'BENEFIT', 'minimum_commitments' => 5])->assertRedirect();
        $unlock = \App\Models\Unlock::where('title', 'Campaña promocional QA')->firstOrFail();
        $this->assertSame($this->admin->id, $unlock->created_by_user_id);
        $this->post($base.'/desbloqueos/'.$unlock->slug.'/enviar')->assertRedirect();
        $this->assertSame(\App\Models\Unlock::PENDING_REVIEW, $unlock->fresh()->status);
        $this->assertSame($this->admin->id, session(Auth::guard('web')->getName()));
        $this->post('/soporte/salir')->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_management_rejects_other_partners_sensitive_operations_and_invalid_inputs(): void
    {
        $other = Partner::factory()->published()->create();
        $other->users()->attach($this->owner, ['role' => 'owner']);
        $foreign = Benefit::factory()->create(['partner_id' => $other->id, 'review_status' => 'draft']);
        $this->start();
        $this->get('/partner')->assertRedirect('/partner/dossis-qa');
        $this->get('/partner/'.$other->slug)->assertForbidden();
        $this->put('/partner/'.$other->slug.'/perfil', ['name' => 'Attack'])->assertForbidden();
        $this->put('/partner/dossis-qa/promociones/'.$foreign->slug, ['title' => 'Attack', 'location_scope' => 'all'])->assertNotFound();
        $this->put('/partner/dossis-qa/perfil', ['name' => '', 'entity_type' => 'organization', 'partner_type' => 'business'])->assertSessionHasErrors('name');
        foreach (['/partner/dossis-qa/validar', '/user/two-factor-authentication', '/membresia/checkout/aviso', '/admin/memberships'] as $path) {
            $this->post($path, [])->assertForbidden();
        }
        $this->assertSame(0, AuditLog::where('action', 'support_partner_action')->count());
        $this->put('/perfil', ['name' => 'Attack'])->assertForbidden();
        $this->put('/settings/password', [])->assertForbidden();
        $this->get('/membresia/checkout')->assertForbidden();
        $this->get('/oauth/authorize')->assertForbidden();
        $this->assertSame('Dossis 43 QA', $this->partner->fresh()->name);
        $this->assertSame(0, AuditLog::where('action', 'support_partner_record_saved')->count());
    }

    public function test_view_stays_read_only_and_unauthorized_admin_cannot_manage(): void
    {
        $this->admin->forceFill(['is_superadmin' => false])->save();
        $this->post('/admin/soporte', ['user_id' => $this->owner->id, 'partner_id' => $this->partner->id, 'mode' => 'manage', 'reason' => 'Configuración editorial solicitada'])->assertForbidden();
        $this->admin->forceFill(['is_superadmin' => true])->save();
        $this->start('view');
        $this->put('/partner/dossis-qa/perfil', ['name' => 'Attack'])->assertForbidden();
        $this->get('/partner/dossis-qa/promociones/crear')->assertForbidden();
        $this->assertSame('Dossis 43 QA', $this->partner->fresh()->name);
    }

    public function test_sucursal_save_preserves_partner_pin_and_publication(): void
    {
        $this->start();
        $this->post('/partner/dossis-qa/sedes', ['name' => 'Nueva sede QA', 'slug' => 'sede-qa', 'location_type' => 'online', 'partner_id' => 999, 'status' => 'published', 'redemption_pin' => '123456', 'opening_hours' => ['monday' => '09:00-18:00']])->assertRedirect();
        $location = $this->partner->locations()->firstOrFail();
        $this->assertSame('draft', $location->status);
        $this->assertFalse($location->hasRedemptionPin());
        $this->assertSame(['monday' => '09:00-18:00'], $location->opening_hours);
    }
}
