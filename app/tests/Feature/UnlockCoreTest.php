<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\UnlockStatusHistory;
use App\Models\User;
use App\Services\UnlockParticipationService;
use App\Services\UnlockStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UnlockCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_and_approves_an_unlock_with_linked_results(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $partner = Partner::factory()->create();
        $benefit = Benefit::factory()->for($partner)->create();
        $experience = Experience::factory()->create();
        $this->actingAs($admin)->post('/admin/desbloqueos', $this->payload(['partner_id' => $partner->id, 'linked_benefit_id' => $benefit->id, 'linked_experience_id' => $experience->id]))->assertRedirect();
        $unlock = Unlock::sole();
        $this->assertSame($benefit->id, $unlock->linked_benefit_id);
        $this->assertSame($experience->id, $unlock->linked_experience_id);
        $this->actingAs($admin)->post('/admin/desbloqueos/'.$unlock->slug.'/estado', ['status' => Unlock::APPROVED])->assertRedirect();
        $this->assertSame(Unlock::APPROVED, $unlock->fresh()->status);
    }

    public function test_partner_owner_and_manager_can_propose_but_staff_cannot_and_cannot_approve(): void
    {
        $partner = Partner::factory()->create();
        foreach (['owner', 'manager'] as $role) {
            $user = $this->partnerUser($partner, $role);
            $this->actingAs($user)->post('/partner/'.$partner->slug.'/desbloqueos', $this->partnerPayload(['title' => $role]))->assertRedirect();
        }
        $staff = $this->partnerUser($partner, 'staff');
        $this->actingAs($staff)->post('/partner/'.$partner->slug.'/desbloqueos', $this->partnerPayload())->assertForbidden();
        $proposal = Unlock::where('title', 'owner')->sole();
        $owner = $this->partnerUser($partner, 'owner');
        $this->actingAs($owner)->post('/admin/desbloqueos/'.$proposal->slug.'/estado', ['status' => Unlock::APPROVED])->assertForbidden();
        $this->actingAs($owner)->post('/partner/'.$partner->slug.'/desbloqueos/'.$proposal->slug.'/enviar')->assertRedirect();
        $this->assertSame(Unlock::PENDING_REVIEW, $proposal->fresh()->status);
    }

    public function test_public_view_is_safe_for_guests_and_secret_data_reveals_only_after_goal(): void
    {
        $partner = Partner::factory()->create(['name' => 'Secret Partner']);
        $location = Location::factory()->for($partner)->create(['name' => 'Secret Location']);
        $unlock = $this->unlock(['secret_mode' => true, 'hide_partner_until_unlock' => true, 'hide_location_until_unlock' => true, 'hide_exact_offer_until_unlock' => true, 'free_user_offer' => 'SECRET OFFER', 'description' => 'SECRET OFFER', 'partner_id' => $partner->id]);
        $unlock->locations()->attach($location);
        $this->get('/d/'.$unlock->slug)->assertOk()->assertDontSee('Secret Partner')->assertDontSee('Secret Location')->assertDontSee('SECRET OFFER');
        app(UnlockParticipationService::class)->commit($unlock, User::factory()->create());
        $this->get('/d/'.$unlock->slug)->assertOk()->assertSee('Secret Partner')->assertSee('Secret Location')->assertSee('SECRET OFFER')->assertDontSee(User::first()->email);
    }

    public function test_interest_commitment_eligibility_and_idempotency(): void
    {
        $unlock = $this->unlock(['minimum_commitments' => 5]);
        $user = User::factory()->create();
        $service = app(UnlockParticipationService::class);
        $service->interest($unlock, $user);
        $this->assertSame(0, $unlock->committedCount());
        $service->commit($unlock, $user);
        $service->commit($unlock, $user);
        $this->assertSame(1, $unlock->committedCount());
        $this->assertSame(1, UnlockParticipation::where('unlock_id', $unlock->id)->where('user_id', $user->id)->count());
        $membersOnly = $this->unlock(['free_user_eligible' => false, 'member_eligible' => true]);
        try { $service->commit($membersOnly, User::factory()->create()); $this->fail('Ineligible user committed.'); } catch (ValidationException) {}
        $member = User::factory()->create();
        Membership::create(['user_id' => $member->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'amount_paid' => 100]);
        $service->commit($membersOnly, $member);
        $this->assertSame(1, $membersOnly->committedCount());
    }

    public function test_capacity_goal_cancellation_and_jp_gate_have_correct_semantics(): void
    {
        $service = app(UnlockParticipationService::class);
        $unlock = $this->unlock(['minimum_commitments' => 2, 'maximum_capacity' => 3]);
        $a = User::factory()->create(); $b = User::factory()->create(); $c = User::factory()->create();
        $service->commit($unlock, $a); $service->commit($unlock, $b);
        $this->assertSame(Unlock::GOAL_REACHED, $unlock->fresh()->status);
        $this->assertSame(1, UnlockStatusHistory::where('unlock_id', $unlock->id)->where('to_status', Unlock::GOAL_REACHED)->count());
        $service->commit($unlock, $c);
        try { $service->commit($unlock, User::factory()->create()); $this->fail('Capacity exceeded.'); } catch (ValidationException) {}
        $service->cancel($unlock, $a);
        $this->assertSame(2, $unlock->committedCount());
        $this->assertSame(Unlock::GOAL_REACHED, $unlock->fresh()->status);
        config()->set('unlocks.jp_commitments_enabled', false);
        $jp = $this->unlock(['jp_deposit' => 1]);
        try { $service->commit($jp, User::factory()->create()); $this->fail('JP commitment accepted.'); } catch (ValidationException) {}
        $this->assertSame(0, $jp->committedCount());
        config()->set('unlocks.jp_commitments_enabled', true);
    }

    public function test_deadline_failure_admin_cancellation_audit_and_partner_material_lock(): void
    {
        $expired = $this->unlock(['commitment_deadline' => now()->subMinute()]);
        app(UnlockStatusService::class)->expireMissedGoals();
        $this->assertSame(Unlock::GOAL_NOT_REACHED, $expired->fresh()->status);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/desbloqueos/'.$expired->slug.'/estado', ['status' => Unlock::CANCELLED, 'reason' => 'Operational'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'action' => 'unlock_status_changed', 'subject_id' => $expired->id]);
        $partner = Partner::factory()->create(); $owner = $this->partnerUser($partner, 'owner'); $unlock = $this->unlock(['partner_id' => $partner->id]);
        app(UnlockParticipationService::class)->commit($unlock, User::factory()->create());
        $this->actingAs($owner)->put('/partner/'.$partner->slug.'/desbloqueos/'.$unlock->slug, $this->partnerPayload(['minimum_commitments' => 99]))->assertStatus(422);
    }

    private function unlock(array $overrides = []): Unlock
    {
        return Unlock::create(array_merge(['title' => 'Unlock '.uniqid(), 'slug' => 'unlock-'.uniqid(), 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 1, 'free_user_eligible' => true, 'member_eligible' => true, 'jp_deposit' => 0, 'jp_completion_bonus' => 0, 'status' => Unlock::ACTIVE], $overrides));
    }

    private function partnerUser(Partner $partner, string $role): User { $user = User::factory()->create(); $user->partners()->attach($partner, ['role' => $role]); return $user; }
    private function payload(array $overrides = []): array { return array_merge(['title' => 'Admin unlock', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 10, 'free_user_eligible' => true, 'member_eligible' => true, 'featured' => false, 'secret_mode' => false, 'hide_partner_until_unlock' => false, 'hide_exact_offer_until_unlock' => false, 'hide_location_until_unlock' => false], $overrides); }
    private function partnerPayload(array $overrides = []): array { return array_merge(['title' => 'Proposal', 'type' => 'BENEFIT', 'minimum_commitments' => 10], $overrides); }
}
