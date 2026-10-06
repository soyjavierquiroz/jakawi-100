<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\MembershipPurchase;
use App\Models\OwnershipAssignment;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\ProgramApplication;
use App\Models\ProgramEnrollment;
use App\Models\ReferralRelationship;
use App\Models\RewardPayout;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\AttributionService;
use App\Services\MembershipPurchaseService;
use App\Services\OwnershipAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnershipAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $program = ProgramEnrollment::TYPE_AFFILIATE): User
    {
        $user = User::factory()->create();
        $user->programEnrollments()->create(['program_type' => $program, 'status' => 'active']);
        return $user;
    }

    private function register(string $email = 'new-owner@example.test'): User
    {
        $this->post('/register', ['name' => 'New User', 'email' => $email, 'whatsapp' => '71234567'])->assertRedirect();
        return User::where('email', $email)->firstOrFail();
    }

    private function partnerApplicationData(): array
    {
        return ['business_name' => 'Café Ruta', 'contact_name' => 'Ana', 'contact_email' => 'ana@example.test'];
    }

    public function test_registration_uses_only_an_eligible_user_referral_and_remains_sticky(): void
    {
        $referrer = $this->owner();
        $referrer->forceFill(['referral_code' => 'ELIGIBLE', 'referral_code_normalized' => 'ELIGIBLE'])->save();
        $this->get('/r/ELIGIBLE')->assertRedirect();
        $new = $this->register();
        $assignment = app(OwnershipAssignmentService::class)->current($new);
        $this->assertSame($referrer->id, $assignment->owner_user_id);
        $this->assertSame(OwnershipAssignment::SOURCE_REFERRAL_RELATIONSHIP, $assignment->source);

        $other = $this->owner(ProgramEnrollment::TYPE_CREATOR);
        $touch = AttributionTouch::create(['user_id' => $new->id, 'referrer_user_id' => $other->id, 'referral_code' => 'OTHER', 'occurred_at' => now()]);
        app(AttributionService::class)->applyFirstValidReferrer($new, $touch);
        app(OwnershipAssignmentService::class)->autoAssignUserFromReferral($new);
        $this->assertSame($assignment->id, app(OwnershipAssignmentService::class)->current($new)->id);
        $this->assertDatabaseCount('ownership_assignments', 1);
    }

    public function test_regular_referrer_and_partner_referrer_do_not_assign_registration(): void
    {
        $regular = User::factory()->create(['referral_code' => 'REGULAR', 'referral_code_normalized' => 'REGULAR']);
        $this->get('/r/REGULAR');
        $first = $this->register('regular@example.test');
        $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $first->id, 'referrer_user_id' => $regular->id]);
        $this->assertNull(app(OwnershipAssignmentService::class)->current($first));

        auth()->logout();
        $partner = Partner::factory()->create(['referral_code' => 'PARTNERCODE', 'referral_code_normalized' => 'PARTNERCODE']);
        $this->get('/r/PARTNERCODE');
        $second = $this->register('partner@example.test');
        $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $second->id, 'acquisition_partner_id' => $partner->id]);
        $this->assertNull(app(OwnershipAssignmentService::class)->current($second));
    }

    public function test_program_application_inherits_user_owner_once_or_stays_unassigned(): void
    {
        $owner = $this->owner();
        $applicant = User::factory()->create();
        app(OwnershipAssignmentService::class)->assignIfUnowned($applicant, $owner, OwnershipAssignment::SOURCE_REFERRAL_RELATIONSHIP);
        $this->actingAs($applicant)->post('/creadores/solicitudes', [])->assertRedirect();
        $application = ProgramApplication::sole();
        $assignment = app(OwnershipAssignmentService::class)->current($application);
        $this->assertSame($owner->id, $assignment->owner_user_id);
        $this->assertSame(OwnershipAssignment::SOURCE_USER_OWNERSHIP, $assignment->source);
        $this->post('/creadores/solicitudes', [])->assertRedirect();
        $this->assertDatabaseCount('ownership_assignments', 2);

        $other = User::factory()->create();
        $this->actingAs($other)->post('/afiliados/solicitudes', [])->assertRedirect();
        $this->assertNull(app(OwnershipAssignmentService::class)->current(ProgramApplication::where('user_id', $other->id)->sole()));
    }

    public function test_partner_application_inherits_authenticated_owner_and_guest_requires_explicit_user_referral(): void
    {
        $city = City::where('slug', 'la-paz')->sole();
        $owner = $this->owner();
        $applicant = User::factory()->create();
        app(OwnershipAssignmentService::class)->assignIfUnowned($applicant, $owner, OwnershipAssignment::SOURCE_REFERRAL_RELATIONSHIP);
        $this->actingAs($applicant)->post('/ciudades/la-paz/partner', $this->partnerApplicationData())->assertRedirect();
        $authenticated = PartnerApplication::where('user_id', $applicant->id)->sole();
        $this->assertSame($owner->id, app(OwnershipAssignmentService::class)->current($authenticated)->owner_user_id);

        auth()->logout();
        $visitor = 'e4a3c55e-9107-4cd0-92dc-e44d73d087b4';
        $touch = AttributionTouch::create(['anonymous_id' => $visitor, 'referral_code' => 'ELIGIBLE', 'referrer_user_id' => $owner->id, 'occurred_at' => now()]);
        $data = ['business_name' => 'Otro Café', 'contact_name' => 'Berta', 'contact_email' => 'berta@example.test'];
        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/partner', $data)->assertRedirect();
        $guest = PartnerApplication::where('business_name', 'Otro Café')->sole();
        $this->assertSame($touch->id, $guest->attribution_touch_id);
        $this->assertSame(OwnershipAssignment::SOURCE_CONVERSION_REFERRAL, app(OwnershipAssignmentService::class)->current($guest)->source);

        $other = $this->owner(ProgramEnrollment::TYPE_CREATOR);
        AttributionTouch::create(['anonymous_id' => $visitor, 'referral_code' => 'OTHER', 'referrer_user_id' => $other->id, 'occurred_at' => now()->addSecond()]);
        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/partner', $data)->assertRedirect();
        $this->assertSame($owner->id, app(OwnershipAssignmentService::class)->current($guest)->owner_user_id);
        $this->assertSame(1, OwnershipAssignment::where('target_type', OwnershipAssignment::TARGET_PARTNER_APPLICATION)->where('target_id', $guest->id)->count());
    }

    public function test_guest_partner_and_touch_without_code_do_not_assign_partner_application(): void
    {
        $partner = Partner::factory()->create();
        $visitor = 'f4a3c55e-9107-4cd0-92dc-e44d73d087b4';
        AttributionTouch::create(['anonymous_id' => $visitor, 'referral_code' => 'PARTNER', 'acquisition_partner_id' => $partner->id, 'occurred_at' => now()]);
        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/partner', $this->partnerApplicationData())->assertRedirect();
        $this->assertNull(app(OwnershipAssignmentService::class)->current(PartnerApplication::sole()));

        $owner = $this->owner();
        $anotherVisitor = 'f4a3c55e-9107-4cd0-92dc-e44d73d087b5';
        AttributionTouch::create(['anonymous_id' => $anotherVisitor, 'referrer_user_id' => $owner->id, 'occurred_at' => now()]);
        $this->withUnencryptedCookie('jakawi_visitor_id', $anotherVisitor)->post('/ciudades/la-paz/partner', ['business_name' => 'Panadería Sur', 'contact_name' => 'Sur', 'contact_email' => 'sur@example.test'])->assertRedirect();
        $this->assertNull(app(OwnershipAssignmentService::class)->current(PartnerApplication::where('business_name', 'Panadería Sur')->sole()));
    }

    public function test_manual_endpoints_validate_eligibility_target_self_assignment_and_keep_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = $this->owner();
        $second = $this->owner(ProgramEnrollment::TYPE_PROMOTER);
        $ineligible = User::factory()->create(['referral_code' => 'CODEONLY', 'referral_code_normalized' => 'CODEONLY']);
        $applicant = User::factory()->create();
        $target = "USER/{$applicant->id}";

        $this->actingAs($admin)->post('/admin/ownership/'.$target, ['owner_user_id' => $ineligible->id, 'reason' => 'Prueba'])->assertSessionHasErrors('owner_user_id');
        $this->post('/admin/ownership/'.$target, ['owner_user_id' => $applicant->id, 'reason' => 'Prueba'])->assertSessionHasErrors('owner_user_id');
        $this->post('/admin/ownership/'.$target, ['owner_user_id' => $first->id, 'reason' => 'Asignación'])->assertRedirect();
        $this->post('/admin/ownership/'.$target, ['owner_user_id' => $first->id, 'reason' => 'Repetido'])->assertRedirect();
        $this->assertDatabaseCount('ownership_assignments', 1);
        $this->post('/admin/ownership/'.$target, ['owner_user_id' => $second->id, 'reason' => 'Cambio'])->assertRedirect();
        $this->assertDatabaseHas('ownership_assignments', ['target_type' => 'USER', 'target_id' => $applicant->id, 'owner_user_id' => $first->id]);
        $this->assertNotNull(OwnershipAssignment::where('owner_user_id', $first->id)->sole()->ended_at);
        $this->assertSame($second->id, app(OwnershipAssignmentService::class)->current($applicant)->owner_user_id);
        ReferralRelationship::create(['referrer_user_id' => $first->id, 'referred_user_id' => $applicant->id,
            'referral_code' => 'FIRST', 'attributed_at' => now(), 'expires_at' => now()->addDays(30), 'status' => 'active']);
        app(OwnershipAssignmentService::class)->autoAssignUserFromReferral($applicant);
        $this->assertSame($second->id, app(OwnershipAssignmentService::class)->current($applicant)->owner_user_id);
        $this->delete('/admin/ownership/'.$target, ['reason' => 'Sin responsable'])->assertRedirect();
        $this->assertNull(app(OwnershipAssignmentService::class)->current($applicant));
        $this->assertDatabaseCount('ownership_assignments', 2);
        $this->assertSame(3, AuditLog::whereIn('action', ['ownership_assigned', 'ownership_reassigned', 'ownership_unassigned'])->count());
    }

    public function test_manual_application_assignment_rejects_applicant_and_non_admin_has_no_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = $this->owner();
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post('/afiliados/solicitudes', [])->assertRedirect();
        $application = ProgramApplication::sole();
        $path = "/admin/ownership/PROGRAM_APPLICATION/{$application->id}";
        $this->post($path, ['owner_user_id' => $owner->id, 'reason' => 'Intento'])->assertForbidden();
        $this->delete($path, ['reason' => 'Intento'])->assertForbidden();
        $this->get('/admin/solicitudes-programas/'.$application->id)->assertForbidden();
        $this->actingAs($admin)->post($path, ['owner_user_id' => $applicant->id, 'reason' => 'No permitido'])->assertSessionHasErrors('owner_user_id');
        $this->post('/admin/ownership/CAMPAIGN/1', ['owner_user_id' => $owner->id, 'reason' => 'No permitido'])->assertNotFound();
        $this->post($path, ['owner_user_id' => $owner->id, 'reason' => 'Manual'])->assertRedirect();
        $this->get('/admin/solicitudes-programas/'.$application->id)
            ->assertInertia(fn (Assert $page) => $page->where('ownership.owner_user_id', $owner->id)->has('ownerCandidates'));
        $this->actingAs($owner)->get('/admin/solicitudes-programas/'.$application->id)->assertForbidden();
        $this->get('/admin/solicitudes-partner')->assertForbidden();
        $this->get('/admin/attribution')->assertForbidden();

        $partnerApplication = PartnerApplication::create(['city_id' => City::where('slug', 'la-paz')->sole()->id, 'user_id' => $applicant->id, 'business_name' => 'X', 'business_name_normalized' => 'x', 'contact_name' => 'A', 'status' => 'SUBMITTED']);
        $this->actingAs($admin)->post("/admin/ownership/PARTNER_APPLICATION/{$partnerApplication->id}", ['owner_user_id' => $applicant->id, 'reason' => 'No permitido'])->assertSessionHasErrors('owner_user_id');
        $this->post("/admin/ownership/PARTNER_APPLICATION/{$partnerApplication->id}", ['owner_user_id' => $owner->id, 'reason' => 'Manual'])->assertRedirect();
        $this->get('/admin/solicitudes-partner/'.$partnerApplication->id)
            ->assertInertia(fn (Assert $page) => $page->where('ownership.owner_user_id', $owner->id)->has('ownerCandidates'));
        $this->actingAs($owner)->get('/admin/solicitudes-partner/'.$partnerApplication->id)->assertForbidden();
    }

    public function test_reassignment_leaves_referral_and_existing_economic_records_unchanged(): void
    {
        $promoter = $this->owner(ProgramEnrollment::TYPE_PROMOTER);
        $newOwner = $this->owner(ProgramEnrollment::TYPE_CREATOR);
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        ReferralRelationship::create(['referrer_user_id' => $promoter->id, 'referred_user_id' => $customer->id, 'referral_code' => 'PROMO', 'attributed_at' => now(), 'expires_at' => now()->addDays(30), 'status' => 'active']);
        RewardRule::create(['name' => 'Comisión promotor', 'participant_type' => 'PROMOTER', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 5, 'priority' => 0, 'status' => 'active']);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash($customer, $promoter, $promoter, 'REC-1', '6a415fb1-d4b0-4dfa-a86f-2d0f9c07a6d0');
        $reward = RewardTransaction::sole();
        $reward->update(['status' => RewardTransaction::STATUS_PAID]);
        $payout = RewardPayout::create(['reference' => 'PO-OWNERSHIP', 'beneficiary_user_id' => $promoter->id,
            'beneficiary_type' => 'USER', 'beneficiary_id' => $promoter->id, 'requested_by_user_id' => $promoter->id,
            'status' => RewardPayout::STATUS_PAID, 'requested_at' => now(), 'requested_amount' => $reward->amount,
            'currency' => 'BOB', 'paid_at' => now(), 'paid_by_user_id' => $admin->id, 'payment_reference' => 'BANK-1']);
        $payout->rewards()->attach($reward->id);
        $beforeRelationship = ReferralRelationship::where('referred_user_id', $customer->id)->sole()->toArray();
        $beforeReward = RewardTransaction::sole()->toArray();
        $beforeSnapshot = $sale->conversion->attribution_snapshot;
        $beforePurchase = MembershipPurchase::sole()->toArray();
        $beforePayout = $payout->fresh()->toArray();

        $this->actingAs($admin)->post("/admin/ownership/USER/{$customer->id}", ['owner_user_id' => $promoter->id, 'reason' => 'Inicial'])->assertRedirect();
        $this->post("/admin/ownership/USER/{$customer->id}", ['owner_user_id' => $newOwner->id, 'reason' => 'Cambio'])->assertRedirect();

        $this->assertSame($beforeRelationship, ReferralRelationship::where('referred_user_id', $customer->id)->sole()->toArray());
        $this->assertSame($beforeReward, RewardTransaction::sole()->toArray());
        $this->assertSame($beforeSnapshot, $sale->conversion->fresh()->attribution_snapshot);
        $this->assertSame($beforePurchase, MembershipPurchase::sole()->toArray());
        $this->assertSame($beforePayout, $payout->fresh()->toArray());
    }
}
