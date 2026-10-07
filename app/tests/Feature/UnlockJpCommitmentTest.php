<?php

namespace Tests\Feature;

use App\Models\JpHold;
use App\Models\RewardTransaction;
use App\Models\RewardRule;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\User;
use App\Services\JpBalanceService;
use App\Services\UnlockParticipationService;
use App\Services\UnlockStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnlockJpCommitmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); config()->set('unlocks.jp_commitments_enabled', true); }

    public function test_hold_is_atomic_and_excluded_from_spendable_balance(): void
    {
        $user = User::factory()->create(); $this->earn($user, 50);
        $unlock = $this->unlock(['jp_deposit' => 50, 'minimum_commitments' => 2]);
        app(UnlockParticipationService::class)->commit($unlock, $user);
        $this->assertDatabaseHas('jp_holds', ['user_id' => $user->id, 'amount' => 50, 'status' => JpHold::HELD]);
        $this->assertSame(['ledger_balance' => 50, 'held' => 50, 'available_balance' => 0], app(JpBalanceService::class)->for($user));
        try { app(UnlockParticipationService::class)->commit($this->unlock(['jp_deposit' => 1]), $user); $this->fail('Overdraw accepted'); } catch (ValidationException) {}
        $this->assertSame(1, JpHold::count());
    }

    public function test_cancellation_failure_and_unlock_cancellation_release_once(): void
    {
        $user = User::factory()->create(); $this->earn($user, 100);
        $service = app(UnlockParticipationService::class); $unlock = $this->unlock(['jp_deposit' => 50, 'minimum_commitments' => 3]);
        $service->commit($unlock, $user); $service->cancel($unlock, $user);
        $this->assertSame(JpHold::RELEASED, JpHold::sole()->status);
        $failure = $this->unlock(['jp_deposit' => 50, 'minimum_commitments' => 2]); $service->commit($failure, $user);
        app(UnlockStatusService::class)->transition($failure, Unlock::GOAL_NOT_REACHED);
        $this->assertSame(2, JpHold::where('status', JpHold::RELEASED)->count());
    }

    public function test_goal_reached_does_not_release_and_production_guard_blocks_activation(): void
    {
        $user = User::factory()->create(); $this->earn($user, 50); $unlock = $this->unlock(['jp_deposit' => 50]);
        app(UnlockParticipationService::class)->commit($unlock, $user);
        $this->assertSame(Unlock::UNLOCKED, $unlock->fresh()->status); $this->assertSame(JpHold::HELD, JpHold::sole()->status);
        config()->set('unlocks.jp_commitments_enabled', false);
        $draft = $this->unlock(['status' => Unlock::SCHEDULED, 'jp_deposit' => 1]);
        $this->expectException(ValidationException::class); app(UnlockStatusService::class)->transition($draft, Unlock::ACTIVE);
    }

    private function unlock(array $overrides = []): Unlock { return Unlock::create(array_merge(['title' => 'JP '.uniqid(), 'slug' => 'jp-'.uniqid(), 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 1, 'free_user_eligible' => true, 'member_eligible' => true, 'jp_deposit' => 0, 'jp_completion_bonus' => 0, 'status' => Unlock::ACTIVE], $overrides)); }
    private function earn(User $user, int $amount): void { $conversion = \App\Models\Conversion::create(['user_id' => $user->id, 'type' => 'membership_purchased', 'idempotency_key' => (string) Str::uuid(), 'gross_amount' => 100, 'eligible_amount' => 100, 'currency' => 'BOB', 'status' => 'confirmed', 'occurred_at' => now()]); $rule = RewardRule::create(['name' => 'JP '.Str::uuid(), 'participant_type' => 'MEMBER', 'event' => 'membership_purchased', 'reward_type' => 'JP', 'calculation_type' => 'FIXED', 'value' => $amount, 'currency' => 'JP', 'priority' => 0, 'status' => 'active']); RewardTransaction::create(['beneficiary_user_id' => $user->id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'conversion_id' => $conversion->id, 'reward_rule_id' => $rule->id, 'reward_type' => 'JP', 'amount' => $amount, 'currency' => 'JP', 'status' => RewardTransaction::STATUS_AVAILABLE]); }
}
