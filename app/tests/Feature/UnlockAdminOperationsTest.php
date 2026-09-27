<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\JpHold;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\User;
use App\Services\UnlockParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UnlockAdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_denied_all_operations(): void
    {
        $unlock = $this->unlock(); $participation = $this->participation($unlock); $user = User::factory()->create();
        foreach (["/admin/desbloqueos/{$unlock->slug}/cerrar-compromisos", "/admin/desbloqueos/{$unlock->slug}/participaciones/{$participation->id}/retirar", "/admin/desbloqueos/{$unlock->slug}/participaciones/{$participation->id}/liberar-jp", "/admin/desbloqueos/{$unlock->slug}/participaciones/{$participation->id}/corregir"] as $url) $this->actingAs($user)->post($url, ['reason' => 'No autorizado', 'correction' => 'confirm_to_cancelled_on_time'])->assertForbidden();
    }

    public function test_close_early_requires_reason_and_blocks_new_commitments_without_reversing_history(): void
    {
        $admin = $this->admin(); $unlock = $this->unlock(['minimum_commitments' => 2]); $existing = $this->participation($unlock);
        $this->actingAs($admin)->post("/admin/desbloqueos/{$unlock->slug}/cerrar-compromisos")->assertRedirect(); $this->assertTrue($unlock->fresh()->commitment_deadline->isFuture());
        $this->actingAs($admin)->post("/admin/desbloqueos/{$unlock->slug}/cerrar-compromisos", ['reason' => 'Operación cerrada'])->assertSessionHasNoErrors();
        $this->assertSame(UnlockParticipation::COMMITTED, $existing->fresh()->status); $this->assertTrue($unlock->fresh()->commitment_deadline->isPast() || $unlock->fresh()->commitment_deadline->isNow());
        $this->expectException(ValidationException::class); app(UnlockParticipationService::class)->commit($unlock->fresh(), User::factory()->create());
    }

    public function test_remove_requires_reason_releases_hold_without_forfeit_or_bonus_and_is_safe(): void
    {
        $admin = $this->admin(); $unlock = $this->unlock(['jp_deposit' => 5, 'jp_completion_bonus' => 9]); $p = $this->participation($unlock); $hold = $this->hold($p, 5);
        $this->actingAs($admin)->post("/admin/desbloqueos/{$unlock->slug}/participaciones/{$p->id}/retirar")->assertRedirect(); $this->assertSame(UnlockParticipation::COMMITTED, $p->fresh()->status);
        $this->actingAs($admin)->post("/admin/desbloqueos/{$unlock->slug}/participaciones/{$p->id}/retirar", ['reason' => 'Duplicado'])->assertSessionHasNoErrors();
        $this->assertSame(UnlockParticipation::REMOVED, $p->fresh()->status); $this->assertSame(JpHold::RELEASED, $hold->fresh()->status); $this->assertDatabaseCount('reward_transactions', 0);
        $this->actingAs($admin)->post("/admin/desbloqueos/{$unlock->slug}/participaciones/{$p->id}/retirar", ['reason' => 'Reintento'])->assertSessionHasNoErrors(); $this->assertDatabaseCount('jp_holds', 1);
    }

    public function test_exceptional_release_requires_reason_releases_once_and_removes_active_guarantee(): void
    {
        $admin = $this->admin(); $unlock = $this->unlock(['jp_deposit' => 7]); $p = $this->participation($unlock); $hold = $this->hold($p, 7);
        $this->actingAs($admin)->post("/admin/desbloqueos/{$unlock->slug}/participaciones/{$p->id}/liberar-jp")->assertRedirect(); $this->assertSame(JpHold::HELD, $hold->fresh()->status);
        $this->actingAs($admin)->post("/admin/desbloqueos/{$unlock->slug}/participaciones/{$p->id}/liberar-jp", ['reason' => 'Excepción verificada'])->assertSessionHasNoErrors();
        $this->assertSame(JpHold::RELEASED, $hold->fresh()->status); $this->assertSame(UnlockParticipation::REMOVED, $p->fresh()->status);
        $this->actingAs($admin)->post("/admin/desbloqueos/{$unlock->slug}/participaciones/{$p->id}/liberar-jp", ['reason' => 'Otra'])->assertSessionHasErrors('jp');
    }

    public function test_state_correction_requires_reason_allows_only_confirmed_to_cancelled_and_releases_hold(): void
    {
        $admin = $this->admin(); $unlock = $this->unlock(['status' => Unlock::UNLOCKED]); $p = $this->participation($unlock, UnlockParticipation::CONFIRMED); $hold = $this->hold($p, 4);
        $url = "/admin/desbloqueos/{$unlock->slug}/participaciones/{$p->id}/corregir";
        $this->actingAs($admin)->post($url, ['correction' => 'confirm_to_cancelled_on_time'])->assertRedirect(); $this->assertSame(UnlockParticipation::CONFIRMED, $p->fresh()->status);
        $this->actingAs($admin)->post($url, ['correction' => 'confirm_to_cancelled_on_time', 'reason' => 'Confirmación errónea'])->assertSessionHasNoErrors();
        $this->assertSame(UnlockParticipation::CANCELLED_ON_TIME, $p->fresh()->status); $this->assertSame(JpHold::RELEASED, $hold->fresh()->status);
        $p->update(['status' => UnlockParticipation::NO_SHOW]); $this->actingAs($admin)->post($url, ['correction' => 'confirm_to_cancelled_on_time', 'reason' => 'No válido'])->assertRedirect(); $this->assertSame(UnlockParticipation::NO_SHOW, $p->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'action' => 'unlock_participation_corrected']); $this->assertGreaterThanOrEqual(2, AuditLog::count());
    }

    private function admin(): User { $user = User::factory()->create(); $user->forceFill(['is_admin' => true])->save(); return $user; }
    private function unlock(array $overrides = []): Unlock { return Unlock::create(array_merge(['title' => 'U'.uniqid(), 'slug' => 'u-'.uniqid(), 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 10, 'free_user_eligible' => true, 'member_eligible' => true, 'jp_deposit' => 0, 'jp_completion_bonus' => 0, 'status' => Unlock::ACTIVE, 'commitment_deadline' => now()->addDay()], $overrides)); }
    private function participation(Unlock $unlock, string $status = UnlockParticipation::COMMITTED): UnlockParticipation { return UnlockParticipation::create(['unlock_id' => $unlock->id, 'user_id' => User::factory()->create()->id, 'status' => $status, 'committed_at' => now()]); }
    private function hold(UnlockParticipation $p, int $amount): JpHold { return JpHold::create(['user_id' => $p->user_id, 'unlock_participation_id' => $p->id, 'amount' => $amount, 'status' => JpHold::HELD, 'held_at' => now()]); }
}
