<?php

namespace Tests\Feature;

use App\Models\JpHold;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\UnlockStatusHistory;
use App\Models\User;
use App\Services\UnlockParticipationService;
use App\Services\UnlockStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnlockLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_scheduled_unlock_before_start_remains_scheduled(): void
    {
        Carbon::setTestNow('2026-09-29 13:59:59 UTC');
        $unlock = $this->scheduledUnlock(['starts_at' => now()->addSecond()]);

        $this->assertSame(0, app(UnlockStatusService::class)->activateDueScheduled());
        $this->assertSame(Unlock::SCHEDULED, $unlock->fresh()->status);
    }

    public function test_due_scheduled_unlock_activates_once_without_a_human_audit_actor(): void
    {
        Carbon::setTestNow('2026-09-29 14:00:00 UTC');
        $unlock = $this->scheduledUnlock(['starts_at' => now()]);
        $service = app(UnlockStatusService::class);

        $this->assertSame(1, $service->activateDueScheduled());
        $this->assertSame(Unlock::ACTIVE, $unlock->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseHas('unlock_status_history', [
            'unlock_id' => $unlock->id,
            'from_status' => Unlock::SCHEDULED,
            'to_status' => Unlock::ACTIVE,
            'actor_user_id' => null,
        ]);

        $this->assertSame(0, $service->activateDueScheduled());
        $this->assertSame(1, UnlockStatusHistory::where('unlock_id', $unlock->id)->where('to_status', Unlock::ACTIVE)->count());
    }

    public function test_stale_scheduled_unlock_is_not_activated_and_existing_expiry_marks_it_failed(): void
    {
        Carbon::setTestNow('2026-10-02 20:00:01 UTC');
        $unlock = $this->scheduledUnlock([
            'starts_at' => now()->subDay(),
            'commitment_deadline' => now()->subSecond(),
        ]);
        $service = app(UnlockStatusService::class);

        $this->assertSame(0, $service->activateDueScheduled());
        $this->assertSame(Unlock::SCHEDULED, $unlock->fresh()->status);
        $this->assertSame(1, $service->expireMissedGoals());
        $this->assertSame(Unlock::GOAL_NOT_REACHED, $unlock->fresh()->status);
    }

    public function test_cancelled_and_rejected_unlocks_never_activate(): void
    {
        Carbon::setTestNow('2026-09-29 14:00:00 UTC');
        $cancelled = $this->scheduledUnlock(['status' => Unlock::CANCELLED, 'starts_at' => now()->subSecond()]);
        $rejected = $this->scheduledUnlock(['status' => Unlock::REJECTED, 'starts_at' => now()->subSecond()]);

        $this->assertSame(0, app(UnlockStatusService::class)->activateDueScheduled());
        $this->assertSame(Unlock::CANCELLED, $cancelled->fresh()->status);
        $this->assertSame(Unlock::REJECTED, $rejected->fresh()->status);
    }

    public function test_existing_no_show_processing_still_works(): void
    {
        $unlock = $this->scheduledUnlock([
            'status' => Unlock::UNLOCKED,
            'fulfillment_ends_at' => now()->subMinute(),
        ]);
        $user = User::factory()->create();
        $participation = UnlockParticipation::create([
            'unlock_id' => $unlock->id,
            'user_id' => $user->id,
            'status' => UnlockParticipation::CONFIRMED,
            'confirmed_at' => now()->subHour(),
        ]);
        JpHold::create([
            'user_id' => $user->id,
            'unlock_participation_id' => $participation->id,
            'amount' => 5,
            'status' => JpHold::HELD,
            'held_at' => now(),
        ]);

        $result = app(UnlockParticipationService::class)->processDeadlines();

        $this->assertSame(1, $result['noShows']);
        $this->assertSame(UnlockParticipation::NO_SHOW, $participation->fresh()->status);
        $this->assertSame(JpHold::FORFEITED, JpHold::where('unlock_participation_id', $participation->id)->sole()->status);
    }

    private function scheduledUnlock(array $overrides = []): Unlock
    {
        return Unlock::create(array_merge([
            'title' => 'Unlock '.uniqid(),
            'slug' => 'unlock-'.uniqid(),
            'origin' => 'JAKAWI',
            'type' => 'EXPERIENCE',
            'minimum_commitments' => 1,
            'free_user_eligible' => true,
            'member_eligible' => true,
            'jp_deposit' => 0,
            'jp_completion_bonus' => 0,
            'status' => Unlock::SCHEDULED,
            'starts_at' => now()->subSecond(),
            'commitment_deadline' => now()->addDay(),
        ], $overrides));
    }
}
