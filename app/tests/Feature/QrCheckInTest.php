<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class QrCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_reservation_can_be_reviewed_and_checked_in_once_by_its_partner(): void
    {
        [$reservation, $partner, $manager] = $this->reservation();
        $url = URL::signedRoute('partner.checkins.scan', ['partner' => $partner->slug, 'reservation_public_id' => $reservation->public_id]);
        $this->actingAs($manager)->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('reservation.member_name', $reservation->user->name)->where('reservation.party_size', 2));
        $this->actingAs($manager)->post('/partner/'.$partner->slug.'/asistencias/'.$reservation->public_id)->assertOk();
        $checked = $reservation->fresh();
        $this->assertNotNull($checked->checked_in_at);
        $this->assertSame($manager->id, $checked->checked_in_by_user_id);
        $this->actingAs($manager)->post('/partner/'.$partner->slug.'/asistencias/'.$reservation->public_id)->assertOk();
        $this->assertSame($checked->checked_in_at->toDateTimeString(), $reservation->fresh()->checked_in_at->toDateTimeString());
    }

    public function test_manual_code_is_partner_scoped_and_wrong_partner_is_forbidden(): void
    {
        [$reservation, $partner, $manager] = $this->reservation();
        $other = Partner::factory()->published()->create();
        $intruder = User::factory()->create(); $intruder->partners()->attach($other, ['role' => 'staff']);
        $this->actingAs($manager)->post('/partner/'.$partner->slug.'/asistencias', ['code' => $reservation->check_in_code])->assertOk();
        $this->actingAs($intruder)->get(URL::signedRoute('partner.checkins.scan', ['partner' => $other->slug, 'reservation_public_id' => $reservation->public_id]))->assertForbidden();
    }

    private function reservation(): array
    {
        $partner = Partner::factory()->published()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'host']]);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create(['reservation_partner_id' => $partner->id]);
        $member = User::factory()->create();
        $reservation = ExperienceReservation::create(['user_id' => $member->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'confirmed', 'party_size' => 2, 'check_in_code' => 'ABCDEF']);
        $manager = User::factory()->create(); $manager->partners()->attach($partner, ['role' => 'staff']);
        return [$reservation, $partner, $manager];
    }
}
