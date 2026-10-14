<?php

namespace Tests\Feature;

use App\Models\{AttributionTouch, Benefit, Challenge, Experience, ExperienceSession, LandingPresentation, Location, Partner, ReferralRelationship, RewardRule, RewardTransaction, User};
use App\Services\{MembershipPurchaseService, AttributionService};
use App\Support\PartnerSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(): array
    {
        config(['jakawi.prelaunch_mode' => true]);
        $partner = Partner::factory()->published()->create(['slug' => 'dosis43', 'name' => 'DOSIS 43 — QA', 'description' => 'Café de evaluación aislada.']);
        $owner = User::factory()->create();
        $partner->users()->attach($owner, ['role' => 'owner']);
        $location = Location::factory()->published()->withPartner($partner)->create(['name' => 'DOSIS Centro QA', 'city' => 'Cochabamba']);
        $location->setRedemptionPin('123456'); $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['slug' => 'dosis-benefit-qa', 'title' => 'Café para compartir QA', 'applies_to_all_locations' => true, 'access_mode' => 'public']);
        $experience = Experience::factory()->published()->create(['title' => 'Cata de café QA']);
        $experience->partners()->attach($partner, ['role' => 'organizer']);
        ExperienceSession::factory()->for($experience)->upcoming()->create(['location_id' => $location->id]);
        $challenge = Challenge::create(['partner_id' => $partner->id, 'title' => 'Reto del café QA', 'slug' => 'dosis-challenge-qa', 'status' => 'open', 'review_status' => 'APPROVED', 'reward_type' => 'JP', 'reward_jp_amount' => 25]);
        $landing = $benefit->landingPresentations()->create(['name' => 'Dosis Benefit QA', 'slug' => 'dosis-benefit-landing-qa', 'status' => 'PUBLISHED', 'default_scope' => 'ALL']);
        return compact('partner', 'owner', 'location', 'benefit', 'experience', 'challenge', 'landing');
    }

    private function signup(string $email = 'dosis-new@example.test'): User
    {
        $this->post('/register', ['name' => 'Dosis New QA', 'email' => $email, 'whatsapp' => '71234567'])->assertRedirect();
        return User::whereEmail($email)->sole();
    }

    public function test_public_identity_inventory_locations_canonical_and_prelaunch(): void
    {
        extract($this->inventory());
        $this->get('/dosis43')->assertOk()->assertInertia(fn (Assert $p) => $p->component('partners/show')
            ->where('canonicalUrl', config('app.url').'/dosis43')->where('partner.name', $partner->name)
            ->where('partner.description', $partner->description)->where('locations.0.name', $location->name)
            ->where('benefits.0.title', $benefit->title)->where('benefits.0.destination_url', route('landing-presentations.show', $landing))
            ->where('experiences.0.title', $experience->title)->where('challenges.0.title', $challenge->title)
            ->where('prelaunchPublic', true)->where('auth.user', null));
        $this->assertSame($owner->id, $partner->users()->wherePivot('role', 'owner')->sole()->id);
        $this->get('/p/dosis43')->assertRedirect('/dosis43')->assertStatus(301);
        $this->get('/partners/dosis43')->assertRedirect('/dosis43')->assertStatus(301);
        $this->get('/unknown-partner')->assertNotFound();
        $partner->update(['status' => 'paused']);
        $this->get('/dosis43')->assertNotFound(); $this->get('/p/dosis43')->assertNotFound();
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_partner_media_projection_preserves_logo_proportions_and_optimizes_cover(): void
    {
        config(['media.imgproxy_url' => 'https://image.test', 'media.imgproxy_key' => str_repeat('ab', 32), 'media.imgproxy_salt' => str_repeat('cd', 32)]);
        $partner = Partner::factory()->published()->create([
            'slug' => 'brand-media', 'logo_path' => 'partners/1/logo/11111111-1111-4111-8111-111111111111.webp',
            'cover_path' => 'partners/1/cover/22222222-2222-4222-8222-222222222222.webp',
        ]);
        $this->get('/brand-media')->assertInertia(fn (Assert $p) => $p
            ->where('partner.logo_url', fn ($url) => str_contains($url, '/rs:fit:480:320/'))
            ->where('partner.cover_url', fn ($url) => str_contains($url, '/rs:fill:960:640/'))
            ->has('partner.logo_srcset', 2)->has('partner.cover_srcset', 4));
        $partner->update(['logo_path' => null, 'cover_path' => null]);
        $this->get('/brand-media')->assertInertia(fn (Assert $p) => $p
            ->where('partner.logo_url', null)->where('partner.cover_url', null)
            ->has('partner.logo_srcset', 0)->has('partner.cover_srcset', 0));
    }

    public function test_empty_profile_and_only_current_public_inventory(): void
    {
        extract($this->inventory());
        $benefit->update(['ends_at' => now()->subDay()]);
        $experience->update(['status' => 'paused']);
        $challenge->update(['published_until' => now()->subDay()]);
        $location->update(['status' => 'paused']);
        $this->get('/dosis43')->assertOk()->assertInertia(fn (Assert $p) => $p->has('benefits', 0)->has('experiences', 0)->has('challenges', 0)->has('locations', 0));
    }

    public function test_reserved_rule_covers_registered_routes_and_invalid_slugs_on_creation_and_change(): void
    {
        foreach (PartnerSlug::reserved() as $slug) $this->assertFalse(PartnerSlug::valid($slug), $slug);
        foreach (['admin', 'login', 'p', 'partners', 'forgot-password', 'afiliados', 'Dosis43', 'dosis_43', '-dosis', 'dosis--43'] as $slug) {
            try { Partner::factory()->create(['slug' => $slug]); $this->fail('Accepted '.$slug); }
            catch (ValidationException $e) { $this->assertArrayHasKey('slug', $e->errors()); }
        }
        $partner = Partner::factory()->published()->create(['slug' => 'safe-profile']);
        $this->expectException(ValidationException::class); $partner->update(['slug' => 'api']);
    }

    public function test_primary_downstream_new_signup_owns_partner_without_partner_signup_button_and_rewards_only_on_purchase(): void
    {
        extract($this->inventory());
        $this->get('/dosis43')->assertOk();
        $touch = AttributionTouch::sole(); $this->assertSame($partner->id, $touch->acquisition_partner_id);
        $this->assertNull($touch->user_id); $this->assertDatabaseCount('referral_relationships', 0); $this->assertDatabaseCount('reward_transactions', 0);
        $this->get('/beneficios/'.$benefit->slug)->assertRedirect(route('landing-presentations.show', $landing));
        $this->get('/l/'.$landing->slug)->assertOk();
        $this->post('/l/'.$landing->slug.'/acquisition', ['method' => 'register'])->assertNoContent();
        $user = $this->signup();
        $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $user->id, 'acquisition_partner_id' => $partner->id, 'attribution_touch_id' => $touch->id]);
        $this->assertDatabaseHas('attribution_touches', ['id' => $touch->id, 'user_id' => $user->id]);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->get('/dosis43')->assertInertia(fn (Assert $p) => $p->where('auth.user.id', $user->id)->has('benefits', 1));
        $this->assertDatabaseCount('attribution_touches', 1);
        RewardRule::create(['name' => 'Existing Partner QA rule', 'participant_type' => 'PARTNER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 10, 'currency' => 'BOB', 'status' => 'active']);
        $admin = User::factory()->create(['is_admin' => true]);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($user, $admin, null, 'PROFILE-QA', (string) \Illuminate\Support\Str::uuid());
        $reward = RewardTransaction::sole(); $this->assertSame($partner->id, $reward->beneficiary_id); $this->assertSame('10.00', $reward->amount);
        $this->assertSame('membership_purchased', $sale->conversion->type);
    }

    public function test_direct_signup_returns_to_public_profile(): void
    {
        extract($this->inventory());
        $this->get('/dosis43'); $this->get('/p/dosis43/auth/register')->assertRedirect('/register');
        $this->post('/register', ['name' => 'Direct QA', 'email' => 'direct@example.test', 'whatsapp' => '71234567'])->assertRedirect('/dosis43');
        $user = User::whereEmail('direct@example.test')->sole();
        $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $user->id, 'acquisition_partner_id' => $partner->id]);
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_existing_login_consumes_candidate_without_ownership_even_after_logout_and_new_registration(): void
    {
        extract($this->inventory()); $user = User::factory()->create();
        $this->get('/dosis43'); $touch = AttributionTouch::sole();
        $this->get('/p/dosis43/auth/login')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dosis43')->assertSessionMissing('attribution_touch_ids');
        $this->assertDatabaseCount('referral_relationships', 0); $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertSame($user->id, $touch->fresh()->user_id);
        $this->get('/dosis43')->assertInertia(fn (Assert $p) => $p->where('auth.user.id', $user->id));
        $this->assertDatabaseCount('attribution_touches', 1);
        $this->post('/logout'); $this->signup('later@example.test');
        $this->assertDatabaseCount('referral_relationships', 0);
    }

    public function test_existing_identity_cannot_signup_and_existing_owner_is_never_reassigned(): void
    {
        extract($this->inventory()); $other = Partner::factory()->published()->create(); $user = User::factory()->create();
        $touch = AttributionTouch::create(['acquisition_partner_id' => $other->id, 'occurred_at' => now(), 'user_id' => $user->id]);
        $relationship = app(AttributionService::class)->applyFirstValidReferrer($user, $touch);
        $this->get('/dosis43'); $this->get('/p/dosis43/auth/register');
        $this->post('/register', ['name' => 'Existing', 'email' => $user->email, 'whatsapp' => '71234567'])->assertSessionHasErrors('email');
        $this->get('/p/dosis43/auth/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dosis43');
        $relationship->update(['expires_at' => now()->subDay()]);
        $this->get('/dosis43');
        $this->assertSame($other->id, $relationship->fresh()->acquisition_partner_id); $this->assertDatabaseCount('referral_relationships', 1);
    }

    public function test_first_valid_partner_wins_even_when_landing_latest_touch_is_different(): void
    {
        extract($this->inventory()); $other = Partner::factory()->published()->create(['slug' => 'second-partner']);
        $this->get('/dosis43'); $this->get('/second-partner');
        $this->get('/l/'.$landing->slug.'?utm_source=poster');
        $user = $this->signup();
        $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $user->id, 'acquisition_partner_id' => $partner->id]);
    }

    public function test_stale_and_unscoped_anonymous_touches_do_not_become_new_ownership(): void
    {
        $partner = Partner::factory()->published()->create();
        $touch = AttributionTouch::create(['acquisition_partner_id' => $partner->id, 'occurred_at' => now()->subDays(31)]);
        $this->withSession(['attribution_touch_ids' => [$touch->id]]); $this->signup();
        $this->assertDatabaseCount('referral_relationships', 0);
    }

    public function test_partner_management_shows_public_url_and_other_accounts_cannot_manage_it(): void
    {
        extract($this->inventory());
        $this->actingAs($owner)->get('/partner/dosis43')->assertInertia(fn (Assert $p) => $p->where('partner.public_url', route('partners.public', $partner)));
        $this->actingAs(User::factory()->create())->get('/partner/dosis43')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/partners/'.$partner->id.'/edit')->assertInertia(fn (Assert $p) => $p->where('publicUrl', route('partners.public', $partner)));
        $data = $partner->only(['name', 'slug', 'status', 'entity_type', 'partner_type', 'category']);
        $this->put('/admin/partners/'.$partner->id, [...$data, 'slug' => 'login'])->assertSessionHasErrors('slug');
        $this->assertSame('dosis43', $partner->fresh()->slug);
        $this->put('/admin/partners/'.$partner->id, [...$data, 'description' => 'Perfil actualizado QA'])->assertRedirect('/admin/partners');
        $this->assertSame('Perfil actualizado QA', $partner->fresh()->description);
    }
    public function test_partner_inventory_and_generic_entry_share_landing_first_for_both_audiences(): void
    {
        extract($this->inventory());
        $experienceLanding = $experience->landingPresentations()->create(['name' => 'Experience', 'slug' => 'experience-primary', 'status' => 'PUBLISHED', 'default_scope' => 'ALL']);
        $challengeLanding = $challenge->landingPresentations()->create(['name' => 'Challenge', 'slug' => 'challenge-primary', 'status' => 'PUBLISHED', 'default_scope' => 'ALL']);
        foreach ([false, true] as $authenticated) {
            if ($authenticated) $this->actingAs(User::factory()->create());
            $this->get('/dosis43')->assertInertia(fn (Assert $p) => $p
                ->where('benefits.0.destination_url', route('landing-presentations.show', $landing))
                ->where('experiences.0.destination_url', route('landing-presentations.show', $experienceLanding))
                ->where('challenges.0.destination_url', route('landing-presentations.show', $challengeLanding)));
            foreach ([$benefit, $experience, $challenge] as $subject) {
                $resolver = app(\App\Services\ProductLandingResolver::class);
                $target = $resolver->navigationUrl($subject, $authenticated);
                $this->get($resolver->nativeUrl($subject).'?utm_source=partner&ref=EXAMPLE')
                    ->assertRedirect($target.'?utm_source=partner&ref=EXAMPLE');
                $this->get($target)->assertOk();
            }
        }
        // Authenticated profile visits never establish acquisition ownership.
        $this->assertDatabaseCount('referral_relationships', 0);
    }

    public function test_inapplicable_landings_fall_back_and_guest_priority_is_preserved(): void
    {
        extract($this->inventory());
        $resolver = app(\App\Services\ProductLandingResolver::class);
        foreach (['DRAFT', 'ARCHIVED', 'PUBLISHED'] as $status) {
            $landing->update(['status' => $status, 'default_scope' => 'NONE']);
            $this->assertSame(route('benefits.show', $benefit), $resolver->navigationUrl($benefit, false));
            $this->get(route('benefits.show', $benefit))->assertOk()->assertInertia(fn (Assert $p) => $p->component('benefits/show'));
        }
        $landing->update(['status' => 'PUBLISHED', 'default_scope' => 'ALL']);
        // Production enforces one default per subject. Relax only inside this
        // rolled-back test transaction to exercise the resolver's defensive priority.
        \Illuminate\Support\Facades\DB::statement('DROP INDEX landing_presentations_one_default');
        $guests = $benefit->landingPresentations()->create(['name' => 'Guests', 'slug' => 'guest-primary', 'status' => 'PUBLISHED', 'default_scope' => 'GUESTS']);
        $this->assertSame($guests->id, $resolver->forViewer($benefit, false)->id);
        $this->assertSame($landing->id, $resolver->forViewer($benefit, true)->id);
        $landing->delete();
        $this->actingAs(User::factory()->create());
        $this->assertSame(route('benefits.show', $benefit), $resolver->navigationUrl($benefit, true));
        $this->get(route('benefits.show', $benefit))->assertOk()->assertInertia(fn (Assert $p) => $p->component('benefits/show'));
        $guests->delete();
        $this->get(route('benefits.show', $benefit))->assertOk()->assertInertia(fn (Assert $p) => $p->component('benefits/show'));
    }

    public function test_landing_cta_continues_domain_action_without_presentation_loop(): void
    {
        extract($this->inventory());
        $benefit->update(['access_mode' => 'authenticated']);
        $this->get('/dosis43')->assertOk();
        $touch = AttributionTouch::sole();
        $this->get('/l/'.$landing->slug)->assertInertia(fn (Assert $p) => $p
            ->where('copy.action.kind', 'guest')->where('acquisition.endpoint', '/l/'.$landing->slug.'/acquisition'));
        $this->post('/l/'.$landing->slug.'/acquisition', ['method' => 'register'])->assertNoContent();
        $this->post('/register', ['name' => 'Landing User', 'email' => 'landing-first@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect('/l/'.$landing->slug.'/activar');
        $user = User::whereEmail('landing-first@example.test')->sole();
        $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $user->id, 'acquisition_partner_id' => $partner->id, 'attribution_touch_id' => $touch->id]);
        $this->actingAs($user)->get('/l/'.$landing->slug)->assertInertia(fn (Assert $p) => $p
            ->where('copy.action.kind', 'native')->where('landingDirectAction.endpoint', route('redemptions.start', $benefit, false))
            ->where('landingDirectAction.data.location_id', $location->id));
        $this->get('/l/'.$landing->slug.'/activar')->assertOk()->assertInertia(fn (Assert $p) => $p->component('landing-presentations/conversion'));
        $response = $this->post(route('redemptions.start', $benefit), ['location_id' => $location->id]);
        $response->assertRedirect();
        $this->assertStringNotContainsString('/l/', $response->headers->get('Location'));
        $this->assertDatabaseCount('redemptions', 1);
    }

}
