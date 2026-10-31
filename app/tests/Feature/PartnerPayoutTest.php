<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Conversion;
use App\Models\Partner;
use App\Models\RewardPayout;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\RewardPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PartnerPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_payout_route_is_retired_and_historical_rewards_are_preserved(): void
    {
        $partner = Partner::factory()->published()->create();
        $owner = $this->actor($partner, 'owner');
        $reward = $this->reward($partner, 25, 'available');
        $this->assertFalse(Route::has('partner.payouts.store'));
        $this->actingAs($owner)->post("/partner/{$partner->slug}/payouts")->assertNotFound();
        $this->assertDatabaseCount('reward_payouts', 0);
        $this->assertSame('available', $reward->fresh()->status);
        $this->actingAs($owner)->get("/partner/{$partner->slug}")->assertInertia(fn ($page) => $page
            ->missing('acquisition')->missing('rewards')->missing('partnerPayout')->missing('partner.referral_link'));
    }

    public function test_shared_service_cannot_reserve_membership_acquisition_rewards_for_new_payouts(): void
    {
        $partner = Partner::factory()->published()->create();
        $owner = $this->actor($partner, 'owner');
        $this->reward($partner, 50, 'available');
        try {
            app(RewardPayoutService::class)->requestPartner($partner, $owner);
            $this->fail('A membership acquisition payout must not be created in COM.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('reward_payouts', 0);
        $this->assertDatabaseHas('reward_transactions', ['status' => 'available']);
    }

    public function test_admin_pays_idempotently_or_rejects_partner_payout(): void
    {
        AppSetting::create(['key' => 'partner_minimum_payout', 'value' => ['amount' => 0]]);
        $partner = Partner::factory()->create();
        $owner = $this->actor($partner, 'owner');
        $reward = $this->reward($partner, 25, 'available');
        $payout = $this->historicalPayout($partner, $owner, $reward);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/payouts')->assertOk();
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", [])->assertSessionHasErrors('payment_reference');
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", ['payment_reference' => 'PARTNER-EXT'])->assertRedirect();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('paid', $reward->fresh()->status);
        $this->actingAs($admin)->post("/admin/payouts/{$payout->id}/paid", ['payment_reference' => 'changed'])->assertRedirect();
        $this->assertSame('PARTNER-EXT', $payout->fresh()->payment_reference);
        $reward2 = $this->reward($partner, 10, 'available');
        $second = $this->historicalPayout($partner, $owner, $reward2);
        $this->actingAs($admin)->post("/admin/payouts/{$second->id}/reject", [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/payouts/{$second->id}/reject", ['reason' => 'Datos incompletos'])->assertRedirect();
        $this->assertSame('available', $reward2->fresh()->status);
    }

    private function historicalPayout(Partner $partner, User $actor, RewardTransaction $reward): RewardPayout
    {
        $payout = RewardPayout::create(['reference' => 'HIST-'.Str::random(8), 'beneficiary_type' => 'PARTNER',
            'beneficiary_id' => $partner->id, 'requested_by_user_id' => $actor->id, 'status' => 'requested',
            'requested_at' => now(), 'requested_amount' => $reward->amount, 'currency' => 'BOB']);
        $payout->rewards()->attach($reward);

        return $payout;
    }

    private function actor(Partner $partner, string $role): User
    {
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => $role]);

        return $user;
    }

    private function reward(Partner $partner, float $amount, string $status, string $type = 'CASH', string $currency = 'BOB'): RewardTransaction
    {
        $conversion = Conversion::create(['user_id' => User::factory()->create()->id, 'type' => 'membership_purchased', 'idempotency_key' => (string) Str::uuid(), 'gross_amount' => 100, 'eligible_amount' => 100, 'currency' => 'BOB', 'status' => 'confirmed', 'occurred_at' => now()]);
        $rule = RewardRule::create(['name' => 'Partner test', 'beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id, 'participant_type' => 'PARTNER', 'event' => 'membership_purchased', 'reward_type' => $type, 'calculation_type' => 'FIXED', 'value' => $amount, 'currency' => $currency, 'status' => 'active']);

        return RewardTransaction::create(['beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id, 'conversion_id' => $conversion->id, 'reward_rule_id' => $rule->id, 'reward_type' => $type, 'amount' => $amount, 'currency' => $currency, 'status' => $status, 'available_at' => $status === 'available' ? now() : null]);
    }
}
