<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Conversion;
use App\Models\ProgramEnrollment;
use App\Models\RewardPayout;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliatePayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_can_only_request_available_cash_at_or_above_the_persistent_threshold(): void
    {
        $affiliate = $this->affiliate();
        AppSetting::create(['key' => 'affiliate_minimum_payout', 'value' => ['amount' => 20]]);
        $available = $this->reward($affiliate, 25, 'available');
        $pending = $this->reward($affiliate, 30, 'pending');
        $cancelled = $this->reward($affiliate, 40, 'cancelled', 'NONCASH');
        $this->actingAs($affiliate)->post('/affiliate/payouts')->assertRedirect('/affiliate');
        $payout = RewardPayout::firstOrFail();
        $this->assertSame('25.00', $payout->requested_amount);
        $this->assertSame([$available->id], $payout->rewards()->pluck('reward_transactions.id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_payout_requested']);
        $this->actingAs($affiliate)->post('/affiliate/payouts')->assertStatus(422);
        $other = $this->affiliate();
        $this->reward($other, 99, 'available');
        $this->actingAs($affiliate)->post('/affiliate/payouts')->assertStatus(422);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_below_threshold_is_rejected_and_dashboard_has_payment_data(): void
    {
        $affiliate = $this->affiliate();
        AppSetting::create(['key' => 'affiliate_minimum_payout', 'value' => ['amount' => 50]]);
        $this->reward($affiliate, 49, 'available');
        $this->actingAs($affiliate)->post('/affiliate/payouts')->assertStatus(422);
        $this->actingAs($affiliate)->get('/affiliate')->assertInertia(fn ($page) => $page->where('metrics.available', '49.00')->where('minimumPayout', '50'));
    }

    public function test_admin_pays_idempotently_and_requires_reference(): void
    {
        $affiliate = $this->affiliate();
        $reward = $this->reward($affiliate, 25, 'available');
        $this->actingAs($affiliate)->post('/affiliate/payouts');
        $payout = RewardPayout::firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/payouts')->assertOk();
        $this->actingAs($affiliate)->post("/admin/payouts/{$payout->id}/paid", ['payment_reference' => 'X'])->assertForbidden();
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", [])->assertSessionHasErrors('payment_reference');
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", ['payment_reference' => 'EXT-123'])->assertRedirect();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('paid', $reward->fresh()->status);
        $this->assertSame('EXT-123', $payout->fresh()->payment_reference);
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", ['payment_reference' => 'CHANGED'])->assertRedirect();
        $this->assertSame('EXT-123', $payout->fresh()->payment_reference);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_payout_paid']);
    }

    public function test_rejection_requires_reason_releases_rewards_and_refund_is_blocked_while_requested(): void
    {
        $affiliate = $this->affiliate();
        $reward = $this->reward($affiliate, 25, 'available');
        $this->actingAs($affiliate)->post('/affiliate/payouts');
        $payout = RewardPayout::firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/reject", [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/reject", ['reason' => 'Datos incompletos'])->assertRedirect();
        $this->assertSame('rejected', $payout->fresh()->status);
        $this->assertSame('available', $reward->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reward_released_from_payout']);
    }

    private function affiliate(): User
    {
        $user = User::factory()->create();
        ProgramEnrollment::create(['user_id' => $user->id, 'program_type' => 'AFFILIATE', 'status' => 'active']);

        return $user;
    }

    private function reward(User $beneficiary, float $amount, string $status, string $rewardType = 'CASH'): RewardTransaction
    {
        $customer = User::factory()->create();
        $conversion = Conversion::create(['user_id' => $customer->id, 'type' => 'membership_purchased', 'idempotency_key' => (string) Str::uuid(), 'gross_amount' => 100, 'eligible_amount' => 100, 'currency' => 'BOB', 'status' => 'confirmed', 'occurred_at' => now()]);
        $rule = RewardRule::create(['name' => 'Test', 'beneficiary_user_id' => $beneficiary->id, 'event' => 'membership_purchased', 'reward_type' => $rewardType, 'calculation_type' => 'FIXED', 'value' => $amount, 'status' => 'active']);

        return RewardTransaction::create(['beneficiary_user_id' => $beneficiary->id, 'conversion_id' => $conversion->id, 'reward_rule_id' => $rule->id, 'reward_type' => $rewardType, 'amount' => $amount, 'currency' => 'BOB', 'status' => $status, 'available_at' => $status === 'available' ? now() : null]);
    }
}
