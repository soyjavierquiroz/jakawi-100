<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\RedemptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_user_can_open_portal_and_member_cannot(): void
    {
        $partner = Partner::factory()->create();
        $partnerUser = $this->partnerUser($partner, 'manager');

        $this->actingAs($partnerUser)->get('/partner/'.$partner->slug)->assertOk()->assertSee($partner->name);
        $this->actingAs($partnerUser)->get('/admin')->assertForbidden();

        $member = User::factory()->create();
        $this->actingAs($member)->get('/partner')->assertForbidden();
        $this->actingAs($member)->get('/partner/reservas')->assertForbidden();
        $this->actingAs($member)->get('/validar')->assertForbidden();
    }

    public function test_partner_cannot_validate_another_partners_redemption(): void
    {
        $partnerA = Partner::factory()->published()->create();
        $partnerB = Partner::factory()->published()->create();
        $validatorA = $this->partnerUser($partnerA, 'staff');
        $redemption = $this->redemptionFor($partnerB);

        $this->actingAs($validatorA)
            ->post('/partner/'.$partnerA->slug.'/validar', ['code' => $redemption->code, 'pin' => '123456'])
            ->assertForbidden();

        $this->assertSame(Redemption::STATUS_PENDING, $redemption->fresh()->status);
    }

    public function test_admin_without_partner_relation_cannot_access_partner_portal_or_validation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $partner = Partner::factory()->published()->create();
        $redemption = $this->redemptionFor($partner);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/partner')->assertForbidden();
        $this->actingAs($admin)
            ->post('/partner/'.$partner->slug.'/validar', ['code' => $redemption->code, 'pin' => '123456'])
            ->assertForbidden();

        $this->assertSame(Redemption::STATUS_PENDING, $redemption->fresh()->status);
    }

    public function test_multi_partner_user_can_only_open_related_partner_context(): void
    {
        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();
        $partnerC = Partner::factory()->create();
        $user = $this->partnerUser($partnerA, 'manager');
        $user->partners()->attach($partnerB, ['role' => 'staff']);

        $this->actingAs($user)->get('/partner')->assertOk()->assertSee($partnerA->name, false)->assertSee($partnerB->name, false);
        $this->actingAs($user)->get('/partner/'.$partnerA->slug.'/reservas')->assertOk();
        $this->actingAs($user)->get('/partner/'.$partnerC->slug)->assertForbidden();
    }

    private function partnerUser(Partner $partner, string $role): User
    {
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => $role]);

        return $user;
    }

    private function redemptionFor(Partner $partner): Redemption
    {
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create([
            'applies_to_all_locations' => true,
        ]);
        $member = User::factory()->create();
        app(MembershipService::class)->activate($member, User::factory()->create());

        return app(RedemptionService::class)->start($member, $benefit, $location);
    }
}
