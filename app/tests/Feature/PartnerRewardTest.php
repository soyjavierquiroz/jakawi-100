<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\Partner;
use App\Models\ProgramEnrollment;
use App\Models\ReferralRelationship;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\MembershipPurchaseService;
use App\Services\RewardResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnerRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_attribution_cannot_earn_cash_even_with_legacy_global_and_specific_rules(): void
    {
        $partner = Partner::factory()->published()->create();
        $customer = User::factory()->create();
        $this->attribute($partner, $customer);
        RewardRule::create(['name' => 'Partner default', 'participant_type' => 'PARTNER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 10, 'currency' => 'BOB', 'status' => 'active']);
        $specific = RewardRule::create(['name' => 'Partner individual', 'beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id, 'participant_type' => 'PARTNER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 15, 'currency' => 'BOB', 'status' => 'active']);
        $sale = $this->sale($customer);
        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertDatabaseHas('reward_rules', ['id' => $specific->id, 'value' => 15]);
        $this->assertTrue($customer->fresh()->hasActiveMembership());
        $this->assertNull(app(RewardResolver::class)->createFor($sale->conversion, $partner, 'PARTNER'));
        $this->assertNull(app(RewardResolver::class)->ruleFor($partner, 'PARTNER', 'PARTNER', 'membership_purchased', null));
        $this->assertSame($partner->id, $sale->conversion->relationship->acquisition_partner_id);
    }

    public function test_no_partner_rule_does_not_block_conversion_and_retired_promoter_rule_cannot_accrue(): void
    {
        $partner = Partner::factory()->published()->create();
        $customer = User::factory()->create();
        $this->attribute($partner, $customer);
        $this->sale($customer);
        $this->assertSame(0, RewardTransaction::count());

        $promoter = User::factory()->create();
        ProgramEnrollment::create(['user_id' => $promoter->id, 'program_type' => 'PROMOTER', 'status' => 'active']);
        RewardRule::create(['name' => 'Promoter', 'participant_type' => 'PROMOTER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 9, 'currency' => 'BOB', 'status' => 'active']);
        $other = User::factory()->create();
        $this->attribute($partner, $other);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($other, $promoter, $promoter, 'P-'.Str::random(8), (string) Str::uuid());
        $this->assertSame(0, RewardTransaction::count());
        $this->assertSame($partner->id, $sale->conversion->relationship->acquisition_partner_id);
    }

    public function test_retry_and_refund_keep_legacy_partner_reward_history_without_new_accrual(): void
    {
        $partner = Partner::factory()->published()->create();
        $customer = User::factory()->create();
        $this->attribute($partner, $customer);
        RewardRule::create(['name' => 'Partner', 'participant_type' => 'PARTNER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 10, 'currency' => 'BOB', 'status' => 'active']);
        $key = (string) Str::uuid();
        $sale = $this->sale($customer, $key);
        $this->sale($customer, $key);
        $this->assertDatabaseCount('reward_transactions', 0);
        // Simulate a historical obligation, not a newly generated commission.
        RewardTransaction::create(['beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id,
            'conversion_id' => $sale->conversion_id, 'reward_rule_id' => RewardRule::sole()->id,
            'reward_type' => 'CASH', 'amount' => 10, 'currency' => 'BOB', 'status' => 'pending']);
        app(MembershipPurchaseService::class)->refund($sale, User::factory()->create(['is_admin' => true]), 'refund');
        $this->assertSame(RewardTransaction::STATUS_CANCELLED, RewardTransaction::firstOrFail()->status);
    }

    private function attribute(Partner $partner, User $customer): void
    {
        $touch = AttributionTouch::create(['user_id' => $customer->id, 'acquisition_partner_id' => $partner->id, 'referral_code' => 'PARTNER', 'occurred_at' => now()]);
        ReferralRelationship::create(['acquisition_partner_id' => $partner->id, 'referred_user_id' => $customer->id, 'referral_code' => 'PARTNER', 'attribution_touch_id' => $touch->id, 'attributed_at' => now(), 'expires_at' => now()->addDays(30), 'status' => 'active']);
    }

    private function sale(User $customer, ?string $key = null)
    {
        $admin = User::factory()->create(['is_admin' => true]);

        return app(MembershipPurchaseService::class)->confirmManualCash($customer, $admin, null, 'R-'.Str::random(8), $key ?? (string) Str::uuid());
    }
}
