<?php

namespace Tests\Feature;

use App\Models\JpHold;
use App\Models\RewardTransaction;
use App\Models\SocialChallenge;
use App\Models\SocialChallengeParticipation;
use App\Models\SocialChallengeRewardGrant;
use App\Models\User;
use App\Services\JpBalanceService;
use App\Services\RewardResolver;
use App\Services\SocialChallengeQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialChallengeJpRewardTest extends TestCase
{
    use RefreshDatabase;

    private function challenge(array $attrs = []): SocialChallenge
    {
        return SocialChallenge::create(array_merge([
            'title' => 'Reto JP', 'slug' => 'reto-jp-'.Str::random(8), 'status' => 'open',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => [], 'required_mentions' => [],
            'qualification_mode' => 'VALID_POST', 'evaluation_mode' => 'CONTINUOUS',
            'reward_type' => 'JP', 'reward_jp_amount' => 500,
        ], $attrs));
    }

    private function participation(SocialChallenge $challenge, array $attrs = []): SocialChallengeParticipation
    {
        return SocialChallengeParticipation::create(array_merge([
            'social_challenge_id' => $challenge->id, 'user_id' => User::factory()->create()->id,
            'social_url' => 'https://instagram.com/p/'.Str::random(10),
            'normalized_url_hash' => hash('sha256', Str::random(20)),
            'external_reference' => 'test-'.Str::uuid(), 'validation_status' => 'valid',
        ], $attrs));
    }

    private function adminPayload(array $attrs = []): array
    {
        return array_merge([
            'title' => 'Reto admin', 'slug' => 'reto-admin', 'status' => 'draft',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => ['#reto'], 'required_mentions' => ['@jakawi'],
            'qualification_mode' => 'VALID_POST', 'evaluation_mode' => 'CONTINUOUS',
            'reward_type' => 'JP', 'reward_jp_amount' => 500,
        ], $attrs);
    }

    public function test_admin_validates_jp_amount_and_keeps_other_reward_amounts_null(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        foreach ([null, 0, -1, '1.5'] as $amount) {
            $payload = $this->adminPayload();
            if ($amount === null) unset($payload['reward_jp_amount']);
            else $payload['reward_jp_amount'] = $amount;
            $this->post('/admin/retos', $payload)->assertSessionHasErrors('reward_jp_amount');
        }
        $this->post('/admin/retos', $this->adminPayload())->assertRedirect();
        $this->assertDatabaseHas('social_challenges', ['slug' => 'reto-admin', 'reward_type' => 'JP', 'reward_jp_amount' => 500]);
        $this->post('/admin/retos', $this->adminPayload(['slug' => 'manual', 'reward_type' => 'MANUAL_PRIZE', 'reward_jp_amount' => null, 'manual_prize_description' => 'Premio']))->assertRedirect();
        $this->assertDatabaseHas('social_challenges', ['slug' => 'manual', 'reward_jp_amount' => null]);
        $this->post('/admin/retos', $this->adminPayload(['slug' => 'manual-invalid', 'reward_type' => 'MANUAL_PRIZE']))->assertSessionHasErrors('reward_jp_amount');
        $partner = \App\Models\Partner::factory()->create();
        $benefit = \App\Models\Benefit::factory()->forPartner($partner)->create(['access_mode' => 'social_challenge_grant']);
        $this->post('/admin/retos', $this->adminPayload(['slug' => 'benefit', 'reward_type' => 'BENEFIT', 'reward_jp_amount' => null, 'partner_id' => $partner->id, 'benefit_id' => $benefit->id]))->assertRedirect();
        $this->assertDatabaseHas('social_challenges', ['slug' => 'benefit', 'reward_jp_amount' => null]);
    }

    public function test_valid_post_credit_is_atomic_idempotent_and_respects_hold(): void
    {
        $challenge = $this->challenge();
        $p = $this->participation($challenge);
        JpHold::create(['user_id' => $p->user_id, 'unlock_participation_id' => $this->unlockParticipationId($p->user_id), 'amount' => 100, 'status' => JpHold::HELD, 'held_at' => now()]);
        $this->app->bind(RewardResolver::class, fn () => throw new \LogicException('RewardResolver must not run'));
        $service = app(SocialChallengeQualificationService::class);
        $service->evaluate($p);
        $service->evaluate($p);
        $service->grant($p);
        $grant = SocialChallengeRewardGrant::sole();
        $credit = RewardTransaction::sole();
        $this->assertSame(500, $grant->jp_amount);
        $this->assertSame('JP', $grant->snapshot['reward_type']);
        $this->assertSame(500, $grant->snapshot['jp_amount']);
        $this->assertSame('valid', $grant->snapshot['validation_status']);
        $this->assertSame($grant->id, $credit->social_challenge_reward_grant_id);
        $this->assertSame('social_challenge', $credit->source);
        $this->assertSame('JP', $credit->currency);
        $this->assertSame('available', $credit->status);
        $this->assertSame('500.00', $credit->amount);
        $this->assertNull($credit->conversion_id);
        $this->assertNull($credit->reward_rule_id);
        $this->assertNull($credit->unlock_participation_id);
        $this->assertSame(['ledger_balance' => 500, 'held' => 100, 'available_balance' => 400], app(JpBalanceService::class)->for($p->user_id));
        $this->assertDatabaseCount('social_challenge_reward_grants', 1);
        $this->assertDatabaseCount('reward_transactions', 1);
    }

    private function unlockParticipationId(int $userId): int
    {
        $unlock = \App\Models\Unlock::create(['title' => 'JP hold', 'slug' => (string) Str::uuid(),
            'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 1,
            'free_user_eligible' => true, 'member_eligible' => true, 'jp_deposit' => 0,
            'jp_completion_bonus' => 0, 'status' => \App\Models\Unlock::ACTIVE]);
        return \App\Models\UnlockParticipation::create(['unlock_id' => $unlock->id, 'user_id' => $userId,
            'status' => \App\Models\UnlockParticipation::COMMITTED])->id;
    }

    public function test_threshold_credits_only_at_target_and_amount_is_frozen(): void
    {
        $challenge = $this->challenge(['qualification_mode' => 'METRIC_THRESHOLD', 'metric' => 'likes', 'target' => 10]);
        $p = $this->participation($challenge, ['likes' => 9]);
        $service = app(SocialChallengeQualificationService::class);
        $service->evaluate($p);
        $this->assertDatabaseCount('reward_transactions', 0);
        $p->update(['likes' => 10]);
        $service->evaluate($p);
        $service->evaluate($p);
        $this->assertDatabaseCount('reward_transactions', 1);
        $this->assertSame(500, $p->grant->jp_amount);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put('/admin/retos/'.$challenge->slug, $this->adminPayload(['slug' => $challenge->slug, 'qualification_mode' => 'METRIC_THRESHOLD', 'metric' => 'likes', 'target' => 10, 'reward_jp_amount' => 700]))->assertSessionHasErrors('reward_jp_amount');
        $this->assertSame(500, $challenge->fresh()->reward_jp_amount);
    }

    public function test_ranked_waits_for_admin_and_confirmation_is_idempotent(): void
    {
        $challenge = $this->challenge(['qualification_mode' => 'RANKED', 'evaluation_mode' => 'AT_CLOSE', 'metric' => 'views', 'winner_count' => 1]);
        $p = $this->participation($challenge, ['views' => 100]);
        app(SocialChallengeQualificationService::class)->evaluate($p);
        $this->assertDatabaseCount('reward_transactions', 0);
        $challenge->update(['status' => 'closed']);
        $p->update(['final_metric_value' => 100, 'final_checked_at' => now()]);
        $admin = User::factory()->create(['is_admin' => true]);
        $url = "/admin/retos/{$challenge->slug}/participaciones/{$p->id}/ganador";
        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->assertDatabaseCount('social_challenge_reward_grants', 1);
        $this->assertDatabaseCount('reward_transactions', 1);
    }

    public function test_manual_waits_for_admin_and_approval_is_idempotent(): void
    {
        $challenge = $this->challenge(['qualification_mode' => 'MANUAL']);
        $p = $this->participation($challenge);
        app(SocialChallengeQualificationService::class)->evaluate($p);
        $this->assertDatabaseCount('reward_transactions', 0);
        $admin = User::factory()->create(['is_admin' => true]);
        $url = "/admin/retos/{$challenge->slug}/participaciones/{$p->id}/revisar";
        $this->actingAs($admin)->post($url, ['decision' => 'approved'])->assertRedirect();
        $this->actingAs($admin)->post($url, ['decision' => 'approved'])->assertRedirect();
        $this->assertDatabaseCount('social_challenge_reward_grants', 1);
        $this->assertDatabaseCount('reward_transactions', 1);
    }

    public function test_cancellation_creates_one_reversal_and_preserves_original(): void
    {
        $challenge = $this->challenge();
        $p = $this->participation($challenge);
        app(SocialChallengeQualificationService::class)->evaluate($p);
        $original = RewardTransaction::sole();
        $admin = User::factory()->create(['is_admin' => true]);
        $url = "/admin/retos/{$challenge->slug}/participaciones/{$p->id}/cancelar-premio";
        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->assertSame('cancelled', $p->grant->fresh()->status);
        $this->assertSame('available', $original->fresh()->status);
        $this->assertSame('500.00', $original->fresh()->amount);
        $reversal = RewardTransaction::where('reversal_of_reward_transaction_id', $original->id)->sole();
        $this->assertSame('-500.00', $reversal->amount);
        $this->assertSame('reversal', $reversal->source);
        $this->assertDatabaseCount('reward_transactions', 2);
        $this->assertSame(0, app(JpBalanceService::class)->for($p->user_id)['ledger_balance']);
    }

    public function test_uncredited_grant_cancellation_does_not_invent_a_reversal(): void
    {
        $challenge = $this->challenge();
        $p = $this->participation($challenge);
        SocialChallengeRewardGrant::create(['social_challenge_id' => $challenge->id, 'participation_id' => $p->id,
            'user_id' => $p->user_id, 'reward_type' => 'JP', 'jp_amount' => 500,
            'status' => 'granted', 'granted_at' => now(), 'snapshot' => ['reward_type' => 'JP', 'jp_amount' => 500]]);
        $admin = User::factory()->create(['is_admin' => true]);
        $url = "/admin/retos/{$challenge->slug}/participaciones/{$p->id}/cancelar-premio";
        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertSame('cancelled', $p->grant->status);
    }

    public function test_database_rejects_a_second_credit_for_the_same_grant(): void
    {
        $p = $this->participation($this->challenge());
        app(SocialChallengeQualificationService::class)->evaluate($p);
        $credit = RewardTransaction::sole();
        try {
            DB::transaction(fn () => RewardTransaction::create([
                'beneficiary_user_id' => $p->user_id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $p->user_id,
                'reward_type' => 'JP', 'currency' => 'JP', 'amount' => 500, 'status' => 'available',
                'source' => 'social_challenge', 'social_challenge_reward_grant_id' => $credit->social_challenge_reward_grant_id,
            ]));
            $this->fail('Expected unique constraint violation');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
        }
        $this->assertDatabaseCount('reward_transactions', 1);
    }

    public function test_database_link_is_unique_and_non_jp_flows_remain_without_ledger_entries(): void
    {
        $challenge = $this->challenge(['reward_type' => 'MANUAL_PRIZE', 'reward_jp_amount' => null, 'manual_prize_description' => 'Premio']);
        $p = $this->participation($challenge);
        app(SocialChallengeQualificationService::class)->evaluate($p);
        $this->assertNull($p->grant->jp_amount);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertSame(1, SocialChallengeRewardGrant::count());
    }
}
