<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\Benefit;
use App\Models\Redemption;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\User;
use App\Services\PublicJourneyContinuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicJourneyWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_reserve_returns_to_experience_after_signup_without_reserving(): void
    {
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create();

        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id])
            ->assertRedirect(route('register'));
        $this->assertSame(['journey' => 'EXPERIENCE', 'resource_id' => $experience->id, 'action' => 'RESERVE', 'context' => ['experience_session_id' => $session->id]], app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, ExperienceReservation::count());
        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect(route('experiences.show', $experience->slug, false));
        $this->assertAuthenticated();
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, ExperienceReservation::count());
        $this->get(route('experiences.show', $experience))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('restoredSessionId', $session->id));
        $this->get(route('experiences.show', $experience))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('restoredSessionId', null));
    }

    public function test_guest_commit_returns_to_unlock_after_signup_without_committing(): void
    {
        $unlock = $this->unlock();

        $this->post(route('unlocks.commit', $unlock))->assertRedirect(route('register'));
        $this->assertSame(['journey' => 'UNLOCK', 'resource_id' => $unlock->id, 'action' => 'COMMIT'], app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, UnlockParticipation::count());

        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect(route('unlocks.show', $unlock->slug, false));
        $this->assertAuthenticated();
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, UnlockParticipation::count());
    }

    public function test_existing_user_returns_to_resource_after_login_without_executing_action(): void
    {
        $user = User::factory()->create();
        $unlock = $this->unlock();

        $this->post(route('unlocks.commit', $unlock))->assertRedirect(route('register'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('unlocks.show', $unlock->slug, false));
        $this->assertAuthenticatedAs($user);
        $this->assertNull(app(PublicJourneyContinuation::class)->consume());
        $this->assertSame(0, UnlockParticipation::count());

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/mi-jakawi');
    }

    public function test_existing_user_returns_to_experience_after_login_without_reserving(): void
    {
        $user = User::factory()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create();

        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id])
            ->assertRedirect(route('register'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('experiences.show', $experience->slug, false));
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, ExperienceReservation::count());
        $this->get(route('experiences.show', $experience))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('restoredSessionId', $session->id));
    }

    public function test_foreign_or_expired_experience_session_is_not_restored(): void
    {
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $foreign = ExperienceSession::factory()->for(Experience::factory()->published()->create())->upcoming()->create();
        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $foreign->id])->assertRedirect(route('register'));
        $this->assertArrayNotHasKey('context', app(PublicJourneyContinuation::class)->get());

        $session = ExperienceSession::factory()->for($experience)->upcoming()->create();
        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id]);
        $session->update(['status' => 'cancelled']);
        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.test', 'whatsapp' => '71234567'])->assertRedirect(route('experiences.show', $experience->slug, false));
        $this->get(route('experiences.show', $experience))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('restoredSessionId', null));
        $this->assertSame(0, ExperienceReservation::count());
    }

    public function test_guest_benefit_intent_returns_after_signup_without_redeeming(): void
    {
        $benefit = Benefit::factory()->published()->create();
        $this->post(route('redemptions.start', $benefit))->assertRedirect(route('register'));
        $this->assertSame(['journey' => 'BENEFIT', 'resource_id' => $benefit->id, 'action' => 'REDEEM'], app(PublicJourneyContinuation::class)->get());
        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.test', 'whatsapp' => '71234567'])->assertRedirect(route('benefits.show', $benefit->slug, false));
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, Redemption::count());
        $this->get(route('benefits.show', $benefit))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('hasActiveMembership', false));
    }

    public function test_deleted_benefit_continuation_falls_back_safely(): void
    {
        $benefit = Benefit::factory()->published()->create();
        $this->post(route('redemptions.start', $benefit))->assertRedirect(route('register'));
        $benefit->delete();
        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.test', 'whatsapp' => '71234567'])->assertRedirect('/dashboard');
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, Redemption::count());
    }

    public function test_existing_user_returns_to_benefit_after_login_without_redeeming(): void
    {
        $user = User::factory()->create();
        $benefit = Benefit::factory()->published()->create();
        $this->post(route('redemptions.start', $benefit))->assertRedirect(route('register'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('benefits.show', $benefit->slug, false));
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, Redemption::count());
    }

    public function test_unlock_detail_exposes_existing_eligibility_and_jp_values(): void
    {
        $unlock = $this->unlock(['free_user_eligible' => false, 'member_eligible' => true, 'jp_deposit' => 5]);
        $this->get(route('unlocks.show', $unlock))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('unlock.free_user_eligible', false)
            ->where('unlock.member_eligible', true)
            ->where('unlock.jp_deposit', 5)
        );
        $this->actingAs(User::factory()->create())->get(route('unlocks.show', $unlock))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('hasActiveMembership', false)
            ->where('jpBalance.spendable', 0)
        );
        $this->assertSame(0, UnlockParticipation::count());
    }

    public function test_missing_and_closed_resources_do_not_create_a_continuation(): void
    {
        $closed = $this->unlock(['status' => Unlock::CANCELLED]);
        $this->post('/d/missing-unlock/comprometer')->assertRedirect(route('login'));
        $this->post(route('unlocks.commit', $closed))->assertRedirect(route('login'));
        $this->post('/experiencias/missing-experience/reservas', ['experience_session_id' => 1])->assertRedirect(route('login'));
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, UnlockParticipation::count());
        $this->assertSame(0, ExperienceReservation::count());
    }

    public function test_resource_removed_after_capture_falls_back_safely(): void
    {
        $unlock = $this->unlock();
        $this->post(route('unlocks.commit', $unlock))->assertRedirect(route('register'));
        $unlock->delete();

        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect('/dashboard');
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, UnlockParticipation::count());
    }

    private function unlock(array $attributes = []): Unlock
    {
        return Unlock::create(array_merge([
            'title' => 'Unlock test', 'slug' => 'unlock-test', 'origin' => 'JAKAWI', 'type' => 'BENEFIT',
            'minimum_commitments' => 5, 'free_user_eligible' => true, 'member_eligible' => true,
            'jp_deposit' => 0, 'status' => Unlock::ACTIVE,
        ], $attributes));
    }
}
