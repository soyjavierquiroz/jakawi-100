<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\Conversion;
use App\Models\OperationalAdjustment;
use App\Models\Partner;
use App\Models\ReferralRelationship;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAdjustmentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_records_separate_audited_cash_and_jp_adjustments_without_a_balance_mutation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]); $user = User::factory()->create();
        $this->actingAs($admin)->post('/admin/adjustments/ledger', ['beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'unit' => 'JP', 'amount' => 100, 'reason' => 'Manual JP correction', 'confirm' => true])->assertRedirect();
        $this->actingAs($admin)->post('/admin/adjustments/ledger', ['beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'unit' => 'CASH', 'amount' => 12.5, 'reason' => 'Manual cash correction', 'confirm' => true])->assertRedirect();
        $this->assertDatabaseHas('operational_adjustments', ['beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'unit' => 'JP', 'amount' => 100]);
        $this->assertDatabaseHas('operational_adjustments', ['beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'unit' => 'CASH', 'amount' => 12.5]);
        $this->assertSame(2, OperationalAdjustment::count()); $this->assertArrayNotHasKey('jp_balance', $user->fresh()->getAttributes());
    }

    public function test_non_admin_and_missing_reason_are_denied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/adjustments/ledger', ['beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'unit' => 'JP', 'amount' => 1, 'reason' => 'x', 'confirm' => true])->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/adjustments/ledger', ['beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'unit' => 'JP', 'amount' => 1, 'confirm' => true])->assertSessionHasErrors('reason');
    }

    public function test_partner_adjustment_keeps_partner_beneficiary(): void
    {
        $admin = User::factory()->create(['is_admin' => true]); $partner = Partner::factory()->create();
        $this->actingAs($admin)->post('/admin/adjustments/ledger', ['beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id, 'unit' => 'CASH', 'amount' => 20, 'reason' => 'Partner credit', 'confirm' => true])->assertRedirect();
        $this->assertDatabaseHas('operational_adjustments', ['beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id]);
    }

    public function test_attribution_correction_preserves_touch_and_audits_old_relationship(): void
    {
        $admin = User::factory()->create(['is_admin' => true]); $user = User::factory()->create(); $old = User::factory()->create(); $new = User::factory()->create();
        $touch = AttributionTouch::create(['user_id' => $user->id, 'referrer_user_id' => $old->id, 'occurred_at' => now()]);
        $relationship = ReferralRelationship::create(['referred_user_id' => $user->id, 'referrer_user_id' => $old->id, 'referral_code' => 'OLD', 'attribution_touch_id' => $touch->id, 'attributed_at' => now(), 'status' => 'active']);
        $this->actingAs($admin)->post('/admin/attribution/'.$user->id.'/correction', ['referrer_type' => 'USER', 'referrer_id' => $new->id, 'reason' => 'Wrong referral', 'confirm' => true])->assertRedirect();
        $this->assertDatabaseHas('attribution_touches', ['id' => $touch->id]); $this->assertSame('invalid', $relationship->fresh()->status);
        $this->assertDatabaseHas('operational_adjustments', ['type' => 'attribution_correction']);
    }

    public function test_paid_reward_cannot_be_rewritten(): void
    {
        $admin = User::factory()->create(['is_admin' => true]); $reward = $this->reward('paid');
        $this->actingAs($admin)->post('/admin/adjustments/rewards/'.$reward->id.'/status', ['status' => 'cancelled', 'reason' => 'No rewrite', 'confirm' => true])->assertStatus(422);
        $this->assertSame('paid', $reward->fresh()->status);
    }

    public function test_admin_csvs_are_authorized_filtered_private_and_formula_safe(): void
    {
        $admin = User::factory()->create(['is_admin' => true]); $user = User::factory()->create(['email' => 'private@example.test']);
        AttributionTouch::create(['user_id' => $user->id, 'utm_source' => '=formula', 'landing_page' => '/safe', 'occurred_at' => now()]);
        foreach (['affiliates', 'conversions', 'rewards', 'payouts', 'attribution'] as $type) $this->actingAs(User::factory()->create())->get('/admin/exports/'.$type)->assertForbidden();
        $response = $this->actingAs($admin)->get('/admin/exports/attribution?from='.now()->toDateString());
        $response->assertOk(); $csv = $response->streamedContent();
        $this->assertStringContainsString("'=formula", $csv); $this->assertStringNotContainsString('private@example.test', $csv);
    }

    private function reward(string $status): RewardTransaction
    {
        $user = User::factory()->create(); $conversion = Conversion::create(['user_id' => $user->id, 'type' => 'test', 'idempotency_key' => (string) Str::uuid(), 'gross_amount' => 1, 'eligible_amount' => 1, 'currency' => 'BOB', 'status' => 'confirmed', 'occurred_at' => now()]);
        $rule = RewardRule::create(['name' => 'Test', 'beneficiary_user_id' => $user->id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'event' => 'test', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 1, 'currency' => 'BOB', 'status' => 'active']);
        return RewardTransaction::create(['beneficiary_user_id' => $user->id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'conversion_id' => $conversion->id, 'reward_rule_id' => $rule->id, 'reward_type' => 'CASH', 'amount' => 1, 'currency' => 'BOB', 'status' => $status]);
    }
}
