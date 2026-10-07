<?php

namespace Tests\Feature;

use App\Models\JpHold;
use App\Models\Membership;
use App\Models\OperationalAdjustment;
use App\Models\RewardTransaction;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\User;
use App\Services\JpBalanceService;
use App\Services\JpLedgerService;
use App\Services\UnlockParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JpLedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_credit_debit_retry_and_canonical_snapshots(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $credit = $this->adjust($admin, $user, 'ledger_credit', 100);
        $this->actingAs($admin)->post('/admin/adjustments/ledger', $credit)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/adjustments/ledger', $credit)->assertSessionHasNoErrors();
        $this->assertSame(1, OperationalAdjustment::count());
        $this->assertSame(1, RewardTransaction::count());
        $this->assertSame(['ledger_balance' => 100, 'held' => 0, 'available_balance' => 100], app(JpBalanceService::class)->for($user));

        $debit = $this->adjust($admin, $user, 'ledger_debit', 80);
        $this->actingAs($admin)->post('/admin/adjustments/ledger', $debit)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/adjustments/ledger', $debit)->assertSessionHasNoErrors();
        $this->assertSame(2, OperationalAdjustment::count());
        $this->assertSame(2, RewardTransaction::count());
        $this->assertSame(['ledger_balance' => 20, 'held' => 0, 'available_balance' => 20], app(JpBalanceService::class)->for($user));
        $this->assertDatabaseHas('reward_transactions', ['operational_adjustment_id' => OperationalAdjustment::where('type', 'ledger_debit')->value('id'), 'amount' => -80, 'source' => 'admin_adjustment']);
        $this->assertEquals(['ledger_balance' => 100, 'held' => 0, 'available_balance' => 100], OperationalAdjustment::where('type', 'ledger_debit')->firstOrFail()->before);
        $this->assertEquals(['ledger_balance' => 20, 'held' => 0, 'available_balance' => 20], OperationalAdjustment::where('type', 'ledger_debit')->firstOrFail()->after);
    }

    public function test_admin_rejects_overdraw_ambiguous_sign_and_key_reuse(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $credit = $this->adjust($admin, $user, 'ledger_credit', 100);
        $this->actingAs($admin)->post('/admin/adjustments/ledger', $credit)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/adjustments/ledger', $this->adjust($admin, $user, 'ledger_debit', 101))->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post('/admin/adjustments/ledger', array_replace($credit, ['type' => 'ledger_debit', 'amount' => -10]))->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post('/admin/adjustments/ledger', array_replace($credit, ['type' => 'ledger_debit']))->assertSessionHasErrors('idempotency_key');
        $this->assertSame(1, RewardTransaction::count());
    }

    public function test_hold_releases_without_entry_and_forfeiture_posts_once(): void
    {
        $user = User::factory()->create();
        $this->reward($user, 100);
        $unlock = $this->unlock();
        $participation = UnlockParticipation::create(['unlock_id' => $unlock->id, 'user_id' => $user->id, 'status' => UnlockParticipation::COMMITTED]);
        $hold = JpHold::create(['user_id' => $user->id, 'unlock_participation_id' => $participation->id, 'amount' => 80, 'status' => JpHold::HELD, 'held_at' => now()]);
        $this->assertSame(['ledger_balance' => 100, 'held' => 80, 'available_balance' => 20], app(JpBalanceService::class)->for($user));
        app(UnlockParticipationService::class)->cancel($unlock, $user);
        $this->assertSame(JpHold::RELEASED, $hold->fresh()->status);
        $this->assertSame(1, RewardTransaction::count());
        $this->assertSame(100, app(JpBalanceService::class)->for($user)['available_balance']);

        $participation2 = UnlockParticipation::create(['unlock_id' => $this->unlock()->id, 'user_id' => $user->id, 'status' => UnlockParticipation::CONFIRMED]);
        $forfeited = JpHold::create(['user_id' => $user->id, 'unlock_participation_id' => $participation2->id, 'amount' => 80, 'status' => JpHold::HELD, 'held_at' => now()]);
        $service = app(JpLedgerService::class);
        $first = $service->forfeit($forfeited);
        $second = $service->forfeit($forfeited);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(JpHold::FORFEITED, $forfeited->fresh()->status);
        $this->assertSame(['ledger_balance' => 20, 'held' => 0, 'available_balance' => 20], app(JpBalanceService::class)->for($user));
        $this->assertSame(2, RewardTransaction::count());
    }

    public function test_jp_reversal_and_admin_status_correction_keep_original(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $original = $this->reward($user, 40);
        $this->actingAs($admin)->post("/admin/adjustments/rewards/{$original->id}/status", ['status' => 'cancelled', 'reason' => 'Incorrect JP', 'confirm' => true])->assertSessionHasNoErrors();
        $first = RewardTransaction::where('reversal_of_reward_transaction_id', $original->id)->sole();
        $this->assertSame('available', $original->fresh()->status);
        $this->assertSame('-40.00', $first->amount);
        $this->assertSame($first->id, app(JpLedgerService::class)->reverse($original, $admin->id)->id);
        $this->assertSame(2, RewardTransaction::count());
        $this->assertSame(0, app(JpBalanceService::class)->for($user)['ledger_balance']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'jp_reward_reversed']);
    }

    public function test_pending_jp_can_cancel_without_entry_and_cash_stays_on_existing_path(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $pending = $this->reward($user, 30, 'pending');
        $cash = $this->reward($user, 20, 'available', 'CASH');
        $this->actingAs($admin)->post("/admin/adjustments/rewards/{$pending->id}/status", ['status' => 'cancelled', 'reason' => 'Cancel', 'confirm' => true])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/admin/adjustments/rewards/{$cash->id}/status", ['status' => 'cancelled', 'reason' => 'Cancel', 'confirm' => true])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('cancelled', $cash->fresh()->status);
        $this->assertSame(2, RewardTransaction::count());
    }

    public function test_negative_ledger_is_visible_while_available_clamps_and_mi_jakawi_uses_it(): void
    {
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'amount_paid' => 100]);
        $this->reward($user, 100);
        $this->reward($user, -150);
        $this->assertSame(['ledger_balance' => -50, 'held' => 0, 'available_balance' => 0], app(JpBalanceService::class)->for($user));
        $this->actingAs($user)->get('/mi-jakawi')->assertInertia(fn ($page) => $page->where('memberReferral.jp_ledger', -50)->where('memberReferral.jp_balance', 0));
    }

    private function adjust(User $actor, User $user, string $type, int $amount): array
    {
        return ['beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'unit' => 'JP', 'type' => $type, 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Correction', 'confirm' => true];
    }

    private function reward(User $user, int $amount, string $status = 'available', string $type = 'JP'): RewardTransaction
    {
        return RewardTransaction::create(['beneficiary_user_id' => $user->id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'reward_type' => $type, 'currency' => $type === 'JP' ? 'JP' : 'BOB', 'amount' => $amount, 'status' => $status, 'available_at' => $status === 'available' ? now() : null]);
    }

    private function unlock(): Unlock
    {
        return Unlock::create(['title' => 'JP test', 'slug' => (string) Str::uuid(), 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'jp_deposit' => 0, 'jp_completion_bonus' => 0, 'status' => Unlock::ACTIVE, 'cancellation_deadline' => now()->addHour()]);
    }
}
