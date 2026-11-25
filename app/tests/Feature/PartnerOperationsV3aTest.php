<?php

namespace Tests\Feature;
use App\Models\{Benefit, Experience, ExperienceReservation, ExperienceSession, Location, Partner, Redemption, User};
use App\Services\{ExperienceCheckInService, ExperiencePublication, ExperienceReservationService, RedemptionService};
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerOperationsV3aTest extends TestCase
{
    use RefreshDatabase;

    private function operationalSession(array $fields = []): array
    {
        config(['jakawi.prelaunch_mode' => false]);
        $partner = Partner::factory()->published()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi', 'duration_minutes' => 60]);
        $experience->partners()->attach($partner, ['role' => 'host']);
        $location = Location::factory()->published()->withPartner($partner)->create(['timezone' => 'America/La_Paz']);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create($fields + ['reservation_partner_id' => $partner->id, 'location_id' => $location->id, 'capacity' => 4]);
        $actor = User::factory()->create();
        $actor->partners()->attach($partner, ['role' => 'manager']);
        return [$session, $partner, $actor];
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->memberships()->create(['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek(), 'amount_paid' => 0]);
        return $user;
    }

    private function reservation(ExperienceSession $session, string $status = 'pending', int $size = 1): ExperienceReservation
    {
        return ExperienceReservation::create(['user_id' => $this->member()->id, 'experience_id' => $session->experience_id, 'experience_session_id' => $session->id, 'partner_id' => $session->reservation_partner_id, 'status' => $status, 'party_size' => $size, 'check_in_code' => app(ExperienceCheckInService::class)->code()]);
    }

    public function test_confirmed_groups_allocate_capacity_pending_rejected_and_cancelled_do_not(): void
    {
        [$session, , $actor] = $this->operationalSession();
        $service = app(ExperienceReservationService::class);
        $first = $this->reservation($session, 'confirmed', 2);
        $this->reservation($session, 'rejected', 10);
        $this->reservation($session, 'cancelled', 10);
        $pending = $this->reservation($session, 'pending', 2);
        $other = $this->reservation($session, 'pending', 3);
        $service->respond($actor, $pending, 'confirmed');
        $responded = $pending->fresh()->responded_at;
        $service->respond($actor, $pending, 'confirmed');
        $this->assertTrue($pending->fresh()->responded_at->eq($responded));
        try { $service->respond($actor, $other, 'confirmed'); $this->fail('Overselling'); } catch (ValidationException $e) { $this->assertArrayHasKey('reservation', $e->errors()); }
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertSame(4, $session->confirmedAttendees());
        $service->cancel($first->user, $first);
        $cancelled = $first->fresh()->cancelled_at;
        $service->cancel($first->user, $first);
        $this->assertTrue($first->fresh()->cancelled_at->eq($cancelled));
        $other->update(['party_size' => 2]);
        $service->respond($actor, $other, 'confirmed');
        $this->assertSame(4, $session->confirmedAttendees());
    }

    public function test_requests_validate_whole_group_and_retries_keep_original_size_even_when_full(): void
    {
        [$session] = $this->operationalSession(['capacity' => 2]);
        $service = app(ExperienceReservationService::class);
        $member = $this->member();
        $r = $service->request($member, $session->experience, $session->id, 2);
        $this->assertSame($r->id, $service->request($member, $session->experience, $session->id, 1)->id);
        $r->update(['status' => 'confirmed']);
        $this->assertSame(2, $service->request($member, $session->experience, $session->id, 1)->party_size);
        foreach ([1, 3, 11, 0] as $size) {
            try { $service->request($this->member(), $session->experience, $session->id, $size); $this->fail('Capacity or group size bypass'); } catch (ValidationException) {}
        }
        $this->assertDatabaseCount('experience_reservations', 1);
    }

    public function test_session_with_any_reservation_cannot_be_deleted_or_reassigned_and_capacity_cannot_shrink_below_occupancy(): void
    {
        [$session, , $actor] = $this->operationalSession();
        $this->reservation($session, 'confirmed', 3);
        foreach (['pending', 'confirmed', 'rejected', 'cancelled'] as $status) {
            $r = $this->reservation($session, $status);
            $admin = User::factory()->create(['is_admin' => true]);
            $this->actingAs($admin)->delete('/admin/experiences/'.$session->experience->slug.'/sessions/'.$session->id)->assertSessionHasErrors('session');
            $this->assertNotNull($r->fresh());
        }
        $before = $session->fresh()->getAttributes();
        try { $session->update(['capacity' => 2]); $this->fail('Capacity reduction'); } catch (ValidationException) {}
        $session->refresh();
        try { $session->update(['reservation_partner_id' => Partner::factory()->published()->create()->id]); $this->fail('Responsibility rewrite'); } catch (ValidationException) {}
        $this->assertSame($before, $session->fresh()->getAttributes());
        [$empty] = $this->operationalSession();
        $empty->delete();
        $this->assertNull($empty->fresh());
    }

    public function test_confirmations_after_start_and_cross_partner_actions_do_not_modify_reservations(): void
    {
        [$session, $partner, $actor] = $this->operationalSession();
        $r = $this->reservation($session);
        $other = Partner::factory()->published()->create();
        $actor->partners()->attach($other, ['role' => 'owner']);
        $this->actingAs($actor)->post('/partner/'.$other->slug.'/reservas/'.$r->public_id.'/confirmed')->assertForbidden();
        $this->actingAs($actor)->post('/partner/'.$other->slug.'/asistencias', ['code' => $r->check_in_code])->assertInertia(fn (Assert $p) => $p->where('error', 'Código inválido. Comprueba los seis caracteres y el establecimiento.')->missing('reservation'));
        $this->travelTo($session->starts_at->copy()->addMinute());
        $this->post('/partner/'.$partner->slug.'/reservas/'.$r->public_id.'/confirmed')->assertSessionHasErrors('reservation');
        $this->assertSame('pending', $r->fresh()->status);
        $this->assertNull($r->fresh()->checked_in_at);
    }

    public function test_pending_future_and_past_confirmed_history_remain_visible_and_scoped(): void
    {
        [$session, $partner, $actor] = $this->operationalSession();
        $pending = $this->reservation($session);
        $future = $this->reservation($session, 'confirmed');
        [$past] = $this->operationalSession(['starts_at' => now()->subHour(), 'ends_at' => now()->subMinute(), 'reservation_partner_id' => $partner->id]);
        $confirmed = $this->reservation($past, 'confirmed');
        $this->actingAs($actor)->get('/partner/'.$partner->slug.'/reservas')->assertInertia(fn (Assert $p) => $p->where('pending.0.public_id', $pending->public_id)->where('upcomingConfirmed.0.public_id', $future->public_id)->where('history.0.public_id', $confirmed->public_id)->where('history.0.timezone', 'America/La_Paz'));
    }

    public function test_check_in_window_uses_absolute_instants_and_explicit_end_or_duration_without_grace(): void
    {
        [$session, $partner, $actor] = $this->operationalSession(['starts_at' => '2030-04-03T10:00:00-04:00', 'ends_at' => '2030-04-03T11:00:00-04:00']);
        $this->assertSame('2030-04-03 14:00:00', $session->fresh()->starts_at->toDateTimeString());
        $r = $this->reservation($session, 'confirmed', 2);
        $service = app(ExperienceCheckInService::class);
        $this->travelTo($session->starts_at->copy()->subSecond());
        $this->assertStringContainsString('Aún no comienza', $service->blocker($r));
        $this->actingAs($actor)->post('/partner/'.$partner->slug.'/asistencias/'.$r->public_id)->assertInertia(fn (Assert $p) => $p->where('error', 'Aún no comienza la sesión. Registra la asistencia durante su horario.'));
        $this->assertNull($r->fresh()->checked_in_at);
        $this->travelTo($session->starts_at);
        $this->assertNull($service->blocker($r));
        $this->travelTo($session->ends_at);
        $checked = $service->checkIn($actor, $r);
        $this->assertNotNull($checked->checked_in_at);
        $this->travelTo($session->ends_at->copy()->addDay());
        $this->assertTrue($service->checkIn($actor, $r)->checked_in_at->eq($checked->checked_in_at));
        $fresh = $this->reservation($session, 'confirmed');
        $this->assertStringContainsString('venció', $service->blocker($fresh));
        try { $service->checkIn($actor, $fresh); $this->fail('Late check-in'); } catch (DomainException) {}
        $this->assertNull($fresh->fresh()->checked_in_at);
        $session->update(['ends_at' => null]);
        $fresh->unsetRelation('session');
        $this->travelTo($session->starts_at->copy()->addMinutes(30));
        $this->assertNull($service->blocker($fresh));
        $session->experience->update(['duration_minutes' => null]);
        $fresh->unsetRelation('session');
        $this->assertStringContainsString('duración válida', $service->blocker($fresh));
        $this->assertSame('America/La_Paz', $session->displayTimezone());
        $session->location->update(['timezone' => 'Invalid/Zone']);
        $this->assertSame('UTC', $session->displayTimezone());
    }

    public function test_check_in_requires_confirmed_scheduled_reservation_and_correct_partner_and_signed_qr(): void
    {
        [$session, $partner, $actor] = $this->operationalSession(['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addHour()]);
        $r = $this->reservation($session);
        $service = app(ExperienceCheckInService::class);
        $this->assertStringContainsString('confirmada', $service->blocker($r));
        $this->actingAs($actor)->get('/partner/'.$partner->slug.'/asistencias/'.$r->public_id)->assertForbidden();
        $r->update(['status' => 'confirmed']);
        $session->update(['status' => 'cancelled']); $r->unsetRelation('session');
        $this->assertStringContainsString('no está disponible', $service->blocker($r));
        $other = User::factory()->create();
        $other->partners()->attach(Partner::factory()->published()->create(), ['role' => 'staff']);
        $this->actingAs($other)->post('/partner/'.$partner->slug.'/asistencias/'.$r->public_id)->assertForbidden();
        $this->assertNull($r->fresh()->checked_in_at);
    }

    private function redemption(): array
    {
        [$session, $partner, $actor] = $this->operationalSession();
        $location = $session->location; $location->setRedemptionPin('123456'); $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        $redemption = app(RedemptionService::class)->start($this->member(), $benefit, $location);
        return [$redemption, $partner, $actor, $benefit];
    }

    public function test_manual_redemption_feedback_pin_success_replay_expiry_and_commercial_restrictions(): void
    {
        [$r, $partner, $actor, $benefit] = $this->redemption();
        $this->actingAs($actor); $url = '/partner/'.$partner->slug.'/validar';
        $this->post($url, ['code' => 'ZZZZZZ', 'pin' => '123456'])->assertForbidden()->assertInertia(fn (Assert $p) => $p->where('error', 'Código inválido. Comprueba el código y el establecimiento.')->missing('result'));
        $this->post($url, ['code' => $r->code, 'pin' => '000000'])->assertInertia(fn (Assert $p) => $p->where('error', 'PIN incorrecto. Utiliza el PIN de la sede indicada.'));
        $this->assertSame('pending', $r->fresh()->status);
        $this->post($url, ['code' => $r->code, 'pin' => '123456'])->assertInertia(fn (Assert $p) => $p->where('message', 'Canje confirmado correctamente.')->where('result.status', 'confirmed')->missing('result.member_name')->missing('result.user_id')->missing('result.pin'));
        $confirmed = $r->fresh()->confirmed_at;
        $this->post($url, ['code' => $r->code, 'pin' => '123456'])->assertInertia(fn (Assert $p) => $p->where('message', 'Canje previamente utilizado. Ya estaba confirmado; no se registró otro canje.'));
        $this->assertTrue($r->fresh()->confirmed_at->eq($confirmed));
        $this->assertSame(1, Redemption::where('status', 'confirmed')->count());
        [$expired, $p, $a] = $this->redemption(); $expired->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($a)->post('/partner/'.$p->slug.'/validar', ['code' => $expired->code, 'pin' => '123456'])->assertInertia(fn (Assert $page) => $page->where('error', 'Código expirado. El miembro debe generar un nuevo código.'));
        $this->post('/partner/'.$p->slug.'/validar', ['code' => $expired->code, 'pin' => '123456'])->assertInertia(fn (Assert $page) => $page->where('error', 'Código expirado. El miembro debe generar un nuevo código.'));
        [$restricted, $p, $a, $b] = $this->redemption(); $b->update(['status' => 'paused']);
        $this->actingAs($a)->post('/partner/'.$p->slug.'/validar', ['code' => $restricted->code, 'pin' => '123456'])->assertInertia(fn (Assert $page) => $page->where('error', 'Este beneficio no está disponible en esta sede o según sus condiciones vigentes.'));
        $this->assertSame('pending', $restricted->fresh()->status);
    }

    public function test_qr_redemption_feedback_keeps_signatures_and_hides_member_personal_information(): void
    {
        [$r, $partner, $actor] = $this->redemption();
        $url = URL::temporarySignedRoute('partner.redemptions.scan.confirm', now()->addMinutes(10), ['partner' => $partner->slug, 'redemption_public_id' => $r->public_id]);
        $this->actingAs($actor)->post(strtok($url, '?'), ['pin' => '123456'])->assertForbidden();
        $this->post($url, ['pin' => '000000'])->assertInertia(fn (Assert $p) => $p->where('error', 'PIN incorrecto. Utiliza el PIN de la sede indicada.')->missing('redemption.member_name')->missing('redemption.user'));
        $this->post($url, ['pin' => '123456'])->assertInertia(fn (Assert $p) => $p->where('message', 'Canje confirmado correctamente.'));
        $other = Partner::factory()->published()->create(); $actor->partners()->attach($other, ['role' => 'staff']);
        $this->post('/partner/'.$other->slug.'/validar', ['code' => $r->code, 'pin' => '123456'])->assertForbidden()->assertInertia(fn (Assert $p) => $p->missing('result'));
    }

    public function test_finite_capacity_no_longer_has_obsolete_technical_blocker_but_operational_confirmation_still_required(): void
    {
        [$session] = $this->operationalSession();
        $experience = $session->experience;
        $experience->update(['catalog_visibility' => 'active', 'operations_confirmed' => false]);
        $policy = app(ExperiencePublication::class);
        $this->assertFalse($policy->canOperate($experience));
        $experience->update(['operations_confirmed' => true]);
        $this->assertTrue($policy->canOperate($experience));
        $experience->update(['catalog_visibility' => 'upcoming']);
        $this->assertFalse($policy->canOperate($experience));
    }
}
