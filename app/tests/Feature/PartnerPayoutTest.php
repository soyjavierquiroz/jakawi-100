<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Conversion;
use App\Models\Partner;
use App\Models\RewardPayout;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnerPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_manager_request_only_their_partner_available_bob_cash_and_preserve_partner_beneficiary(): void
    {
        AppSetting::create(['key' => 'partner_minimum_payout', 'value' => ['amount' => 20]]);
        $partner = Partner::factory()->create(); $owner = $this->actor($partner, 'owner'); $manager = $this->actor($partner, 'manager');
        $available = $this->reward($partner, 25, 'available');
        $this->reward($partner, 30, 'pending'); $this->reward($partner, 30, 'cancelled'); $this->reward($partner, 30, 'paid'); $this->reward($partner, 30, 'available', 'JP', 'JP');
        $this->reward(Partner::factory()->create(), 99, 'available');
        $this->actingAs($owner)->post("/partner/{$partner->slug}/payouts")->assertRedirect();
        $payout = RewardPayout::firstOrFail();
        $this->assertSame('PARTNER', $payout->beneficiary_type); $this->assertSame($partner->id, $payout->beneficiary_id); $this->assertNull($payout->beneficiary_user_id); $this->assertSame($owner->id, $payout->requested_by_user_id);
        $this->assertSame([$available->id], $payout->rewards()->pluck('reward_transactions.id')->all());
        $this->actingAs($manager)->post("/partner/{$partner->slug}/payouts")->assertStatus(422);
    }

    public function test_threshold_is_independent_and_staff_or_other_partner_is_denied(): void
    {
        AppSetting::create(['key' => 'affiliate_minimum_payout', 'value' => ['amount' => 1]]);
        AppSetting::create(['key' => 'partner_minimum_payout', 'value' => ['amount' => 50]]);
        $partner = Partner::factory()->create(); $staff = $this->actor($partner, 'staff'); $owner = $this->actor($partner, 'owner'); $this->reward($partner, 49, 'available');
        $this->actingAs($staff)->post("/partner/{$partner->slug}/payouts")->assertForbidden();
        $this->actingAs($owner)->post("/partner/{$partner->slug}/payouts")->assertStatus(422);
        $other = Partner::factory()->create(); $this->actingAs($owner)->post("/partner/{$other->slug}/payouts")->assertForbidden();
    }

    public function test_admin_pays_idempotently_or_rejects_partner_payout(): void
    {
        AppSetting::create(['key' => 'partner_minimum_payout', 'value' => ['amount' => 0]]);
        $partner = Partner::factory()->create(); $owner = $this->actor($partner, 'owner'); $reward = $this->reward($partner, 25, 'available');
        $this->actingAs($owner)->post("/partner/{$partner->slug}/payouts"); $payout = RewardPayout::firstOrFail(); $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/payouts')->assertOk();
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", [])->assertSessionHasErrors('payment_reference');
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", ['payment_reference' => 'PARTNER-EXT'])->assertRedirect();
        $this->assertSame('paid', $payout->fresh()->status); $this->assertSame('paid', $reward->fresh()->status);
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", ['payment_reference' => 'changed'])->assertRedirect(); $this->assertSame('PARTNER-EXT', $payout->fresh()->payment_reference);
        $reward2 = $this->reward($partner, 10, 'available'); $this->actingAs($owner)->post("/partner/{$partner->slug}/payouts"); $second = RewardPayout::latest('id')->firstOrFail();
        $this->actingAs($admin)->post("/admin/payouts/{$second->id}/reject", [])->assertSessionHasErrors('reason'); $this->actingAs($admin)->post("/admin/payouts/{$second->id}/reject", ['reason' => 'Datos incompletos'])->assertRedirect(); $this->assertSame('available', $reward2->fresh()->status);
    }

    private function actor(Partner $partner, string $role): User { $user = User::factory()->create(); $user->partners()->attach($partner, ['role' => $role]); return $user; }
    private function reward(Partner $partner, float $amount, string $status, string $type = 'CASH', string $currency = 'BOB'): RewardTransaction
    {
        $conversion = Conversion::create(['user_id' => User::factory()->create()->id, 'type' => 'membership_purchased', 'idempotency_key' => (string) Str::uuid(), 'gross_amount' => 100, 'eligible_amount' => 100, 'currency' => 'BOB', 'status' => 'confirmed', 'occurred_at' => now()]);
        $rule = RewardRule::create(['name' => 'Partner test', 'beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id, 'participant_type' => 'PARTNER', 'event' => 'membership_purchased', 'reward_type' => $type, 'calculation_type' => 'FIXED', 'value' => $amount, 'currency' => $currency, 'status' => 'active']);
        return RewardTransaction::create(['beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id, 'conversion_id' => $conversion->id, 'reward_rule_id' => $rule->id, 'reward_type' => $type, 'amount' => $amount, 'currency' => $currency, 'status' => $status, 'available_at' => $status === 'available' ? now() : null]);
    }
}
