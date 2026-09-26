<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MembershipPurchase;
use App\Models\ProgramEnrollment;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\MembershipPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPromoterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_enrolls_existing_member_and_prevents_duplicate_active_enrollment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $this->actingAs($admin)->post('/admin/promoters', ['user_id' => $member->id, 'status' => 'active'])->assertRedirect('/admin/promoters/'.$member->id);
        $this->actingAs($admin)->post('/admin/promoters', ['user_id' => $member->id, 'status' => 'active'])->assertRedirect();
        $this->assertSame(1, ProgramEnrollment::where('user_id', $member->id)->where('program_type', 'PROMOTER')->where('status', 'active')->count());
        $this->assertTrue($member->fresh()->hasActiveProgram('PROMOTER'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'program_enrollment_added']);
    }

    public function test_admin_creates_safe_promoter_with_code_and_non_admin_cannot_manage(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/promoters', ['name' => 'Nueva Promotora', 'email' => 'promoter@example.test', 'status' => 'active'])->assertRedirect();
        $promoter = User::where('email', 'promoter@example.test')->firstOrFail();
        $this->assertNotSame('password', $promoter->password); $this->assertNotNull($promoter->referral_code);
        $this->actingAs(User::factory()->create())->get('/admin/promoters')->assertForbidden();
        $this->actingAs($promoter)->put('/admin/promoters/'.$promoter->id.'/enrollment', ['status' => 'inactive'])->assertForbidden();
    }

    public function test_deactivation_and_expiry_remove_operational_access_without_deleting_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]); $promoter = $this->promoter(); $sale = $this->sale($promoter);
        $this->actingAs($admin)->put('/admin/promoters/'.$promoter->id.'/enrollment', ['status' => 'inactive'])->assertRedirect();
        $this->actingAs($promoter)->get('/promoter/sales')->assertForbidden();
        $this->assertDatabaseHas('membership_purchases', ['id' => $sale->id]);
        $enrollment = $promoter->programEnrollments()->first(); $enrollment->update(['status' => 'active', 'ends_at' => now()->subMinute()]);
        $this->actingAs($promoter)->get('/promoter/sales')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'promoter_deactivated']);
    }

    public function test_referral_code_is_generated_without_silent_overwrite_and_link_attributes_prospectively(): void
    {
        $admin = User::factory()->create(['is_admin' => true]); $promoter = $this->promoter();
        $this->actingAs($admin)->post('/admin/promoters/'.$promoter->id.'/referral-code')->assertRedirect(); $first = $promoter->fresh()->referral_code;
        $this->actingAs($admin)->post('/admin/promoters/'.$promoter->id.'/referral-code')->assertRedirect(); $this->assertSame($first, $promoter->fresh()->referral_code);
        $this->actingAs($admin)->post('/admin/promoters/'.$promoter->id.'/referral-code', ['regenerate' => true])->assertRedirect(); $this->assertNotSame($first, $promoter->fresh()->referral_code);
        $this->get('/r/'.$promoter->fresh()->referral_code)->assertRedirect('/');
        $this->assertDatabaseHas('audit_logs', ['action' => 'promoter_referral_code_changed']);
    }

    public function test_individual_commission_wins_over_program_rule_without_stacking_and_is_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]); $promoter = $this->promoter();
        RewardRule::create(['name' => 'Programa', 'participant_type' => 'PROMOTER', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => 'PERCENTAGE', 'value' => 10, 'currency' => 'BOB', 'priority' => 0, 'status' => 'active']);
        $this->actingAs($admin)->put('/admin/promoters/'.$promoter->id.'/commission', ['mode' => 'custom', 'calculation_type' => 'FIXED', 'value' => 25, 'currency' => 'BOB', 'status' => 'active'])->assertRedirect();
        $this->sale($promoter);
        $this->assertSame(1, RewardTransaction::count()); $this->assertSame('25.00', RewardTransaction::firstOrFail()->amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'individual_reward_rule_added']);
    }

    public function test_promoters_cannot_see_another_promoters_sales(): void
    {
        $one = $this->promoter(); $two = $this->promoter(); $sale = $this->sale($two);
        $this->actingAs($one)->get('/promoter/sales/'.$sale->id)->assertForbidden();
    }

    private function promoter(): User { $user = User::factory()->create(); ProgramEnrollment::create(['user_id' => $user->id, 'program_type' => 'PROMOTER', 'status' => 'active']); return $user; }
    private function sale(User $promoter): MembershipPurchase { return app(MembershipPurchaseService::class)->confirmManualCash(User::factory()->create(), $promoter, $promoter, 'REC-'.Str::random(8), (string) Str::uuid()); }
}
