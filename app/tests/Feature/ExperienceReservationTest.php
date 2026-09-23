<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\Partner;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertDatabaseHas('experience_reservations', ['user_id' => $user->id, 'status' => 'pending']);
        $this->assertSame(1, \App\Models\AnalyticsEvent::where('event_name', 'experience_reserve_click')->count());
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
        $manager = User::factory()->create(); $manager->partners()->attach($partner, ['role' => 'manager']);
        $other = Partner::factory()->published()->create(); $intruder = User::factory()->create(); $intruder->partners()->attach($other, ['role' => 'manager']);
        $this->actingAs($intruder)->post("/partner/reservas/{$reservation->public_id}/confirmed")->assertForbidden();
        $this->actingAs($manager)->post("/partner/reservas/{$reservation->public_id}/confirmed")->assertRedirect();
        $this->assertDatabaseHas('experience_reservations', ['id' => $reservation->id, 'status' => 'confirmed', 'responded_by_user_id' => $manager->id]);
        $this->actingAs($member)->post("/reservas/{$reservation->public_id}/cancelar")->assertRedirect();
        $this->assertDatabaseHas('experience_reservations', ['id' => $reservation->id, 'status' => 'cancelled']);
        $this->actingAs(User::factory()->create())->get('/partner/reservas')->assertForbidden();
    }

    private function reservable(bool $membership = false): array
    {
        $partner = Partner::factory()->published()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create(['reservation_partner_id' => $partner->id]);
        $user = User::factory()->create();
        if ($membership) app(MembershipService::class)->activate($user, User::factory()->create());
        return [$user, $experience, $session, $partner];
    }
}
