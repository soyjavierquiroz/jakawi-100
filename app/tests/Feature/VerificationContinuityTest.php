<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\LandingPresentation;
use App\Models\Partner;
use App\Models\Unlock;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerificationContinuityTest extends TestCase
{
    use RefreshDatabase;

    private function landing($subject): LandingPresentation
    {
        return $subject->landingPresentations()->create(['name' => 'Continuidad', 'slug' => 'continuity-'.$subject->id.'-'.class_basename($subject), 'status' => 'PUBLISHED', 'default_scope' => 'NONE']);
    }

    private function experience(): array
    {
        $partner = Partner::factory()->published()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
        $sessions = ExperienceSession::factory()->for($experience)->upcoming()->count(2)->create(['reservation_partner_id' => $partner->id]);
        $user = User::factory()->unverified()->create();
        app(MembershipService::class)->activate($user, User::factory()->create());
        return [$user, $experience, $sessions->last(), $this->landing($experience)];
    }

    private function verify(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $user->id, 'hash' => sha1($user->email)]);
    }

    public function test_internal_reservation_verifies_in_context_and_returns_selected_session_without_reserving(): void
    {
        Notification::fake();
        [$user, $experience, $session, $landing] = $this->experience();
        $activation = route('landing-presentations.activation', $landing, false);
        $this->actingAs($user)->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id, 'landing_presentation_id' => $landing->id])->assertRedirect($activation.'?verify=1');
        foreach ([1, 2] as $visit) {
            $this->get($activation.'?verify=1')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('verificationRequired', true)->where('experience.id', $experience->id)->where('restoredSessionId', $session->id));
        }
        $this->post(route('verification.send'))->assertRedirect();
        $this->withSession(['url.intended' => 'https://evil.test'])->get($this->verify($user))->assertRedirect($activation);
        $this->get($activation)->assertInertia(fn (Assert $page) => $page->where('verificationRequired', false)->where('restoredSessionId', $session->id));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('experience_reservations', 0);
        // Only another explicit mutation creates a reservation.
        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id, 'landing_presentation_id' => $landing->id])->assertRedirect();
        $this->assertDatabaseCount('experience_reservations', 1);
    }

    public function test_internal_native_fallback_stays_protected_and_foreign_landing_or_session_cannot_be_used(): void
    {
        [$user, $experience, $session, $landing] = $this->experience();
        $this->actingAs($user)->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id])->assertRedirect(route('verification.notice'));
        $foreign = Experience::factory()->published()->create();
        $this->post(route('experiences.reservations.store', $foreign), ['experience_session_id' => $session->id, 'landing_presentation_id' => $landing->id])->assertNotFound();
        $otherSession = ExperienceSession::factory()->for($foreign)->upcoming()->create();
        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $otherSession->id, 'landing_presentation_id' => $landing->id])->assertStatus(422);
        $this->assertDatabaseCount('experience_reservations', 0);
    }

    public function test_external_result_is_viewable_unverified_without_local_reservation(): void
    {
        [$user, $experience, $session, $landing] = $this->experience();
        $experience->update(['reservation_method' => 'external', 'reservation_url' => 'https://example.test/reserve']);
        $this->actingAs($user)->get(route('landing-presentations.result', ['presentation' => $landing, 'session' => $session->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('externalReservationReady', true)->where('verificationRequired', false)->where('restoredSessionId', $session->id));
        $this->assertDatabaseCount('experience_reservations', 0);
    }

    public function test_unlock_returns_to_same_state_without_commit_jp_or_reveal(): void
    {
        Notification::fake();
        $unlock = Unlock::create(['title' => 'Unlock Continuidad', 'slug' => 'unlock-continuity', 'status' => Unlock::ACTIVE, 'minimum_commitments' => 3, 'free_user_eligible' => true, 'member_eligible' => true, 'jp_deposit' => 10, 'secret_mode' => true, 'hide_exact_offer_until_unlock' => true, 'description' => 'RESTRICTED-SOURCE']);
        $landing = $this->landing($unlock);
        $user = User::factory()->unverified()->create();
        $activation = route('landing-presentations.activation', $landing, false);
        $this->actingAs($user)->post(route('unlocks.commit', $unlock), ['landing_presentation_id' => $landing->id])->assertRedirect($activation.'?verify=1');
        $this->get($activation.'?verify=1')->assertOk()->assertDontSee('RESTRICTED-SOURCE')->assertInertia(fn (Assert $page) => $page
            ->where('verificationRequired', true)->where('unlock.id', $unlock->id)->where('unlock.jp_deposit', 10)->where('participation', null)->where('unlock.revealable', false));
        $this->post(route('verification.send'))->assertRedirect();
        $this->get($this->verify($user))->assertRedirect($activation);
        $this->get($activation)->assertDontSee('RESTRICTED-SOURCE')->assertInertia(fn (Assert $page) => $page->where('verificationRequired', false)->where('participation', null)->where('unlock.revealable', false));
        $this->assertDatabaseCount('unlock_participations', 0);
        $this->assertDatabaseCount('jp_holds', 0);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertSame(Unlock::ACTIVE, $unlock->fresh()->status);
    }

    public function test_unlock_existing_state_and_intended_action_survive_without_replaying_mutations(): void
    {
        $unlock = Unlock::create(['title' => 'Existing Unlock', 'slug' => 'existing-unlock', 'status' => Unlock::ACTIVE, 'minimum_commitments' => 3, 'free_user_eligible' => true]);
        $landing = $this->landing($unlock);
        $user = User::factory()->unverified()->create();
        $participation = $user->unlockParticipations()->create(['unlock_id' => $unlock->id, 'status' => 'COMMITTED', 'committed_at' => now()]);
        $this->actingAs($user);
        foreach (['interest', 'commit', 'cancel', 'confirm'] as $action) {
            $this->post(route('unlocks.'.$action, $unlock))->assertRedirect(route('verification.notice'));
        }
        $activation = route('landing-presentations.activation', $landing, false);
        $this->post(route('unlocks.cancel', $unlock), ['landing_presentation_id' => $landing->id])->assertRedirect($activation.'?verify=1');
        $this->get($activation.'?verify=1')->assertInertia(fn (Assert $page) => $page->where('participation.status', 'COMMITTED'));
        $this->assertSame('CANCEL', app(\App\Services\PublicJourneyContinuation::class)->get()['action']);
        $this->get($this->verify($user))->assertRedirect($activation);
        $this->assertSame('COMMITTED', $participation->fresh()->status);
        $this->assertDatabaseCount('unlock_participations', 1);
        $this->assertDatabaseCount('jp_holds', 0);
    }

    public function test_invalid_verification_proof_does_not_enable_mutation(): void
    {
        [$user, $experience, $session, $landing] = $this->experience();
        $this->actingAs($user)->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id, 'landing_presentation_id' => $landing->id]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $user->id, 'hash' => sha1('someone-else@example.test')]);
        $this->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('experience_reservations', 0);
    }
}
