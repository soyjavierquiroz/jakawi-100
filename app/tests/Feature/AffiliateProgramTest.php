<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\Conversion;
use App\Models\ProgramEnrollment;
use App\Models\ReferralRelationship;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\MembershipPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliateProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_enrolls_existing_or_new_affiliate_safely(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $existing = User::factory()->create();
        $this->actingAs($admin)->post('/admin/affiliates', ['user_id' => $existing->id, 'status' => 'active'])->assertRedirect('/admin/affiliates/'.$existing->id);
        $this->assertTrue($existing->fresh()->hasActiveProgram('AFFILIATE'));
        $this->actingAs($admin)->post('/admin/affiliates', ['name' => 'Nueva Afiliada', 'email' => 'affiliate@example.test', 'status' => 'active'])->assertRedirect();
        $new = User::whereEmail('affiliate@example.test')->firstOrFail();
        $this->assertNotSame('password', $new->password);
        $this->assertNotNull($new->referral_code);
    }

    public function test_dashboard_requires_active_affiliate_and_isolated(): void
    {
        $affiliate = $this->affiliate();
        $normal = User::factory()->create();
        $this->actingAs($affiliate)->get('/affiliate')->assertOk();
        $this->actingAs($normal)->get('/affiliate')->assertForbidden();
        $affiliate->programEnrollments()->firstOrFail()->update(['status' => 'inactive']);
        $this->actingAs($affiliate)->get('/affiliate')->assertForbidden();
    }

    public function test_referral_records_touch_relationship_and_dashboard_metrics(): void
    {
        $affiliate = $this->affiliate('COCHAFOOD');
        $this->get('/r/COCHAFOOD?utm_campaign=launch&utm_content=story')->assertRedirect('/?utm_campaign=launch&utm_content=story');
        $touch = AttributionTouch::where('referrer_user_id', $affiliate->id)->firstOrFail();
        $this->assertSame('launch', $touch->utm_campaign);
        $customer = User::factory()->create();
        $relationship = $this->relationship($affiliate, $customer, $touch);
        $conversion = Conversion::create(['user_id' => $customer->id, 'type' => 'membership_purchased', 'idempotency_key' => (string) Str::uuid(), 'gross_amount' => 100, 'eligible_amount' => 100, 'currency' => 'BOB', 'status' => 'confirmed', 'occurred_at' => now(), 'referral_relationship_id' => $relationship->id]);
        $this->actingAs($affiliate)->get('/affiliate')->assertInertia(fn ($page) => $page->where('metrics.clicks', 1)->where('metrics.registrations', 1)->where('metrics.purchases', 1)->where('metrics.revenue', '100.00'));
        $conversion->update(['status' => 'refunded']);
        $this->actingAs($affiliate)->get('/affiliate')->assertInertia(fn ($page) => $page->where('metrics.purchases', 0)->where('metrics.revenue', '0'));
    }

    public function test_first_valid_affiliate_gets_one_reward_when_no_credited_seller_and_rule_precedence_does_not_stack(): void
    {
        $affiliate = $this->affiliate();
        $customer = User::factory()->create();
        $this->relationship($affiliate, $customer);
        RewardRule::create(['name' => 'Affiliate standard', 'participant_type' => 'AFFILIATE', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => 'PERCENTAGE', 'value' => 10, 'currency' => 'BOB', 'priority' => 0, 'status' => 'active']);
        RewardRule::create(['name' => 'Individual', 'beneficiary_user_id' => $affiliate->id, 'participant_type' => 'AFFILIATE', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 25, 'currency' => 'BOB', 'priority' => 0, 'status' => 'active']);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($customer, User::factory()->create(), null, 'REC-1', (string) Str::uuid());
        $this->assertSame(1, RewardTransaction::count());
        $this->assertSame($affiliate->id, RewardTransaction::firstOrFail()->beneficiary_user_id);
        $this->assertSame('25.00', RewardTransaction::firstOrFail()->amount);
        $this->assertSame($affiliate->id, $sale->conversion->relationship->referrer_user_id);
    }

    public function test_promoter_context_wins_and_preserves_affiliate_attribution_without_double_reward(): void
    {
        $affiliate = $this->affiliate();
        $promoter = $this->promoter();
        $customer = User::factory()->create();
        $this->relationship($affiliate, $customer);
        foreach (['AFFILIATE', 'PROMOTER'] as $type) {
            RewardRule::create(['name' => $type, 'participant_type' => $type, 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 10, 'currency' => 'BOB', 'priority' => 0, 'status' => 'active']);
        }
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($customer, $promoter, $promoter, 'REC-2', (string) Str::uuid());
        $this->assertSame(1, RewardTransaction::count());
        $this->assertSame($promoter->id, RewardTransaction::firstOrFail()->beneficiary_user_id);
        $this->assertSame($affiliate->id, $sale->conversion->relationship->referrer_user_id);
        $this->assertSame($promoter->id, $sale->conversion->attribution_snapshot['credited_seller_user_id']);
    }

    public function test_no_rule_succeeds_refund_cancels_affiliate_reward_and_only_admin_can_operate_reward(): void
    {
        $affiliate = $this->affiliate();
        $customer = User::factory()->create();
        $this->relationship($affiliate, $customer);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($customer, User::factory()->create(), null, 'REC-3', (string) Str::uuid());
        $this->assertSame(0, RewardTransaction::count());
        RewardRule::create(['name' => 'Affiliate', 'participant_type' => 'AFFILIATE', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 10, 'currency' => 'BOB', 'priority' => 0, 'status' => 'active']);
        $rewardedCustomer = User::factory()->create();
        $this->relationship($affiliate, $rewardedCustomer);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($rewardedCustomer, User::factory()->create(), null, 'REC-4', (string) Str::uuid());
        $reward = RewardTransaction::firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($affiliate)->post('/admin/affiliates/'.$affiliate->id.'/rewards/'.$reward->id.'/available')->assertForbidden();
        $this->actingAs($admin)->post('/admin/affiliates/'.$affiliate->id.'/rewards/'.$reward->id.'/available')->assertRedirect();
        $this->assertSame('available', $reward->fresh()->status);
        $this->actingAs($admin)->post('/admin/sales/'.$sale->id.'/refund', ['reason' => 'Anulada'])->assertRedirect();
        $this->assertSame('cancelled', $reward->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_reward_made_available']);
    }

    private function affiliate(?string $code = null): User
    {
        $user = User::factory()->create(['referral_code' => $code, 'referral_code_normalized' => $code]);
        ProgramEnrollment::create(['user_id' => $user->id, 'program_type' => 'AFFILIATE', 'status' => 'active']);

        return $user;
    }

    private function promoter(): User
    {
        $user = User::factory()->create();
        ProgramEnrollment::create(['user_id' => $user->id, 'program_type' => 'PROMOTER', 'status' => 'active']);

        return $user;
    }

    private function relationship(User $affiliate, User $customer, ?AttributionTouch $touch = null): ReferralRelationship
    {
        $touch ??= AttributionTouch::create(['user_id' => $customer->id, 'referrer_user_id' => $affiliate->id, 'referral_code' => $affiliate->referral_code ?? 'AFFILIATE', 'occurred_at' => now()]);

        return ReferralRelationship::create(['referrer_user_id' => $affiliate->id, 'referred_user_id' => $customer->id, 'referral_code' => $touch->referral_code, 'attribution_touch_id' => $touch->id, 'attributed_at' => now(), 'expires_at' => now()->addDays(30), 'status' => 'active']);
    }
}
