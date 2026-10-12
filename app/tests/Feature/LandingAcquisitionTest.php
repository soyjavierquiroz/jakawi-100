<?php

namespace Tests\Feature;

use App\Models\{Benefit, Challenge, Experience, ExperienceSession, LandingPresentation, Location, Partner, Unlock, User};
use App\Services\{ProductLandingResolver, PublicJourneyContinuation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private function subjects(): array
    {
        $partner = Partner::factory()->published()->create(['name' => 'Altura Nube Café']);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['title' => 'Café de cortesía con tu brunch', 'applies_to_all_locations' => true]);
        foreach (['Centro', 'Recoleta'] as $name) {
            $location = Location::factory()->published()->withPartner($partner)->create(['name' => $name]);
            $location->setRedemptionPin('123456');
            $location->save();
        }
        $experience = Experience::factory()->published()->create(['title' => 'Sunset JAKAWI']);
        ExperienceSession::factory()->for($experience)->upcoming()->create();
        $challenge = Challenge::create([
            'title' => 'Reto Cena de Ciudad', 'slug' => 'reto-cena', 'status' => 'open', 'review_status' => 'APPROVED',
            'description' => 'Comparte tu cena.', 'instructions' => 'Publica una foto.',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => [], 'required_mentions' => [],
            'evidence_type' => 'SOCIAL_POST', 'qualification_type' => 'VALID_EVIDENCE', 'selection_type' => 'ALL_QUALIFIED',
            'evaluation_mode' => 'CONTINUOUS', 'review_mode' => 'AUTOMATIC', 'participation_eligibility' => 'ALL_USERS',
            'reward_eligibility' => 'ALL_USERS', 'reward_type' => 'JP', 'reward_jp_amount' => 25,
        ]);
        $unlock = Unlock::create([
            'title' => 'Cena secreta', 'slug' => 'cena-secreta', 'status' => Unlock::ACTIVE,
            'minimum_commitments' => 10, 'free_user_eligible' => true, 'member_eligible' => true,
            'secret_mode' => true, 'hide_exact_offer_until_unlock' => true, 'hide_partner_until_unlock' => true,
            'hide_location_until_unlock' => true, 'description' => 'RESTRICTED-SOURCE-SENTINEL',
            'free_user_offer' => 'RESTRICTED-OFFER-SENTINEL', 'partner_id' => $partner->id,
        ]);

        return [$benefit, $experience, $challenge, $unlock];
    }

    private function landing($subject, string $scope = 'GUESTS'): LandingPresentation
    {
        return $subject->landingPresentations()->create([
            'name' => 'Acquisition QA', 'slug' => 'acquire-'.strtolower(class_basename($subject)).'-'.$subject->id,
            'status' => 'PUBLISHED', 'default_scope' => $scope,
        ]);
    }

    public function test_guest_native_routes_resolve_published_guest_or_all_default_and_preserve_attribution(): void
    {
        foreach ($this->subjects() as $index => $subject) {
            $landing = $this->landing($subject, $index % 2 ? 'ALL' : 'GUESTS');
            $this->get(app(ProductLandingResolver::class)->nativeUrl($subject).'?utm_source=qr&ref=abc&campaign_key=untrusted')
                ->assertRedirect(route('landing-presentations.show', $landing).'?utm_source=qr&ref=abc&campaign_key=untrusted');
        }
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_guest_native_routes_without_published_default_render_product_detail(): void
    {
        foreach ($this->subjects() as $subject) {
            $landing = $this->landing($subject, 'NONE');
            $landing->update(['status' => 'DRAFT']);
            $this->get(app(ProductLandingResolver::class)->nativeUrl($subject))->assertOk();
            $landing->update(['status' => 'PUBLISHED']);
            $this->get(app(ProductLandingResolver::class)->nativeUrl($subject))->assertOk();
        }
    }

    public function test_authenticated_free_and_active_members_resolve_all_landing_and_direct_landing_stays_landing(): void
    {
        $subjects = $this->subjects();
        foreach ([false, true] as $member) {
            $user = User::factory()->create();
            if ($member) \App\Models\Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'amount_paid' => 100]);
            foreach ($subjects as $subject) {
                $landing = $subject->landingPresentations()->first() ?? $this->landing($subject, 'ALL');
                $this->actingAs($user)->get(app(ProductLandingResolver::class)->nativeUrl($subject))->assertRedirect(route('landing-presentations.show', $landing));
                $this->assertSame(route('landing-presentations.show', $landing), app(ProductLandingResolver::class)->navigationUrl($subject, true));
                $this->get(route('landing-presentations.show', $landing))->assertOk()->assertInertia(fn (Assert $page) => $page->where('presentation.id', $landing->id));
            }
        }
    }

    public function test_authenticated_guest_only_default_and_no_default_use_product_detail(): void
    {
        $user = User::factory()->create();
        foreach ($this->subjects() as $subject) {
            $this->actingAs($user)->get(app(ProductLandingResolver::class)->nativeUrl($subject))->assertOk();
            $this->landing($subject, 'GUESTS');
            $this->get(app(ProductLandingResolver::class)->nativeUrl($subject))->assertOk();
            $this->assertSame(app(ProductLandingResolver::class)->nativeUrl($subject), app(ProductLandingResolver::class)->navigationUrl($subject, true));
        }
    }

    public function test_membership_request_preserves_landing_and_location_and_returns_after_explicit_completion(): void
    {
        $benefit = $this->subjects()[0];
        $landing = $this->landing($benefit, 'ALL');
        $location = $benefit->availableLocations()->first();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/membresia?'.http_build_query(['journey' => 'BENEFIT', 'action' => 'REDEEM', 'resource_id' => $benefit->id, 'location_id' => $location->id, 'landing_presentation_id' => $landing->id]))->assertOk();
        $this->assertSame($landing->id, app(PublicJourneyContinuation::class)->get()['landing_presentation_id']);
        $this->post(route('membership.request'))->assertRedirect(route('membership.show'));
        $purchase = \App\Models\MembershipPurchaseRequest::sole();
        $this->assertSame($landing->id, $purchase->intent()['landing_presentation_id']);
        $this->assertSame($location->id, $purchase->intent()['context']['location_id']);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('redemptions', 0);
        \App\Models\Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'amount_paid' => 100]);
        $purchase->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        $this->post(route('membership.return', $purchase))->assertRedirect(route('landing-presentations.activation', $landing, false));
        $this->get(route('landing-presentations.activation', $landing))->assertInertia(fn (Assert $page) => $page->where('acquisitionResumed', true)->where('selectedLocationId', $location->id)->where('hasActiveMembership', true));
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_experience_membership_continuation_preserves_landing_and_selected_session(): void
    {
        $experience = $this->subjects()[1];
        $landing = $this->landing($experience, 'ALL');
        $session = $experience->upcomingSessions()->first();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/membresia?'.http_build_query(['journey' => 'EXPERIENCE', 'action' => 'RESERVE', 'resource_id' => $experience->id, 'experience_session_id' => $session->id, 'landing_presentation_id' => $landing->id]))->assertOk();
        $intent = app(PublicJourneyContinuation::class)->get();
        $this->assertSame($landing->id, $intent['landing_presentation_id']);
        $this->assertSame(route('landing-presentations.activation', $landing, false), app(PublicJourneyContinuation::class)->destination($intent));
        $this->get(route('landing-presentations.activation', $landing))->assertInertia(fn (Assert $page) => $page->where('acquisitionResumed', true)->where('restoredSessionId', $session->id)->where('experience.id', $experience->id));
        $this->assertNoDomainMutation();
    }

    public function test_membership_rejects_a_landing_from_a_different_subject(): void
    {
        [$benefit, $experience] = $this->subjects();
        $landing = $this->landing($experience, 'ALL');
        $this->actingAs(User::factory()->create())->get('/membresia?'.http_build_query(['journey' => 'BENEFIT', 'action' => 'REDEEM', 'resource_id' => $benefit->id, 'landing_presentation_id' => $landing->id]))->assertSessionHasErrors('journey');
        $this->assertNoDomainMutation();
    }

    public function test_register_preserves_landing_subject_action_and_resumes_benefit_location_without_mutation(): void
    {
        $benefit = $this->subjects()[0];
        $landing = $this->landing($benefit);
        $this->post(route('landing-presentations.acquisition', $landing), ['method' => 'register'])->assertNoContent();
        $intent = app(PublicJourneyContinuation::class)->get();
        $this->assertSame($landing->id, $intent['landing_presentation_id']);
        $this->assertSame($benefit->id, $intent['resource_id']);
        $this->assertSame('REDEEM', $intent['action']);
        $this->post('/register', ['name' => 'Café Guest', 'email' => 'cafe@example.test', 'whatsapp' => '71234567', 'password' => 'Valid-Cafe-2026!'])
            ->assertRedirect(route('landing-presentations.activation', $landing, false));
        $user = User::where('email', 'cafe@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Hash::check('Valid-Cafe-2026!', $user->password));
        $this->get(route('landing-presentations.show', $landing))->assertInertia(fn (Assert $page) => $page
            ->where('acquisitionResumed', true)->where('benefit.id', $benefit->id)->has('locations', 2)->where('hasActiveMembership', false));
        $this->assertNoDomainMutation();
    }

    public function test_login_preserves_each_landing_context_without_reserving_joining_or_committing(): void
    {
        $user = User::factory()->create();
        foreach ($this->subjects() as $subject) {
            $landing = $this->landing($subject);
            $this->post(route('landing-presentations.acquisition', $landing), ['method' => 'login'])->assertNoContent();
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('landing-presentations.activation', $landing, false));
            $this->get(route('landing-presentations.show', $landing))->assertInertia(fn (Assert $page) => $page
                ->where('acquisitionResumed', true)->where('acquisition.subjectId', $subject->id)->where('acquisition.title', $subject->title));
            $this->post('/logout');
        }
        $this->assertNoDomainMutation();
    }

    public function test_challenge_registration_returns_to_the_same_landing_without_joining_or_bypassing_verification(): void
    {
        $challenge = $this->subjects()[2];
        $landing = $this->landing($challenge);
        $this->post(route('landing-presentations.acquisition', $landing), ['method' => 'register'])->assertNoContent();
        $this->post('/register', ['name' => 'Challenge Guest', 'email' => 'challenge@example.test', 'whatsapp' => '71234567', 'password' => 'Valid-Challenge-2026!'])
            ->assertRedirect(route('landing-presentations.activation', $landing, false));
        $this->get(route('landing-presentations.show', $landing))->assertInertia(fn (Assert $page) => $page
            ->where('acquisitionResumed', true)->where('challenge.id', $challenge->id)
            ->where('canSubmit', false)->where('participation', null));
        $this->post(route('social-challenges.intent', $challenge))->assertRedirect(route('verification.notice'));
        $this->assertNoDomainMutation();
    }

    public function test_contextual_registration_rejects_invalid_phone_and_invalid_login_keeps_intent(): void
    {
        $landing = $this->landing($this->subjects()[0]);
        $this->post(route('landing-presentations.acquisition', $landing), ['method' => 'register']);
        $this->from(route('landing-presentations.show', $landing))->post('/register', ['name' => 'Guest', 'email' => 'guest@example.test', 'whatsapp' => 'invalid'])->assertSessionHasErrors('whatsapp');
        $this->assertGuest();
        $this->from(route('landing-presentations.show', $landing))->post('/login', ['email' => 'guest@example.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertSame($landing->id, app(PublicJourneyContinuation::class)->get()['landing_presentation_id']);
        $this->assertNoDomainMutation();
    }

    public function test_acquisition_copy_fallback_and_saved_copy_are_projected_without_unlock_source(): void
    {
        foreach ($this->subjects() as $subject) {
            $landing = $this->landing($subject);
            if ($subject instanceof Unlock) {
                $landing->update(['vsl' => ['source' => ['type' => 'mp4', 'url' => 'https://cdn.example.test/RESTRICTED-VSL-SENTINEL.mp4']]]);
            }
            $this->get(route('landing-presentations.show', $landing))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('acquisition.title', $subject->title)->where('acquisition.headline', fn ($value) => str_contains($value, $subject->title)));
            $landing->update(['acquisition_headline' => 'Tu promesa está a un paso', 'acquisition_supporting_copy' => 'Crea tu cuenta y seguimos aquí.']);
            $response = $this->get(route('landing-presentations.show', $landing));
            $response->assertInertia(fn (Assert $page) => $page->where('acquisition.headline', 'Tu promesa está a un paso')->where('acquisition.supportingCopy', 'Crea tu cuenta y seguimos aquí.'));
            if ($subject instanceof Unlock) {
                $response->assertDontSee('RESTRICTED-SOURCE-SENTINEL')->assertDontSee('RESTRICTED-OFFER-SENTINEL')->assertDontSee('RESTRICTED-VSL-SENTINEL')
                    ->assertInertia(fn (Assert $page) => $page->where('acquisition.provider', null)->where('vsl', null));
            }
        }
    }

    public function test_stale_foreign_and_nonpublic_landing_context_cannot_resume(): void
    {
        [$benefit, $experience] = $this->subjects();
        $landing = $this->landing($benefit);
        $continuation = app(PublicJourneyContinuation::class);
        $continuation->setLanding($landing);
        $intent = $continuation->get();
        $this->assertNull($continuation->destination([...$intent, 'resource_id' => $experience->id, 'journey' => 'EXPERIENCE', 'action' => 'RESERVE']));
        $landing->update(['default_scope' => 'NONE', 'status' => 'DRAFT']);
        $this->assertNull($continuation->destination($intent));
        $this->post(route('landing-presentations.acquisition', $landing), ['method' => 'register'])->assertNotFound();
    }

    private function assertNoDomainMutation(): void
    {
        foreach (['redemptions', 'experience_reservations', 'challenge_participations', 'unlock_participations', 'memberships', 'jp_holds'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
