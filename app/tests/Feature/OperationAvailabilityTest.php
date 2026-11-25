<?php

namespace Tests\Feature;

use App\Models\{Benefit, Challenge, Experience, ExperienceReservation, ExperienceSession, Location, Partner, Redemption, Unlock, UnlockParticipation, User};
use App\Services\{ChallengeService, ExperienceCheckInService, ExperienceReservationService, MembershipBankCheckout, MembershipPurchaseService, MembershipService, RedemptionService, RewardPayoutService, UnlockParticipationService, UnlockStatusService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationAvailabilityTest extends TestCase
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
        config(['jakawi.prelaunch_mode' => true, 'jakawi.analytics.enabled' => false]);
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

    public function test_own_configuration_preserves_operational_confirmation_capacity_location_or_unlock_eligibility(): void
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

    public function test_operations_preserve_membership_pin_partner_and_eligibility_validation(): void
    {
        $owner = $this->actor('owner');
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
