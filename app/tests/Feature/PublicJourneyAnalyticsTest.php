<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\ExperienceReservation;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Unlock;
use App\Models\User;
use App\Services\AnalyticsTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicJourneyAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_views_do_not_start_intent_and_guest_actions_track_each_journey_and_auth_start(): void
    {
        [$experience, $session] = $this->experience();
        [$benefit] = $this->benefit();
        $unlock = $this->unlock();

        $this->get(route('experiences.show', $experience))->assertOk();
        $this->get(route('benefits.show', $benefit))->assertOk();
        $this->get(route('unlocks.show', $unlock))->assertOk();
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'journey_intent_started')->count());

        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id])->assertRedirect(route('register'));
        $this->post(route('unlocks.commit', $unlock))->assertRedirect(route('register'));
        $this->post(route('redemptions.start', $benefit))->assertRedirect(route('register'));

        $this->assertSame(3, AnalyticsEvent::where('event_name', 'journey_intent_started')->count());
        $this->assertSame(3, AnalyticsEvent::where('event_name', 'journey_auth_started')->count());
        foreach (AnalyticsEvent::where('event_name', 'journey_intent_started')->get() as $event) {
            $this->assertSame('guest', $event->metadata['auth_state']);
            $this->assertArrayNotHasKey('membership_state', $event->metadata);
            $this->assertArrayHasKey('resource_slug', $event->metadata);
        }
        foreach (AnalyticsEvent::where('event_name', 'journey_auth_started')->get() as $event) {
            $this->assertSame('register', $event->metadata['auth_destination']);
        }
    }

    public function test_register_and_login_return_only_when_valid_continuation_is_consumed(): void
    {
        [$experience, $session] = $this->experience();
        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id]);
        $this->post('/register', ['name' => 'Journey User', 'email' => 'journey@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect(route('experiences.show', $experience->slug, false));
        $event = AnalyticsEvent::where('event_name', 'journey_auth_returned')->sole();
        $this->assertEquals(['journey' => 'EXPERIENCE', 'action' => 'RESERVE', 'resource_id' => $experience->id,
            'auth_method' => 'register', 'has_session_context' => true], $event->metadata);

        $this->post(route('logout'));
        $unlock = $this->unlock();
        $existingUser = User::factory()->create();
        $this->post(route('unlocks.commit', $unlock));
        $this->post(route('login.store'), ['email' => $existingUser->email, 'password' => 'password'])
            ->assertRedirect(route('unlocks.show', $unlock->slug, false));
        $this->assertSame('login', AnalyticsEvent::where('event_name', 'journey_auth_returned')->latest('id')->firstOrFail()->metadata['auth_method']);
        $this->assertSame(2, AnalyticsEvent::where('event_name', 'journey_auth_returned')->count());

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $existingUser->email, 'password' => 'password'])->assertRedirect();
        $this->assertSame(2, AnalyticsEvent::where('event_name', 'journey_auth_returned')->count());
    }

    public function test_normal_registration_does_not_emit_auth_return(): void
    {
        $this->post('/register', ['name' => 'Normal User', 'email' => 'normal@example.test', 'whatsapp' => '71234567'])->assertRedirect();
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'journey_auth_returned')->count());
    }

    public function test_auth_return_tracking_failure_does_not_block_product_redirect(): void
    {
        $user = User::factory()->create();
        $unlock = $this->unlock();
        $this->post(route('unlocks.commit', $unlock))->assertRedirect(route('register'));
        $tracker = \Mockery::mock(AnalyticsTracker::class);
        $tracker->shouldReceive('journeyAuthReturned')->once()->andThrow(new \RuntimeException('tracker unavailable'));
        app()->instance(AnalyticsTracker::class, $tracker);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('unlocks.show', $unlock->slug, false));
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'journey_auth_returned')->count());
    }

    public function test_membership_gates_follow_existing_flags_and_member_state(): void
    {
        [$experience] = $this->experience();
        [$benefit] = $this->benefit();
        $required = $this->unlock(['free_user_eligible' => false, 'member_eligible' => true]);
        $free = $this->unlock(['free_user_eligible' => true, 'member_eligible' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('experiences.show', $experience))->assertOk();
        $this->get(route('benefits.show', $benefit))->assertOk();
        $this->get(route('unlocks.show', $required))->assertOk();
        $this->get(route('unlocks.show', $free))->assertOk();
        $this->assertSame(3, AnalyticsEvent::where('event_name', 'journey_membership_gate_viewed')->count());
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'journey_intent_started')->count());
        foreach (AnalyticsEvent::where('event_name', 'journey_membership_gate_viewed')->get() as $event) {
            $this->assertSame('inactive', $event->metadata['membership_state']);
        }

        Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'amount_paid' => 100]);
        $this->get(route('experiences.show', $experience))->assertOk();
        $this->get(route('benefits.show', $benefit))->assertOk();
        $this->get(route('unlocks.show', $required))->assertOk();
        $this->assertSame(3, AnalyticsEvent::where('event_name', 'journey_membership_gate_viewed')->count());
    }

    public function test_membership_link_intent_is_explicit_and_deduplicated(): void
    {
        [$experience] = $this->experience();
        [$benefit] = $this->benefit();
        $unlock = $this->unlock(['free_user_eligible' => false, 'member_eligible' => true]);
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([['experience', $experience], ['benefit', $benefit], ['unlock', $unlock]] as [$journey, $resource]) {
            $this->post("/analytics/journey-intent/{$journey}/{$resource->slug}")->assertNoContent();
            $this->post("/analytics/journey-intent/{$journey}/{$resource->slug}")->assertNoContent();
        }
        $this->assertSame(3, AnalyticsEvent::where('event_name', 'journey_intent_started')->count());
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'journey_auth_started')->count());
        Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'amount_paid' => 100]);
        $this->post("/analytics/journey-intent/experience/{$experience->slug}")->assertNotFound();
        $this->get(route('benefits.show', $benefit))->assertOk();
        $this->post("/analytics/journey-intent/benefit/{$benefit->slug}")->assertNoContent();
        $this->assertSame('active', AnalyticsEvent::where('event_name', 'journey_intent_started')->latest('id')->firstOrFail()->metadata['membership_state']);
    }

    public function test_external_modal_click_starts_one_intent_before_exit(): void
    {
        $experience = Experience::factory()->published()->create(['reservation_method' => 'url', 'reservation_url' => 'https://book.example.test/private']);
        ExperienceSession::factory()->for($experience)->upcoming()->create();
        $this->get(route('experiences.show', $experience))->assertOk();

        $this->post("/analytics/journey-intent/experience/{$experience->slug}")->assertNoContent();
        $this->get(route('experiences.reserve', ['experience' => $experience, 'method' => 'url']))->assertRedirect('https://book.example.test/private');
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'journey_intent_started')->count());
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'journey_external_exit')->count());
    }

    public function test_authenticated_actions_track_intent_and_preserve_domain_event_meanings(): void
    {
        [$experience, $session] = $this->experience();
        [$benefit, $location] = $this->benefit();
        $unlock = $this->unlock();
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'amount_paid' => 100]);
        $this->actingAs($user);

        $this->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id])->assertRedirect();
        $this->post(route('unlocks.commit', $unlock))->assertRedirect();
        $this->post(route('redemptions.start', $benefit), ['location_id' => $location->id])->assertRedirect();

        $this->assertSame(3, AnalyticsEvent::where('event_name', 'journey_intent_started')->count());
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'journey_auth_started')->count());
        $this->assertSame('pending', ExperienceReservation::where('experience_id', $experience->id)->sole()->status);
        $this->assertSame('jakawi', AnalyticsEvent::where('event_name', 'experience_reserve_click')->sole()->metadata['reservation_method']);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'unlock_commitment_started')->count());
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'unlock_committed')->count());
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'redeem_started')->count());
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'redeem_confirmed')->count());
        $this->assertSame(0, AnalyticsEvent::where('event_name', 'journey_action_completed')->count());
    }

    public function test_external_exits_are_explicit_and_metadata_contains_no_destination_or_pii(): void
    {
        $experience = Experience::factory()->published()->create([
            'reservation_method' => 'whatsapp', 'reservation_whatsapp' => '+59170000000',
            'reservation_url' => 'https://book.example.test/private?email=private@example.test',
            'reservation_phone' => '+59171111111',
        ]);
        ExperienceSession::factory()->for($experience)->upcoming()->create();

        foreach (['whatsapp' => 'https://wa.me/59170000000', 'url' => 'https://book.example.test/private?email=private@example.test', 'phone' => 'tel:+59171111111'] as $method => $destination) {
            $this->get(route('experiences.reserve', ['experience' => $experience, 'method' => $method]))->assertRedirect($destination);
        }
        $exits = AnalyticsEvent::where('event_name', 'journey_external_exit')->get();
        $this->assertSame(['whatsapp', 'url', 'phone'], $exits->pluck('metadata')->map(fn ($metadata) => $metadata['destination_type'])->all());
        $this->assertSame(3, AnalyticsEvent::where('event_name', 'experience_reserve_click')->count());
        foreach ($exits as $event) {
            $this->assertEqualsCanonicalizing(['journey', 'action', 'resource_id', 'destination_type'], array_keys($event->metadata));
            $json = json_encode($event->metadata);
            foreach (['email', 'whatsapp', 'phone', 'https://', '+591', 'name'] as $private) {
                if (in_array($private, ['whatsapp', 'phone'], true)) continue;
                $this->assertStringNotContainsString($private, $json);
            }
        }
    }

    /** @return array{Experience,ExperienceSession} */
    private function experience(): array
    {
        $partner = Partner::factory()->published()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
        return [$experience, ExperienceSession::factory()->for($experience)->upcoming()->create(['reservation_partner_id' => $partner->id])];
    }

    /** @return array{Benefit,Location} */
    private function benefit(): array
    {
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        return [$benefit, $location];
    }

    private function unlock(array $attributes = []): Unlock
    {
        return Unlock::create(array_merge([
            'title' => 'Journey '.uniqid(), 'slug' => 'journey-'.uniqid(), 'origin' => 'JAKAWI',
            'type' => 'BENEFIT', 'minimum_commitments' => 5, 'free_user_eligible' => true,
            'member_eligible' => true, 'jp_deposit' => 0, 'jp_completion_bonus' => 0, 'status' => Unlock::ACTIVE,
        ], $attributes));
    }
}
