<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\Membership;
use App\Models\ProgramEnrollment;
use App\Models\ReferralRelationship;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\MembershipPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberReferralJpTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_generates_global_code_and_confirmed_purchase_creates_available_configured_jp_once(): void
    {
        $member = $this->member();
        $friend = User::factory()->create();
        $this->relationship($member, $friend);
        RewardRule::create(['name' => 'Member referral', 'participant_type' => 'MEMBER', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'JP', 'calculation_type' => 'FIXED', 'value' => 137, 'currency' => 'JP', 'priority' => 0, 'status' => 'active']);

        $this->actingAs($member)->get('/mi-jakawi')->assertOk()->assertInertia(fn ($page) => $page->where('memberReferral.eligible', true)->where('memberReferral.code', $member->fresh()->referral_code));
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($friend, User::factory()->create(), null, 'JP-1', (string) Str::uuid());
        $reward = RewardTransaction::sole();
        $this->assertSame($member->id, $reward->beneficiary_user_id);
        $this->assertSame('JP', $reward->reward_type);
        $this->assertSame('JP', $reward->currency);
        $this->assertSame('137.00', $reward->amount);
        $this->assertSame('available', $reward->status);
        $this->actingAs($member)->get('/mi-jakawi')->assertInertia(fn ($page) => $page->where('memberReferral.joined_count', 1)->where('memberReferral.jp_earned', 137)->where('memberReferral.jp_balance', 137));
    }

    public function test_free_or_expired_referrer_does_not_earn_but_history_survives_expiry(): void
    {
        $member = $this->member();
        RewardTransaction::create(['beneficiary_user_id' => $member->id, 'conversion_id' => $this->conversionFor(User::factory()->create())->id, 'reward_rule_id' => $this->jpRule()->id, 'reward_type' => 'JP', 'amount' => 41, 'currency' => 'JP', 'status' => 'available', 'available_at' => now()]);
        $member->activeMembership()->firstOrFail()->update(['ends_at' => now()->subSecond()]);
        $friend = User::factory()->create(); $this->relationship($member, $friend); $this->jpRule();
        app(MembershipPurchaseService::class)->confirmManualCash($friend, User::factory()->create(), null, 'JP-2', (string) Str::uuid());
        $this->assertSame(1, RewardTransaction::count());
        $this->actingAs($member)->get('/mi-jakawi')->assertInertia(fn ($page) => $page->where('memberReferral.eligible', false)->where('memberReferral.jp_balance', 41));
    }

    public function test_commercial_programs_win_over_member_and_refund_cancels_jp(): void
    {
        $member = $this->member(); $friend = User::factory()->create(); $this->relationship($member, $friend); $this->jpRule();
        $promoter = User::factory()->create(); ProgramEnrollment::create(['user_id' => $promoter->id, 'program_type' => 'PROMOTER', 'status' => 'active']);
        RewardRule::create(['name' => 'Promoter', 'participant_type' => 'PROMOTER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 9, 'currency' => 'BOB', 'priority' => 0, 'status' => 'active']);
        app(MembershipPurchaseService::class)->confirmManualCash($friend, $promoter, $promoter, 'JP-3', (string) Str::uuid());
        $this->assertSame('CASH', RewardTransaction::sole()->reward_type);

        $friend2 = User::factory()->create(); $this->relationship($member, $friend2);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($friend2, User::factory()->create(), null, 'JP-4', (string) Str::uuid());
        $jp = RewardTransaction::where('reward_type', 'JP')->sole();
        app(MembershipPurchaseService::class)->refund($sale, User::factory()->create(['is_admin' => true]), 'refund');
        $this->assertSame('cancelled', $jp->fresh()->status);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'amount_paid' => 100]);
        return $user;
    }

    private function relationship(User $member, User $friend): void
    {
        $touch = AttributionTouch::create(['user_id' => $friend->id, 'referrer_user_id' => $member->id, 'referral_code' => $member->referral_code ?? 'MEMBER', 'occurred_at' => now()]);
        ReferralRelationship::create(['referrer_user_id' => $member->id, 'referred_user_id' => $friend->id, 'referral_code' => $touch->referral_code, 'attribution_touch_id' => $touch->id, 'attributed_at' => now(), 'expires_at' => now()->addDays(30), 'status' => 'active']);
    }

    private function jpRule(): RewardRule
    {
        return RewardRule::firstOrCreate(['name' => 'JP'], ['participant_type' => 'MEMBER', 'event' => 'membership_purchased', 'reward_type' => 'JP', 'calculation_type' => 'FIXED', 'value' => 41, 'currency' => 'JP', 'priority' => 0, 'status' => 'active']);
    }

    private function conversionFor(User $user): \App\Models\Conversion
    {
        return \App\Models\Conversion::create(['user_id' => $user->id, 'type' => 'membership_purchased', 'idempotency_key' => (string) Str::uuid(), 'gross_amount' => 100, 'eligible_amount' => 100, 'currency' => 'BOB', 'status' => 'confirmed', 'occurred_at' => now()]);
    }
}
