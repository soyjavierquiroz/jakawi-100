<?php

namespace Tests\Feature;

use App\Exceptions\PrelaunchOperationBlocked;
use App\Models\{Benefit, Challenge, Experience, ExperienceReservation, ExperienceSession, Location, Partner, Redemption, Unlock, UnlockParticipation, User};
use App\Services\{ChallengeService, ExperienceCheckInService, ExperienceReservationService, MembershipBankCheckout, MembershipPurchaseService, MembershipService, RedemptionService, RewardPayoutService, UnlockParticipationService, UnlockStatusService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrelaunchOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;
    private User $admin;
    private User $member;
    private Benefit $benefit;
    private Experience $experience;
    private ExperienceSession $session;
    private ExperienceReservation $reservation;
    private Redemption $redemption;
    private Unlock $unlock;
    private UnlockParticipation $participation;
    private Challenge $challenge;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode' => false, 'jakawi.analytics.enabled' => false]);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->member = User::factory()->create();
        $this->partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($this->partner)->create();
        $location->setRedemptionPin('123456'); $location->save();
        $this->benefit = Benefit::factory()->published()->forPartner($this->partner)->create(['applies_to_all_locations' => true]);
        app(MembershipService::class)->activate($this->member, $this->admin);
        $this->redemption = app(RedemptionService::class)->start($this->member, $this->benefit, $location);
        $this->experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $this->experience->syncPartnersWithRoles([['partner_id' => $this->partner->id, 'role' => 'organizer']]);
        $this->session = ExperienceSession::factory()->for($this->experience)->upcoming()->create(['reservation_partner_id' => $this->partner->id]);
        $this->reservation = app(ExperienceReservationService::class)->request($this->member, $this->experience, $this->session->id);
        $this->unlock = Unlock::create(['title' => 'Prueba', 'slug' => 'prueba', 'partner_id' => $this->partner->id, 'origin' => 'PARTNER', 'type' => 'BENEFIT', 'status' => Unlock::ACTIVE, 'minimum_commitments' => 10]);
        $this->participation = UnlockParticipation::create(['unlock_id' => $this->unlock->id, 'user_id' => $this->member->id, 'status' => UnlockParticipation::CONFIRMED]);
        $this->challenge = Challenge::create(['title' => 'Prueba', 'slug' => 'prueba', 'partner_id' => $this->partner->id, 'status' => 'open', 'review_status' => 'APPROVED', 'evidence_type' => 'MANUAL', 'qualification_type' => 'MANUAL', 'selection_type' => 'MANUAL', 'review_mode' => 'REQUIRED', 'evaluation_mode' => 'AT_CLOSE', 'reward_type' => 'MANUAL_PRIZE', 'manual_prize_description' => 'Prueba', 'allowed_platforms' => [], 'required_mentions' => [], 'required_hashtags' => []]);
    }

    private function actor(string $role): User
    {
        $actor = User::factory()->create();
        $actor->partners()->attach($this->partner, ['role' => $role]);
        return $actor;
    }

    private function snapshot(): array
    {
        $tables = ['experience_reservations', 'redemptions', 'unlock_participations', 'challenge_participations', 'challenge_social_entries', 'challenge_reward_grants', 'memberships', 'membership_purchases', 'membership_bank_payments', 'membership_purchase_requests', 'reward_transactions', 'reward_payouts', 'jp_holds', 'conversions', 'audit_logs', 'commercial_outbox', 'membership_bank_movements', 'jakawi_bank_reconciliations'];
        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->map(fn ($row) => json_encode($row))->sort()->values()->toJson()])->all();
    }

    public function test_owner_manager_staff_cannot_operate_direct_routes_or_signed_qr_and_records_do_not_change(): void
    {
        foreach (['owner', 'manager', 'staff'] as $role) {
            $actor = $this->actor($role);
            config(['jakawi.prelaunch_mode' => true]);
            $before = $this->snapshot();
            $base = '/partner/'.$this->partner->slug;
            $this->actingAs($actor);
            foreach ([
                $base.'/reservas/'.$this->reservation->public_id.'/confirmed' => [],
                $base.'/reservas/'.$this->reservation->public_id.'/rejected' => [],
                $base.'/asistencias/'.$this->reservation->public_id => [],
                $base.'/validar' => ['code' => $this->redemption->code, 'pin' => '123456'],
            ] as $url => $data) {
                $this->postJson($url, $data)->assertStatus(423)->assertJsonPath('code', 'PRELAUNCH_OPERATION_BLOCKED');
            }
            $qr = URL::temporarySignedRoute('partner.redemptions.scan.confirm', now()->addMinutes(10), ['partner' => $this->partner->slug, 'redemption_public_id' => $this->redemption->public_id]);
            $this->postJson($qr, ['pin' => '123456'])->assertStatus(423);
            $this->assertSame($before, $this->snapshot(), $role);
            config(['jakawi.prelaunch_mode' => false]);
        }
    }

    public function test_member_admin_and_internal_commercial_routes_are_blocked_before_payload_side_effects(): void
    {
        config(['jakawi.prelaunch_mode' => true]); $before = $this->snapshot();
        $this->actingAs($this->member);
        foreach ([
            '/experiencias/'.$this->experience->slug.'/reservas',
            '/beneficios/'.$this->benefit->slug.'/canjear',
            '/d/'.$this->unlock->slug.'/interes', '/d/'.$this->unlock->slug.'/comprometer',
            '/d/'.$this->unlock->slug.'/confirmar', '/d/'.$this->unlock->slug.'/cancelar',
            '/retos/'.$this->challenge->slug.'/participar',
            '/membresia/checkout/aviso', '/membresia/checkout/comprobante',
        ] as $url) $this->postJson($url)->assertStatus(423);
        $this->getJson('/experiencias/'.$this->experience->slug.'/reservar')->assertStatus(423);
        $this->getJson('/p/'.$this->partner->slug.'/club/checkout')->assertStatus(423);
        $this->actingAs($this->admin);
        foreach (['/admin/memberships', '/admin/sales', '/admin/conciliacion-membresias/aprobar', '/admin/extractos-bancarios/preview/00000000-0000-4000-8000-000000000000/confirmar'] as $url) $this->postJson($url)->assertStatus(423);
        $this->withoutMiddleware(\App\Http\Middleware\AuthenticateClubPurchase::class)->postJson('/internal/club/purchases', [])->assertStatus(423);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_service_calls_and_direct_models_cannot_bypass_http_policy(): void
    {
        $actor = $this->actor('owner'); config(['jakawi.prelaunch_mode' => true]); $before = $this->snapshot();
        foreach ([
            fn () => app(ExperienceReservationService::class)->request($this->member, $this->experience, $this->session->id),
            fn () => app(ExperienceReservationService::class)->respond($actor, $this->reservation, 'confirmed'),
            fn () => app(ExperienceCheckInService::class)->checkIn($actor, $this->reservation),
            fn () => app(RedemptionService::class)->confirm($this->redemption->code, '123456'),
            fn () => app(UnlockParticipationService::class)->commit($this->unlock, $this->member),
            fn () => app(UnlockParticipationService::class)->fulfill($this->participation, $actor),
            fn () => app(ChallengeService::class)->join($this->challenge, $this->member),
            fn () => app(MembershipService::class)->activate($actor, $this->admin),
            fn () => app(MembershipPurchaseService::class)->confirmManualCash($actor, $this->admin, null, 'TEST', 'test-prelaunch'),
            fn () => app(MembershipBankCheckout::class)->start($actor),
            fn () => app(RewardPayoutService::class)->requestPartner($this->partner, $actor),
            fn () => app(UnlockStatusService::class)->transition($this->unlock, Unlock::SCHEDULED, $this->admin),
            fn () => $this->reservation->update(['status' => 'confirmed']),
            fn () => $this->redemption->delete(),
        ] as $operation) {
            try { $operation(); $this->fail('PRELAUNCH bypass'); }
            catch (PrelaunchOperationBlocked $e) { $this->assertSame(423, $e->getStatusCode()); }
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_inertia_redirect_has_clear_validation_message_and_get_checkout_has_valid_inertia_page(): void
    {
        $actor = $this->actor('owner'); config(['jakawi.prelaunch_mode' => true]);
        $url = '/partner/'.$this->partner->slug.'/validar';
        $this->actingAs($actor)->from($url)->withHeader('X-Inertia', 'true')->post($url, ['code' => $this->redemption->code, 'pin' => '123456'])
            ->assertStatus(303)->assertRedirect($url)->assertSessionHasErrors('prelaunch');
        $this->flushHeaders();
        $this->get('/membresia/checkout')->assertStatus(423)->assertInertia(fn (Assert $p) => $p->component('prelaunch-blocked')->where('message', PrelaunchOperationBlocked::MESSAGE));
    }

    public function test_editorial_workflow_dashboard_history_and_kpis_remain_available(): void
    {
        $owner = $this->actor('owner'); config(['jakawi.prelaunch_mode' => true]);
        $base = '/partner/'.$this->partner->slug;
        $this->actingAs($owner);
        foreach ([$base, $base.'/perfil', $base.'/rendimiento', $base.'/reservas', $base.'/promociones', $base.'/experiencias', $base.'/retos', $base.'/desbloqueos'] as $url) $this->get($url)->assertOk();
        $this->post($base.'/promociones', ['title' => 'Borrador seguro', 'location_scope' => 'all'])->assertRedirect();
        $draft = Benefit::where('title', 'Borrador seguro')->firstOrFail();
        $this->put($base.'/promociones/'.$draft->slug, ['title' => 'Propuesta segura', 'location_scope' => 'all'])->assertRedirect();
        $this->post($base.'/promociones/'.$draft->slug.'/enviar')->assertRedirect();
        $this->actingAs($this->admin)->get('/admin/review')->assertOk();
        $this->post('/admin/review/benefits/'.$draft->slug, ['action' => 'changes', 'notes' => 'Completar condiciones'])->assertRedirect();
        $this->assertSame('changes_requested', $draft->fresh()->review_status);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_automatic_processing_is_noop_and_expired_redemption_read_preserves_history(): void
    {
        $this->redemption->update(['expires_at' => now()->subMinute()]);
        config(['jakawi.prelaunch_mode' => true]); $before = $this->snapshot();
        $this->artisan('unlocks:expire')->assertSuccessful();
        $this->artisan('challenges:refresh-rankings')->assertSuccessful();
        $this->artisan('challenges:close-expired')->assertSuccessful();
        $this->assertSame(0, app(\App\Services\JakawiBankReconciliation::class)->run());
        $this->assertSame(0, app(\App\Services\Commercial\CommercialDispatcher::class)->run());
        $this->actingAs($this->member)->get('/canjes/'.$this->redemption->public_id)->assertOk();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_prelaunch_off_does_not_bypass_operational_confirmation_capacity_location_or_unlock_eligibility(): void
    {
        $this->experience->update(['catalog_visibility' => 'upcoming', 'operations_confirmed' => false]);
        $this->session->update(['capacity' => 1]);
        $this->assertFalse(app(\App\Services\ExperiencePublication::class)->canOperate($this->experience));
        $this->actingAs($this->member)->post('/experiencias/'.$this->experience->slug.'/reservas', ['experience_session_id' => $this->session->id])->assertSessionHasErrors('session');
        $this->experience->update(['catalog_visibility' => 'active', 'operations_confirmed' => true]);
        $this->session->update(['venue_label' => 'Punto de encuentro verificado']);
        $this->assertTrue(app(\App\Services\ExperiencePublication::class)->canOperate($this->experience));
        $owner = $this->actor('owner');
        app(ExperienceReservationService::class)->respond($owner, $this->reservation, 'confirmed');
        $another = User::factory()->create();
        app(MembershipService::class)->activate($another, $this->admin);
        $this->actingAs($another)->post('/experiencias/'.$this->experience->slug.'/reservas', ['experience_session_id' => $this->session->id])->assertSessionHasErrors('reservation');
        $this->assertSame(1, $this->session->confirmedAttendees());
        $location = $this->redemption->location;
        $location->update(['status' => 'paused']);
        $owner = $this->actor('owner');
        $this->actingAs($owner)->post('/partner/'.$this->partner->slug.'/validar', ['code' => $this->redemption->code, 'pin' => '123456'])->assertOk();
        $this->assertSame('pending', $this->redemption->fresh()->status);
        $this->unlock->update(['member_eligible' => false, 'free_user_eligible' => false]);
        try { app(UnlockParticipationService::class)->commit($this->unlock, $this->member); $this->fail('Eligibility bypass'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('unlock', $e->errors()); }
    }

    public function test_disabling_prelaunch_keeps_membership_pin_partner_and_eligibility_validation(): void
    {
        $owner = $this->actor('owner');
        config(['jakawi.prelaunch_mode' => true]);
        $this->actingAs($owner)->postJson('/partner/'.$this->partner->slug.'/validar')->assertStatus(423);
        config(['jakawi.prelaunch_mode' => false]);
        $this->actingAs($owner)->postJson('/partner/'.$this->partner->slug.'/validar', ['code' => $this->redemption->code, 'pin' => '000000'])->assertOk();
        $this->assertSame('pending', $this->redemption->fresh()->status);
        $this->postJson('/partner/'.$this->partner->slug.'/validar', ['code' => $this->redemption->code, 'pin' => '123456'])->assertOk();
        $this->assertSame('confirmed', $this->redemption->fresh()->status);
        $this->partner->update(['status' => 'paused']);
        $this->postJson('/partner/'.$this->partner->slug.'/reservas/'.$this->reservation->public_id.'/confirmed')->assertForbidden();
        $this->assertSame('pending', $this->reservation->fresh()->status);
        $this->actingAs(User::factory()->create())->post('/experiencias/'.$this->experience->slug.'/reservas', ['experience_session_id' => $this->session->id])->assertRedirect();
        $this->assertDatabaseCount('experience_reservations', 1);
    }
}
