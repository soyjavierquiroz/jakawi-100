<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use App\Services\MembershipService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PioneerMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function membership(string $type = Membership::TYPE_PIONEER): Membership
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 15:00:00', 'America/La_Paz'));
        $user = User::factory()->create();
        $m = app(MembershipService::class)->activate($user, $user);
        $m->update(['type' => $type]);
        return $m->refresh();
    }

    public function test_pioneer_is_active_after_historical_expiry_without_launch_and_cancellation_still_wins(): void
    {
        config(['jakawi.membership.official_launch_date' => null]);
        $m = $this->membership();
        $original = $m->getRawOriginal();
        $this->travelTo(CarbonImmutable::parse('2030-01-01', 'America/La_Paz'));
        $this->assertTrue($m->isActive());
        $this->assertTrue($m->user->hasActiveMembership());
        $this->assertTrue(Membership::active()->whereKey($m->id)->exists());
        $this->assertFalse(Membership::expired()->whereKey($m->id)->exists());
        $this->assertNull($m->effectiveEndsAt());
        app(MembershipService::class)->cancel($m);
        $this->assertFalse($m->fresh()->isActive());
        $this->assertFalse($m->user->hasActiveMembership());
        foreach (['starts_at', 'ends_at', 'amount_paid', 'user_id'] as $field) {
            $this->assertSame($original[$field], $m->fresh()->getRawOriginal($field));
        }
    }

    public function test_launch_plus_fifteen_calendar_months_inclusive_bolivia_end_of_day_and_no_month_overflow(): void
    {
        $m = $this->membership();
        config(['jakawi.membership.official_launch_date' => '2027-01-31']);
        $end = CarbonImmutable::parse('2028-04-30 23:59:59', 'America/La_Paz');
        $this->assertSame('2028-05-01 03:59:59', $m->effectiveEndsAt()->format('Y-m-d H:i:s'));
        $this->travelTo($end);
        $this->assertTrue($m->isActive());
        $this->assertTrue($m->user->hasActiveMembership());
        $this->travelTo($end->endOfDay());
        $this->assertTrue($m->isActive());
        $this->assertTrue($m->user->hasActiveMembership());
        $this->travelTo($end->addSecond());
        $this->assertFalse($m->isActive());
        $this->assertFalse($m->user->hasActiveMembership());
        $this->assertTrue(Membership::expired()->whereKey($m->id)->exists());
    }

    public function test_standard_purchase_keeps_365_days_and_never_gets_pioneer_automatically(): void
    {
        $m = $this->membership(Membership::TYPE_STANDARD);
        $this->assertEquals(365, $m->starts_at->diffInDays($m->ends_at));
        $this->travelTo($m->ends_at->copy()->addSecond());
        $this->assertFalse($m->isActive());
        $this->assertFalse($m->user->hasActiveMembership());
        $this->assertSame('standard', $m->type);
    }

    public function test_existing_benefit_access_accepts_pioneer_after_standard_expiry_and_rejects_cancellation(): void
    {
        config(['jakawi.membership.official_launch_date' => null]);
        $m = $this->membership();
        $this->travelTo($m->ends_at->copy()->addDay());
        $partner = \App\Models\Partner::factory()->published()->create();
        $location = \App\Models\Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = \App\Models\Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        $service = app(\App\Services\RedemptionService::class);
        $redemption = $service->start($m->user, $benefit, $location);
        $this->assertSame($m->id, $redemption->membership_id);
        app(MembershipService::class)->cancel($m);
        $this->expectException(\DomainException::class);
        $service->confirm($redemption->code, '123456');
    }

    public function test_invalid_launch_date_cannot_silently_roll_into_a_different_month(): void
    {
        config(['jakawi.membership.official_launch_date' => '2027-02-30']);
        $this->expectException(\InvalidArgumentException::class);
        Membership::pioneerEndsAt();
    }

    public function test_admin_presents_type_effective_expiry_and_historical_date_separately(): void
    {
        config(['jakawi.membership.official_launch_date' => null]);
        $m = $this->membership();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->travelTo($m->ends_at->copy()->addYear());
        $this->actingAs($admin)->get('/admin/memberships?status=active')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('memberships.data.0.type_label', 'Pionero')->where('memberships.data.0.status', 'active')
            ->where('memberships.data.0.ends_at', null)->where('memberships.data.0.expiry_pending_launch', true)
            ->where('summary.expired', 0));
        $this->get('/admin/memberships/'.$m->id)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('membership.type', 'pioneer')->where('membership.ends_at', null)
            ->where('membership.historical_ends_at', $m->ends_at->toISOString()));
        $m->update(['starts_at' => now()->addDay()]);
        $this->assertFalse($m->fresh()->isActive());
        $this->assertFalse($m->user->hasActiveMembership());
    }
}
