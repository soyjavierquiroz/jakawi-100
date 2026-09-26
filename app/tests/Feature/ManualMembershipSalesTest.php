<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\MembershipPurchase;
use App\Models\ProgramEnrollment;
use App\Models\ReferralRelationship;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\MembershipPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ManualMembershipSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_promoter_enrollment_controls_portal_and_sales_are_isolated(): void
    {
        $promoter = $this->promoter(); $other = $this->promoter(); $member = User::factory()->create();
        $sale = $this->sale($other, User::factory()->create());
        $this->actingAs($promoter)->get('/promoter/sales')->assertOk();
        $this->actingAs($member)->get('/promoter/sales')->assertForbidden();
        $this->actingAs($promoter)->get('/promoter/sales/'.$sale->id)->assertForbidden();
    }

    public function test_promoter_creates_safe_customer_and_manual_sale_uses_config(): void
    {
        Notification::fake(); $promoter = $this->promoter(); config()->set('jakawi.membership.price_bob', 135); config()->set('jakawi.membership.duration_days', 400);
        $this->actingAs($promoter)->post('/promoter/sales', ['name' => 'Cliente Seguro', 'email' => 'secure@example.test', 'manual_reference' => 'REC-44', 'idempotency_key' => '7c1c3afd-1c25-4f83-81a7-981918b0c522'])->assertRedirect();
        $customer = User::where('email', 'secure@example.test')->firstOrFail(); $sale = MembershipPurchase::firstOrFail();
        $this->assertNotSame('password', $customer->password); $this->assertSame('REC-44', $sale->manual_reference); $this->assertSame('135.00', $sale->amount); $this->assertNotNull($sale->membership_id); $this->assertSame(400, $sale->duration_days); $this->assertSame('membership_purchased', $sale->conversion->type); $this->assertTrue(str_starts_with($sale->reference, 'MS-'));
    }

    public function test_marketing_attribution_is_preserved_while_promoter_is_credited(): void
    {
        $promoter = $this->promoter(); $marketing = User::factory()->create(); $customer = User::factory()->create();
        $touch = AttributionTouch::create(['user_id' => $customer->id, 'referrer_user_id' => $marketing->id, 'referral_code' => 'MARKET', 'utm_source' => 'tiktok', 'landing_page' => '/', 'occurred_at' => now()]);
        ReferralRelationship::create(['referrer_user_id' => $marketing->id, 'referred_user_id' => $customer->id, 'referral_code' => 'MARKET', 'attribution_touch_id' => $touch->id, 'attributed_at' => now(), 'expires_at' => now()->addDays(10), 'status' => 'active']);
        $sale = $this->sale($promoter, $customer); $snapshot = $sale->conversion->attribution_snapshot;
        $this->assertSame($marketing->id, $snapshot['referrer_user_id']); $this->assertSame('tiktok', $snapshot['utm_source']); $this->assertSame($promoter->id, $snapshot['credited_seller_user_id']);
    }

    public function test_matching_reward_is_pending_and_retry_does_not_duplicate_financial_records(): void
    {
        $promoter = $this->promoter(); RewardRule::create(['name' => 'Promotor 10%', 'participant_type' => 'PROMOTER', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => 'PERCENTAGE', 'value' => 10, 'currency' => 'BOB', 'priority' => 0, 'status' => 'active']);
        $customer = User::factory()->create(); $key = '6a415fb1-d4b0-4dfa-a86f-2d0f9c07a6d0'; $first = app(MembershipPurchaseService::class)->confirmManualCash($customer, $promoter, $promoter, 'R-1', $key); $second = app(MembershipPurchaseService::class)->confirmManualCash($customer, $promoter, $promoter, 'R-1', $key);
        $this->assertSame($first->id, $second->id); $this->assertSame(1, MembershipPurchase::count()); $this->assertSame(1, RewardTransaction::count()); $this->assertSame('pending', RewardTransaction::first()->status); $this->assertSame('10.00', RewardTransaction::first()->amount);
    }

    public function test_no_rule_still_succeeds_and_fixed_rule_is_calculated(): void
    {
        $promoter = $this->promoter(); $this->sale($promoter, User::factory()->create()); $this->assertSame(0, RewardTransaction::count());
        RewardRule::create(['name' => 'Fijo', 'participant_type' => 'PROMOTER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 17, 'priority' => 0, 'status' => 'active']); $this->sale($promoter, User::factory()->create());
        $this->assertSame('17.00', RewardTransaction::firstOrFail()->amount);
    }

    public function test_only_admin_refunds_and_refund_preserves_history_and_cancels_reward(): void
    {
        $promoter = $this->promoter(); RewardRule::create(['name' => 'Fijo', 'participant_type' => 'PROMOTER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 5, 'priority' => 0, 'status' => 'active']); $sale = $this->sale($promoter, User::factory()->create()); $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($promoter)->post('/admin/sales/'.$sale->id.'/refund', ['reason' => 'No procede'])->assertForbidden();
        $this->actingAs($admin)->post('/admin/sales/'.$sale->id.'/refund', ['reason' => 'Recibo anulado'])->assertRedirect(); $sale->refresh();
        $this->assertSame('refunded', $sale->status); $this->assertSame('cancelled', $sale->membership->fresh()->status); $this->assertSame('refunded', $sale->conversion->fresh()->status); $this->assertSame('cancelled', RewardTransaction::firstOrFail()->fresh()->status);
    }

    private function promoter(): User { $user = User::factory()->create(); ProgramEnrollment::create(['user_id' => $user->id, 'program_type' => 'PROMOTER', 'status' => 'active']); return $user; }
    private function sale(User $promoter, User $customer): MembershipPurchase { return app(MembershipPurchaseService::class)->confirmManualCash($customer, $promoter, $promoter, 'REC-'.uniqid(), (string) \Illuminate\Support\Str::uuid()); }
}
