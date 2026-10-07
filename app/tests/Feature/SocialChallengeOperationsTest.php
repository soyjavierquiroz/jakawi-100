<?php

namespace Tests\Feature;

use App\Integrations\ShareContest\InspectionException;
use App\Integrations\ShareContest\Inspector;
use App\Jobs\RefreshSocialChallengeParticipation;
use App\Models\SocialChallenge;
use App\Models\SocialChallengeParticipation;
use App\Models\User;
use App\Services\PublicJourneyContinuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
use Tests\TestCase;

class SocialChallengeOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function challenge(string $slug = 'reto-social'): SocialChallenge
    {
        return SocialChallenge::create([
            'title' => 'Reto social', 'slug' => $slug, 'status' => 'open',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => [], 'required_mentions' => [],
            'qualification_mode' => 'VALID_POST', 'evaluation_mode' => 'CONTINUOUS',
            'reward_type' => 'MANUAL_PRIZE', 'manual_prize_description' => 'Premio manual',
        ]);
    }

    public function test_guest_participate_registers_then_returns_to_same_challenge_without_submitting(): void
    {
        Queue::fake();
        $challenge = $this->challenge();
        $other = $this->challenge('otro-reto');
        $this->get(route('social-challenges.show', $challenge))->assertOk();
        $this->post(route('social-challenges.intent', $challenge))->assertRedirect(route('register'));
        $continuation = app(PublicJourneyContinuation::class);
        $this->assertSame(['journey' => 'SOCIAL_CHALLENGE', 'resource_id' => $challenge->id, 'action' => 'PARTICIPATE'], $continuation->get());
        $this->assertSame(0, SocialChallengeParticipation::count());

        $this->post('/register', ['name' => 'Nuevo usuario', 'email' => 'nuevo@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect(route('social-challenges.show', $challenge->slug, false));
        $this->assertAuthenticated();
        $this->assertNull($continuation->get());
        $this->assertSame(0, SocialChallengeParticipation::count());
        Queue::assertNotPushed(RefreshSocialChallengeParticipation::class);

        $user = User::where('email', 'nuevo@example.test')->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user);
        $this->get(route('social-challenges.show', $challenge))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('social-challenges/show')->where('challenge.id', $challenge->id)->where('canSubmit', true));
        $this->post(route('social-challenges.submit', $challenge), ['url' => 'https://instagram.com/p/one'])->assertRedirect();
        $this->assertDatabaseHas('social_challenge_participations', ['social_challenge_id' => $challenge->id, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('social_challenge_participations', ['social_challenge_id' => $other->id]);
        Queue::assertPushed(RefreshSocialChallengeParticipation::class, 1);
    }

    public function test_guest_login_returns_to_same_challenge_without_arbitrary_url_or_auto_submit(): void
    {
        Queue::fake();
        $challenge = $this->challenge();
        $user = User::factory()->create();
        $this->get(route('social-challenges.show', $challenge))->assertOk();
        $this->post(route('social-challenges.intent', $challenge))->assertRedirect(route('register'));
        try {
            app(PublicJourneyContinuation::class)->set('SOCIAL_CHALLENGE', $challenge->id, 'PARTICIPATE', ['url' => 'https://outside.example/']);
            $this->fail('Arbitrary URL context was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame('SOCIAL_CHALLENGE', app(PublicJourneyContinuation::class)->get()['journey']);
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('social-challenges.show', $challenge->slug, false));
        $this->assertAuthenticatedAs($user);
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        $this->assertSame(0, SocialChallengeParticipation::count());
        Queue::assertNotPushed(RefreshSocialChallengeParticipation::class);
        $this->get(route('social-challenges.show', $challenge))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('social-challenges/show')->where('challenge.id', $challenge->id)->where('canSubmit', true));
    }

    public function test_http_submission_and_admin_refresh_only_enqueue_database_social_job(): void
    {
        Queue::fake();
        Http::fake();
        $challenge = $this->challenge();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('social-challenges.submit', $challenge), ['url' => 'https://instagram.com/p/one'])->assertRedirect();
        $participation = SocialChallengeParticipation::sole();
        $reference = $participation->external_reference;
        Queue::assertPushed(RefreshSocialChallengeParticipation::class, fn (RefreshSocialChallengeParticipation $job) =>
            $job->connection === 'database' && $job->queue === 'social' && $job->participationId === $participation->id);
        Http::assertNothingSent();

        $participation->update(['refresh_pending' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.social-challenges.participations.refresh', [$challenge, $participation]))->assertRedirect();
        Queue::assertPushed(RefreshSocialChallengeParticipation::class, 2);
        Queue::assertPushed(RefreshSocialChallengeParticipation::class, fn (RefreshSocialChallengeParticipation $job) =>
            $job->connection === 'database' && $job->queue === 'social' && $job->participationId === $participation->id);
        $this->assertSame($reference, $participation->fresh()->external_reference);
        $this->assertSame(1, SocialChallengeParticipation::count());
        Http::assertNothingSent();
    }

    public function test_sharecontest_configuration_distinguishes_missing_token_from_configured_fake(): void
    {
        $challenge = $this->challenge();
        $participation = SocialChallengeParticipation::create([
            'social_challenge_id' => $challenge->id, 'user_id' => User::factory()->create()->id,
            'social_url' => 'https://instagram.com/p/one', 'normalized_url_hash' => hash('sha256', 'one'),
            'external_reference' => 'stable-reference',
        ]);
        Http::fake();
        config(['services.sharecontest.url' => 'https://sharecontest.example.test', 'services.sharecontest.token' => null]);
        try {
            app(Inspector::class)->inspect($participation);
            $this->fail('Missing token was accepted.');
        } catch (InspectionException $e) {
            $this->assertSame('configuration', $e->errorCode);
            $this->assertFalse($e->retryable);
        }
        Http::assertNothingSent();

        config(['services.sharecontest.token' => 'test-only-credential']);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['sharecontest.example.test/*' => Http::response([
            'success' => true, 'request_id' => 'request-1',
            'data' => ['data_quality' => 'partial', 'metrics' => ['views' => null]],
            'validation' => ['status' => 'review_required', 'checks' => []],
        ], 200)]);
        $result = app(Inspector::class)->inspect($participation);
        $this->assertSame('review_required', $result->data['validation_status']);
        $this->assertNull($result->data['views']);
        Http::assertSentCount(1);
    }
}
