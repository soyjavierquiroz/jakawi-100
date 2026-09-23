<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\Partner;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExperienceReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_requests_once_and_the_event_is_recorded_once(): void
    {
        [$user, $experience, $session] = $this->reservable(true);
        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id])->assertRedirect();
        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id])->assertRedirect();
        $this->assertDatabaseCount('experience_reservations', 1);
        $this->assertDatabaseHas('experience_reservations', ['user_id' => $user->id, 'status' => 'pending', 'party_size' => 1]);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'experience_reserve_click')->count());
    }

    public function test_party_size_persists_and_existing_active_reservation_keeps_its_original_size(): void
    {
        [$user, $experience, $session] = $this->reservable(true);

        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id, 'party_size' => 2])->assertRedirect();
        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id, 'party_size' => 4])->assertRedirect();

        $this->assertDatabaseCount('experience_reservations', 1);
        $this->assertDatabaseHas('experience_reservations', ['user_id' => $user->id, 'party_size' => 2]);
    }

    public function test_party_size_must_be_between_one_and_ten(): void
    {
        [$user, $experience, $session] = $this->reservable(true);

        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id, 'party_size' => 0])->assertSessionHasErrors('party_size');
        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id, 'party_size' => 11])->assertSessionHasErrors('party_size');
        $this->assertDatabaseCount('experience_reservations', 0);
    }

    public function test_guest_and_member_without_membership_cannot_request(): void
    {
        [$user, $experience, $session] = $this->reservable();
        $this->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id])->assertRedirect();
        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id])->assertSessionHasErrors('session');
        $this->assertDatabaseCount('experience_reservations', 0);
    }

    public function test_external_and_invalid_or_past_sessions_cannot_create_internal_reservations(): void
    {
        [$user, $experience, $session] = $this->reservable(true);
        $experience->update(['reservation_method' => 'external']);
        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id])->assertSessionHasErrors('session');
        $experience->update(['reservation_method' => 'jakawi']);
        $session->update(['starts_at' => now()->subMinute()]);
        $this->actingAs($user)->post("/experiencias/{$experience->slug}/reservas", ['experience_session_id' => $session->id])->assertSessionHasErrors('session');
    }

    public function test_partner_can_confirm_or_reject_only_its_own_reservations_and_member_can_cancel_own(): void
    {
        [$member, $experience, $session, $partner] = $this->reservable(true);
        $reservation = ExperienceReservation::create(['user_id' => $member->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'pending']);
        $manager = User::factory()->create();
        $manager->partners()->attach($partner, ['role' => 'manager']);
        $other = Partner::factory()->published()->create();
        $intruder = User::factory()->create();
        $intruder->partners()->attach($other, ['role' => 'manager']);
        $this->actingAs($intruder)->post("/partner/{$other->slug}/reservas/{$reservation->public_id}/confirmed")->assertForbidden();
        $this->actingAs($manager)->post("/partner/{$partner->slug}/reservas/{$reservation->public_id}/confirmed")->assertRedirect();
        $this->assertDatabaseHas('experience_reservations', ['id' => $reservation->id, 'status' => 'confirmed', 'responded_by_user_id' => $manager->id]);
        $this->actingAs($member)->post("/reservas/{$reservation->public_id}/cancelar")->assertRedirect();
        $this->assertDatabaseHas('experience_reservations', ['id' => $reservation->id, 'status' => 'cancelled']);
        $this->actingAs(User::factory()->create())->get('/partner/reservas')->assertForbidden();
    }

    public function test_partner_view_shows_only_member_name_party_size_and_confirmed_attendee_totals(): void
    {
        [$member, $experience, $session, $partner] = $this->reservable(true);
        $member->update(['name' => 'Miembro Visible']);
        $manager = User::factory()->create();
        $manager->partners()->attach($partner, ['role' => 'manager']);
        ExperienceReservation::create(['user_id' => $member->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'pending', 'party_size' => 2]);
        ExperienceReservation::create(['user_id' => User::factory()->create()->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'confirmed', 'party_size' => 3]);
        ExperienceReservation::create(['user_id' => User::factory()->create()->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'confirmed', 'party_size' => 1]);

        $this->actingAs($manager)->get('/partner/'.$partner->slug.'/reservas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pending.0.member_name', 'Miembro Visible')
            ->where('pending.0.party_size', 2)
            ->where('pending.0.confirmed_attendee_count', 4)
            ->missing('pending.0.email')
            ->missing('pending.0.member_email')
        );
    }

    public function test_partner_cannot_view_another_partners_member_data(): void
    {
        [$member, $experience, $session, $partner] = $this->reservable(true);
        $otherPartner = Partner::factory()->published()->create();
        $manager = User::factory()->create();
        $manager->partners()->attach($otherPartner, ['role' => 'manager']);
        ExperienceReservation::create(['user_id' => $member->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'pending', 'party_size' => 2]);

        $this->actingAs($manager)->get('/partner/'.$otherPartner->slug.'/reservas')->assertOk()->assertInertia(fn (Assert $page) => $page->has('pending', 0));
    }

    private function reservable(bool $membership = false): array
    {
        $partner = Partner::factory()->published()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create(['reservation_partner_id' => $partner->id]);
        $user = User::factory()->create();
        if ($membership) {
            app(MembershipService::class)->activate($user, User::factory()->create());
        }

        return [$user, $experience, $session, $partner];
    }
}
