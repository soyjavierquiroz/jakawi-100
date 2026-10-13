<?php

namespace Tests\Feature;

use App\Models\{Challenge, ChallengeParticipation, LandingPresentation, Partner, Unlock, User};
use App\Services\{ChallengeDeliveryPresenter, ChallengeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Notification, Queue, URL};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChallengeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function challenge(array $overrides = []): Challenge
    {
        return Challenge::create(array_replace([
            'title' => 'Reto Cena de Ciudad — DEMO', 'slug' => 'delivery-cena',
            'partner_id' => Partner::factory()->published()->create(['name' => 'Brasa Prisma — DEMO'])->id,
            'status' => 'open', 'review_status' => 'APPROVED', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
            'description' => 'Comparte tu plan gastronómico.', 'instructions' => 'Publica una foto original de tu cena y mantén la publicación pública.',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => ['#jakawidemocena'], 'required_mentions' => [],
            'evidence_type' => 'SOCIAL_POST', 'qualification_type' => 'METRIC_THRESHOLD', 'qualification_metric' => 'likes', 'qualification_target' => 50,
            'selection_type' => 'FIRST_N', 'winner_limit' => 10, 'review_mode' => 'REQUIRED', 'evaluation_mode' => 'CONTINUOUS',
            'participation_eligibility' => 'ALL_USERS', 'reward_eligibility' => 'ALL_USERS', 'max_entries_per_user' => 1,
            'reward_type' => 'MANUAL_PRIZE', 'manual_prize_description' => 'Cena para dos — premio DEMO.',
        ], $overrides));
    }

    private function landing(Challenge $challenge): LandingPresentation
    {
        return $challenge->landingPresentations()->create(['name' => 'Delivery QA', 'slug' => 'delivery-'.$challenge->slug, 'status' => 'PUBLISHED', 'default_scope' => 'ALL']);
    }

    private function join(Challenge $c, LandingPresentation $l): void
    {
        $this->post(route('social-challenges.intent', $c), ['landing_presentation_slug' => $l->slug])->assertRedirect(route('landing-presentations.result', $l).'#participar');
    }

    public function test_unverified_user_explicitly_joins_social_challenge_and_reaches_delivery_once(): void
    {
        $c = $this->challenge(); $l = $this->landing($c); $u = User::factory()->unverified()->create();
        $this->actingAs($u); $this->join($c, $l); $this->join($c, $l);
        $this->assertDatabaseCount('challenge_participations', 1);
        $this->assertDatabaseCount('challenge_social_entries', 0);
        $this->assertDatabaseCount('challenge_reward_grants', 0);
        $this->assertNull($u->fresh()->email_verified_at);
        $this->get(route('landing-presentations.result', $l))->assertInertia(fn (Assert $p) => $p->component('landing-presentations/conversion')->where('conversionStage', 'result')->has('participation.id')->where('canSubmit', false));
    }

    public function test_delivery_has_authoritative_instructions_and_title_once_without_verification(): void
    {
        $c = $this->challenge(); $l = $this->landing($c);
        $this->actingAs(User::factory()->unverified()->create()); $this->join($c, $l);
        $this->get(route('landing-presentations.result', $l))->assertInertia(fn (Assert $p) => $p
            ->where('delivery.title', $c->title)->where('delivery.partner', 'Brasa Prisma — DEMO')
            ->where('delivery.steps.0.body', $c->instructions)->has('delivery.steps', 4)
            ->where('delivery.platforms', ['instagram'])->where('delivery.hashtags', ['#jakawidemocena'])
            ->where('delivery.mission', 'Consigue al menos 50 likes con evidencia válida.')
            ->where('delivery.deadline', $c->ends_at->toIso8601String()));
        $this->assertSame(1, substr_count(json_encode(app(ChallengeDeliveryPresenter::class)->present($c), JSON_UNESCAPED_UNICODE), $c->title));
    }

    public function test_gets_never_participate_and_result_requires_owned_participation(): void
    {
        $c = $this->challenge(); $l = $this->landing($c);
        $this->actingAs(User::factory()->unverified()->create());
        $this->get(route('landing-presentations.show', $l))->assertInertia(fn (Assert $p) => $p->where('canJoin', true)->where('participation', null)->where('landingDirectAction.endpoint', route('social-challenges.intent', $c, false)));
        $this->get(route('landing-presentations.activation', $l))->assertOk();
        $this->get(route('landing-presentations.result', $l))->assertNotFound();
        $this->assertDatabaseCount('challenge_participations', 0);
        $this->join($c, $l);
        $this->actingAs(User::factory()->create())->get(route('landing-presentations.result', $l))->assertNotFound();
    }

    public function test_unverified_submission_remains_blocked_at_evidence_boundary(): void
    {
        $c = $this->challenge(); $l = $this->landing($c);
        $this->actingAs(User::factory()->unverified()->create()); $this->join($c, $l);
        $this->post(route('social-challenges.submit', $c), ['url' => 'https://instagram.com/p/delivery'])->assertSessionHasErrors(['url' => 'Confirma tu correo antes de enviar evidencia.']);
        $this->assertDatabaseCount('challenge_social_entries', 0);
        $this->assertDatabaseCount('challenge_reward_grants', 0);
    }

    public function test_verified_participant_can_submit_evidence_without_automatic_reward(): void
    {
        Queue::fake(); $c = $this->challenge(); $l = $this->landing($c);
        $this->actingAs(User::factory()->create()); $this->join($c, $l);
        $this->post(route('social-challenges.submit', $c), ['url' => 'https://instagram.com/p/delivery', 'landing_presentation_slug' => $l->slug])->assertRedirect(route('landing-presentations.result', $l));
        $this->assertDatabaseCount('challenge_social_entries', 1);
        $this->assertDatabaseCount('challenge_reward_grants', 0);
        Queue::assertPushed(\App\Jobs\RefreshChallengeSocialEntry::class);
    }

    public function test_manual_reward_still_requires_verified_identity(): void
    {
        $c = $this->challenge(['evidence_type' => 'MANUAL', 'qualification_type' => 'MANUAL', 'selection_type' => 'ALL_QUALIFIED', 'review_mode' => 'AUTOMATIC']);
        $l = $this->landing($c); $u = User::factory()->unverified()->create();
        $this->actingAs($u); $this->join($c, $l);
        $p = ChallengeParticipation::sole(); $p->update(['review_status' => 'approved']);
        $service = app(ChallengeService::class); $service->evaluate($p);
        $this->assertNull($service->selectAndMaybeGrant($p));
        $this->assertDatabaseCount('challenge_reward_grants', 0);
        $u->markEmailAsVerified();
        $service->evaluate($p);
        $this->assertDatabaseCount('challenge_reward_grants', 1);
        $this->assertSame('granted', $p->fresh()->grant->status);
    }

    public function test_signed_verification_returns_to_same_delivery_and_updates_capability(): void
    {
        Notification::fake(); $c = $this->challenge(); $l = $this->landing($c); $u = User::factory()->unverified()->create();
        $this->actingAs($u); $this->join($c, $l);
        $this->get(route('landing-presentations.result', $l))->assertOk();
        $this->post(route('verification.send'))->assertRedirect();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $this->get($url)->assertRedirect(route('landing-presentations.result', $l, false));
        $this->assertNotNull($u->fresh()->email_verified_at);
        $this->get(route('landing-presentations.result', $l))->assertInertia(fn (Assert $p) => $p->where('canSubmit', true)->has('participation.id'));
        $this->assertDatabaseCount('challenge_participations', 1);
    }

    public function test_membership_dates_and_manual_instructions_remain_authoritative(): void
    {
        $c = $this->challenge(['participation_eligibility' => 'ACTIVE_MEMBERS', 'evidence_type' => 'MANUAL', 'qualification_type' => 'MANUAL', 'ends_at' => null]); $l = $this->landing($c);
        $this->actingAs(User::factory()->unverified()->create())->post(route('social-challenges.intent', $c), ['landing_presentation_slug' => $l->slug])->assertSessionHasErrors('participation');
        $this->assertDatabaseCount('challenge_participations', 0);
        $data = app(ChallengeDeliveryPresenter::class)->present($c);
        $this->assertNull($data['deadline']); $this->assertCount(2, $data['steps']);
        $this->assertSame([], $data['platforms']); $this->assertSame($c->instructions, $data['steps'][0]['body']);
        $c->update(['participation_eligibility' => 'ALL_USERS', 'ends_at' => now()->subMinute()]);
        $this->post(route('social-challenges.intent', $c))->assertNotFound();
    }

    public function test_unlock_restricted_source_stays_protected(): void
    {
        $u = Unlock::create(['title' => 'Secret', 'slug' => 'delivery-secret', 'status' => Unlock::UNLOCKED, 'minimum_commitments' => 2,
            'secret_mode' => true, 'hide_exact_offer_until_unlock' => true, 'hide_partner_until_unlock' => true,
            'free_user_eligible' => true, 'member_eligible' => true, 'description' => 'RESTRICTED-SOURCE', 'free_user_offer' => 'RESTRICTED-OFFER']);
        $l = $u->landingPresentations()->create(['name' => 'Secret QA', 'slug' => 'secret-qa', 'status' => 'PUBLISHED', 'default_scope' => 'ALL', 'vsl' => ['source' => ['type' => 'mp4', 'url' => 'https://example.test/RESTRICTED-SOURCE.mp4']]]);
        $this->actingAs(User::factory()->unverified()->create())->get(route('landing-presentations.show', $l))->assertDontSee('RESTRICTED-SOURCE')->assertDontSee('RESTRICTED-OFFER');
        $this->assertDatabaseCount('unlock_participations', 0);
    }
}
