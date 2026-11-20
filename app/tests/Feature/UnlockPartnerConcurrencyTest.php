<?php

namespace Tests\Feature;

use App\Models\{AuditLog, JpHold, Partner, RewardTransaction, Unlock, UnlockParticipation, User};
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\EvidenceDatabaseTruncation;
use Tests\TestCase;

class UnlockPartnerConcurrencyTest extends TestCase
{
    use EvidenceDatabaseTruncation;
    protected array $exceptTables = ['cities'];

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    private function race(bool $sameParticipation): void
    {
        config(['jakawi.prelaunch_mode' => false, 'jakawi.analytics.enabled' => false]);
        $partner = Partner::factory()->published()->create(); $actor = User::factory()->create(); $actor->partners()->attach($partner, ['role' => 'owner']);
        $unlock = Unlock::create(['slug' => 'race-'.uniqid(), 'title' => 'Cumplimiento concurrente', 'partner_id' => $partner->id, 'status' => Unlock::UNLOCKED, 'minimum_commitments' => 1, 'jp_deposit' => 3, 'jp_completion_bonus' => 5]);
        $participations = [];
        foreach (range(1, $sameParticipation ? 1 : 2) as $i) {
            $p = UnlockParticipation::create(['unlock_id' => $unlock->id, 'user_id' => User::factory()->create()->id, 'status' => UnlockParticipation::CONFIRMED, 'confirmed_at' => now()]);
            JpHold::create(['user_id' => $p->user_id, 'unlock_participation_id' => $p->id, 'amount' => 3, 'status' => JpHold::HELD, 'held_at' => now()]); $participations[] = $p;
        }
        $code = <<<'PHP'
require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.host')!=='db-test' || config('database.connections.pgsql.database')!=='jakawi_test') throw new RuntimeException('Isolated race only');
config(['jakawi.prelaunch_mode'=>false,'jakawi.analytics.enabled'=>false]);
Illuminate\Support\Facades\DB::select("select set_config('application_name','v3b-fulfillment-race',false)");
$p=app(App\Services\UnlockParticipationService::class)->fulfill(App\Models\UnlockParticipation::findOrFail($argv[1]),App\Models\User::findOrFail($argv[2]),'partner_manual','QA concurrente'); echo $p->status;
PHP;
        DB::beginTransaction(); Unlock::lockForUpdate()->findOrFail($unlock->id); $processes = [];
        try {
            foreach ([$participations[0], $participations[$sameParticipation ? 0 : 1]] as $p) {
                $process = new Process(['php', '-r', $code, (string) $p->id, (string) $actor->id], base_path()); $process->setTimeout(40); $process->start(); $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                $waiting = (int) DB::selectOne("select count(*) as n from pg_stat_activity where application_name='v3b-fulfillment-race' and wait_event_type='Lock'")->n;
                if ($waiting === 2) break; usleep(50000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Independent processes must contend on the same Unlock lock');
        } finally { DB::commit(); }
        foreach ($processes as $process) { $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getErrorOutput()); $this->assertSame(UnlockParticipation::FULFILLED, trim($process->getOutput())); }
        $expected = $sameParticipation ? 1 : 2;
        $this->assertSame($expected, RewardTransaction::where('source', 'unlock_fulfillment')->count());
        $this->assertSame($expected, AuditLog::where('action', 'unlock_fulfilled')->count());
        $this->assertSame($expected, JpHold::where('status', JpHold::RELEASED)->count());
        foreach ($participations as $p) { $this->assertSame($actor->id, $p->fresh()->fulfilled_by_user_id); $this->assertSame('partner_manual', $p->fresh()->fulfillment_source); }
    }

    public function test_concurrent_retries_fulfill_once_release_one_hold_and_award_one_configured_bonus(): void { $this->race(true); }
    public function test_two_participations_of_same_unlock_fulfill_without_lock_order_deadlock(): void { $this->race(false); }
}
