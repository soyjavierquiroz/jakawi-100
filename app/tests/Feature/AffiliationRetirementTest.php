<?php

namespace Tests\Feature;

use App\Models\Conversion;
use App\Models\Partner;
use App\Models\ProgramEnrollment;
use App\Models\RewardPayout;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\RewardPayoutService;
use App\Services\RewardResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AffiliationRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_panels_redirect_to_club_and_com_admin_stays_protected(): void
    {
        foreach (['/affiliate', '/creator', '/afiliados', '/creadores', '/promotores', '/afiliados/aplicar'] as $path) {
            $this->get($path)->assertRedirect('https://jakawi.club/afiliados');
        }
        $this->get('/admin/affiliates')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/affiliates')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['/admin/affiliates', '/admin/affiliates/create', '/admin/creators/1', '/admin/solicitudes-programas'] as $path) {
            $this->get($path)->assertRedirect('https://jakawi.club/afiliados');
        }
        $this->get('/admin')->assertOk();
    }

    public function test_retired_mutations_cannot_create_rules_enrollments_or_withdrawals(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['/affiliate/payouts', '/creator/payouts', '/admin/affiliates', '/admin/creators', '/afiliados/solicitudes', '/promotores/solicitudes', '/admin/solicitudes-programas/1/estado'] as $path) {
            $this->assertContains($this->post($path, ['status' => 'active'])->status(), [404, 405]);
        }
        $this->put('/admin/promoters/1/commission', ['mode' => 'custom', 'value' => 20])->assertNotFound();
        $this->post('/admin/promoters/1/referral-code')->assertNotFound();
        $this->assertDatabaseCount('program_enrollments', 0);
        $this->assertDatabaseCount('reward_payouts', 0);
        $this->assertDatabaseCount('reward_rules', 0);
        $this->assertFalse(Schema::hasTable('program_applications'));
    }

    public function test_direct_resolver_calls_cannot_reactivate_any_retired_affiliate_rule(): void
    {
        $user = User::factory()->create();
        $conversion = $this->conversion($user);
        foreach (['AFFILIATE', 'CREATOR', 'PROMOTER'] as $type) {
            RewardRule::create(['name' => $type, 'participant_type' => $type, 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 50, 'currency' => 'BOB', 'status' => 'active']);
            $this->assertNull(app(RewardResolver::class)->createFor($conversion, $user, $type));
            $this->assertNull(app(RewardResolver::class)->ruleFor($user, 'USER', $type, 'membership_purchased', null));
        }
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_old_rules_and_history_are_preserved_but_absent_from_active_management(): void
    {
        [$rule, $reward, $payout] = $this->history();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/reward-rules')->assertInertia(fn (Assert $page) => $page->has('rules', 0));
        $this->get('/admin/payouts')->assertInertia(fn (Assert $page) => $page->has('payouts.data', 0)->missing('affiliates')->missing('minimumPayout'));
        $this->get('/admin/payouts/'.$payout->id)->assertNotFound();
        $this->post('/admin/payouts/'.$payout->id.'/paid', ['payment_reference' => 'FORBIDDEN'])->assertNotFound();
        $this->post('/admin/payouts/'.$payout->id.'/reject', ['reason' => 'FORBIDDEN'])->assertNotFound();
        $this->post('/admin/adjustments/rewards/'.$reward->id.'/status', ['status' => 'available', 'reason' => 'FORBIDDEN', 'confirm' => true])->assertNotFound();
        $this->put('/admin/reward-rules/'.$rule->id, [])->assertNotFound();
        $this->get('/admin/exports/affiliates')->assertNotFound();
        $this->assertStringNotContainsString('LEGACY-PO', $this->get('/admin/exports/payouts')->streamedContent());
        $this->assertStringNotContainsString((string) $reward->id.',USER', $this->get('/admin/exports/rewards')->streamedContent());
        $this->assertSame('pending', $reward->fresh()->status);
        $this->assertSame('requested', $payout->fresh()->status);
        $this->assertSame('50.00', $reward->fresh()->amount);
        $this->assertSame(1, $payout->rewards()->count());
    }

    public function test_new_affiliate_pricing_and_user_cash_adjustments_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $base = ['name' => 'Retired', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 10, 'priority' => 0, 'status' => 'active'];
        foreach (['AFFILIATE', 'CREATOR', 'PROMOTER'] as $type) {
            $this->postJson('/admin/reward-rules', $base + ['participant_type' => $type])->assertUnprocessable();
        }
        $this->postJson('/admin/reward-rules', $base + ['participant_type' => 'MEMBER', 'beneficiary_type' => 'USER', 'beneficiary_id' => $user->id])->assertUnprocessable();
        $this->postJson('/admin/adjustments/ledger', ['beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'unit' => 'CASH', 'amount' => 10, 'reason' => 'No affiliate ledger', 'confirm' => true])->assertUnprocessable();
        $this->assertDatabaseCount('reward_rules', 0);
        $this->assertDatabaseCount('operational_adjustments', 0);
    }

    public function test_operational_jp_and_partner_rules_remain_available(): void
    {
        $partner = Partner::factory()->published()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $base = ['name' => 'Operational', 'event' => 'membership_purchased', 'calculation_type' => 'FIXED', 'value' => 10, 'priority' => 0, 'status' => 'inactive'];
        $this->post('/admin/reward-rules', $base + ['participant_type' => 'PARTNER', 'beneficiary_type' => 'PARTNER', 'beneficiary_id' => $partner->id, 'reward_type' => 'CASH'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/admin/reward-rules', $base + ['participant_type' => 'MEMBER', 'reward_type' => 'JP'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reward_rules', 2);
        $this->get('/admin/reward-rules')->assertInertia(fn (Assert $page) => $page->has('rules', 2));
    }

    public function test_schema_cleanup_preserves_unexpected_applications_and_financial_records(): void
    {
        [$rule, $reward, $payout] = $this->history();
        $create = require database_path('migrations/2026_10_05_000001_create_program_applications_table.php');
        $create->up();
        DB::table('program_applications')->insert(['user_id' => $reward->beneficiary_user_id, 'program_type' => 'AFFILIATE', 'status' => 'SUBMITTED']);
        $cleanup = require database_path('migrations/2026_10_30_120000_retire_com_affiliate_applications.php');
        $cleanup->up();
        $this->assertTrue(Schema::hasTable('program_applications'));
        $this->assertDatabaseCount('program_applications', 1);
        $this->assertDatabaseHas('reward_transactions', ['id' => $reward->id, 'amount' => 50]);
        $this->assertDatabaseHas('reward_payouts', ['id' => $payout->id, 'status' => 'requested']);
        DB::table('program_applications')->delete();
        $cleanup->up();
        $this->assertFalse(Schema::hasTable('program_applications'));
        $this->assertDatabaseHas('reward_rules', ['id' => $rule->id]);
        $this->assertSame(1, $payout->rewards()->count());
    }

    public function test_admin_navigation_describes_only_operational_com_modules(): void
    {
        $nav = file_get_contents(resource_path('js/pages/admin/layout.tsx'));
        foreach (['/admin/affiliates', '/admin/creators', '/admin/solicitudes-programas'] as $path) {
            $this->assertStringNotContainsString($path, $nav);
        }
        foreach (['Operadores de ventas', 'Pagos a Partners', 'Reglas JP y Partners', 'Campañas operativas', 'Atribución comercial', 'AFILIADOS Y COMISIONES — CLUB', 'https://jakawi.club/afiliados'] as $text) {
            $this->assertStringContainsString($text, $nav);
        }
    }

    private function conversion(User $user): Conversion
    {
        return Conversion::create(['user_id' => $user->id, 'type' => 'membership_purchased', 'idempotency_key' => (string) Str::uuid(), 'gross_amount' => 100, 'eligible_amount' => 100, 'currency' => 'BOB', 'status' => 'confirmed', 'occurred_at' => now()]);
    }

    private function history(): array
    {
        $user = User::factory()->create();
        $conversion = $this->conversion($user);
        $rule = RewardRule::create(['name' => 'Legacy fixture', 'participant_type' => 'AFFILIATE', 'event' => 'membership_purchased', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'value' => 50, 'currency' => 'BOB', 'status' => 'active']);
        $reward = RewardTransaction::create(['beneficiary_user_id' => $user->id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'conversion_id' => $conversion->id, 'reward_rule_id' => $rule->id, 'reward_type' => 'CASH', 'amount' => 50, 'currency' => 'BOB', 'status' => 'pending']);
        $payout = RewardPayout::create(['reference' => 'LEGACY-PO', 'beneficiary_user_id' => $user->id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'status' => 'requested', 'requested_at' => now(), 'requested_amount' => 50, 'currency' => 'BOB']);
        $payout->rewards()->attach($reward->id);

        return [$rule, $reward, $payout];
    }
}
