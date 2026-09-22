<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\RedemptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MembershipRedemptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_has_configured_price_duration_and_activator(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $membership = app(MembershipService::class)->activate($user, $admin);
        $this->assertSame('100.00', $membership->amount_paid);
        $this->assertSame('2027-09-22 12:00:00', $membership->ends_at->toDateTimeString());
        $this->assertSame($admin->id, $membership->activated_by);
        $this->assertTrue($membership->isActive());
        Carbon::setTestNow();
    }

    public function test_membership_active_rules_and_history(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $service = app(MembershipService::class);
        $active = $service->activate($user, $admin, Carbon::now()->subDay(), amountPaid: '95.50');
        $this->assertSame('95.50', $active->amount_paid);
        $this->assertSame($active->id, $user->activeMembership()->first()->id);
        $this->expectException(DomainException::class);
        $service->activate($user, $admin);
    }

    public function test_future_expired_and_cancelled_memberships_are_not_active(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        $user = User::factory()->create();
        foreach ([[now()->addDay(), now()->addDays(2), 'active'], [now()->subDays(2), now()->subDay(), 'active'], [now()->subDay(), now()->addDay(), 'cancelled']] as [$start, $end, $status]) {
            Membership::create(['user_id' => $user->id, 'status' => $status, 'starts_at' => $start, 'ends_at' => $end, 'amount_paid' => '100.00']);
        }
        $this->assertFalse($user->hasActiveMembership());
        $this->assertCount(0, Membership::active()->get());
        Carbon::setTestNow();
    }

    public function test_cancel_preserves_history_and_allows_a_new_membership(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $service = app(MembershipService::class);
        $old = $service->activate($user, $admin);
        $service->cancel($old);
        $new = $service->activate($user, $admin);
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertNotSame($old->id, $new->id);
    }

    public function test_start_requires_active_membership_and_reuses_valid_pending_code(): void
    {
        [$user, $benefit, $location] = $this->redeemable();
        $service = app(RedemptionService::class);
        $this->expectException(DomainException::class);
        $service->start($user, $benefit, $location);
    }

    public function test_start_creates_location_aware_snapshots_and_code(): void
    {
        [$user, $benefit, $location, $membership] = $this->redeemable(true);
        $service = app(RedemptionService::class);
        $one = $service->start($user, $benefit, $location);
        $two = $service->start($user, $benefit, $location);
        $this->assertSame($one->id, $two->id);
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $one->code);
        $this->assertTrue(str_starts_with($one->public_id, '01'));
        $this->assertSame('25.00', $one->savings_amount);
        $this->assertSame($benefit->partner->name, $one->partner_name);
        $this->assertSame($location->name, $one->location_name);
        $this->assertSame($benefit->title, $one->benefit_title);
        $this->assertSame($membership->id, $one->membership_id);
        $this->assertTrue($one->expires_at->between(now()->addMinutes(9), now()->addMinutes(11)));
    }

    public function test_start_rejects_invalid_benefit_location_or_missing_pin(): void
    {
        [$user, $benefit, $location] = $this->redeemable(true);
        $service = app(RedemptionService::class);
        $location->update(['status' => 'draft']);
        $this->expectException(DomainException::class);
        $service->start($user, $benefit, $location);
    }

    public function test_confirm_is_idempotent_and_uses_the_location_pin(): void
    {
        [$user, $benefit, $location] = $this->redeemable(true);
        $service = app(RedemptionService::class);
        $pending = $service->start($user, $benefit, $location);
        try {
            $service->confirm($pending->code, '000000');
            $this->fail('Wrong PIN accepted.');
        } catch (DomainException) {
        }
        $this->assertSame('pending', $pending->fresh()->status);
        $confirmed = $service->confirm($pending->code, '123456');
        $again = $service->confirm($pending->code, '999999');
        $this->assertSame('confirmed', $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertSame($confirmed->id, $again->id);
    }

    public function test_confirm_revalidates_and_expires_codes(): void
    {
        [$user, $benefit, $location] = $this->redeemable(true);
        $service = app(RedemptionService::class);
        $pending = $service->start($user, $benefit, $location);
        $pending->update(['expires_at' => now()->subSecond()]);
        try {
            $service->confirm($pending->code, '123456');
            $this->fail('Expired code accepted.');
        } catch (DomainException) {
        }
        $this->assertSame('expired', $pending->fresh()->status);
    }

    public function test_limit_counts_only_confirmed_and_unlimited_allows_many(): void
    {
        [$user, $benefit, $location] = $this->redeemable(true);
        $service = app(RedemptionService::class);
        $first = $service->start($user, $benefit, $location);
        $service->confirm($first->code, '123456');
        $this->expectException(DomainException::class);
        $service->start($user, $benefit, $location);
    }

    public function test_snapshots_and_roi_are_historical(): void
    {
        [$user, $benefit, $location, $membership] = $this->redeemable(true);
        $service = app(RedemptionService::class);
        $one = $service->start($user, $benefit, $location);
        $service->confirm($one->code, '123456');
        $benefit->update(['redemption_limit_per_member' => null, 'estimated_savings' => '28.00', 'title' => 'Changed']);
        $benefit->partner->update(['name' => 'Changed partner']);
        $location->update(['name' => 'Changed location']);
        $this->assertSame('25.00', $one->fresh()->savings_amount);
        $this->assertNotSame('Changed', $one->fresh()->benefit_title);
        $two = $service->start($user, $benefit->fresh(), $location->fresh());
        $service->confirm($two->code, '123456');
        $this->assertSame('53.00', $membership->fresh()->confirmedSavings());
        $this->assertSame('47.00', $membership->fresh()->remainingToPayback());
        $this->assertFalse($membership->fresh()->hasPaidForItself());
    }

    /** @return array{User, Benefit, Location, Membership|null} */
    private function redeemable(bool $membership = false): array
    {
        $partner = Partner::factory()->published()->create();
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['estimated_savings' => '25.00', 'applies_to_all_locations' => true]);
        $user = User::factory()->create();
        $record = $membership ? app(MembershipService::class)->activate($user, User::factory()->create()) : null;

        return [$user, $benefit, $location, $record];
    }
}
