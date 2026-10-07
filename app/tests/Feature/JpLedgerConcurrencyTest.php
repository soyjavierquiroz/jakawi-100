<?php

namespace Tests\Feature;

use App\Models\JpHold;
use App\Models\RewardTransaction;
use App\Models\Unlock;
use App\Models\User;
use App\Services\JpBalanceService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class JpLedgerConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['cities'];

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    public function test_two_concurrent_debits_of_eighty_allow_only_one(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->credit($user);
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    app(App\Services\JpLedgerService::class)->adminAdjustment(App\Models\User::findOrFail($argv[1]), App\Models\User::findOrFail($argv[2]), 'ledger_debit', 80, 'Concurrent debit', $argv[3]);
    echo 'ok';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'blocked';
}
PHP;
        $results = $this->race($user, [[$code, $admin->id, $user->id, (string) Str::uuid()], [$code, $admin->id, $user->id, (string) Str::uuid()]]);
        $this->assertSame(['blocked', 'ok'], $results);
        $this->assertSame(20, app(JpBalanceService::class)->for($user)['available_balance']);
        $this->assertSame(2, RewardTransaction::count());
    }

    public function test_two_concurrent_holds_cannot_overreserve(): void
    {
        $user = User::factory()->create();
        $this->credit($user);
        $first = $this->unlock();
        $second = $this->unlock();
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config()->set('unlocks.jp_commitments_enabled', true);
try {
    app(App\Services\UnlockParticipationService::class)->commit(App\Models\Unlock::findOrFail($argv[1]), App\Models\User::findOrFail($argv[2]));
    echo 'ok';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'blocked';
}
PHP;
        $results = $this->race($user, [[$code, $first->id, $user->id], [$code, $second->id, $user->id]]);
        $this->assertSame(['blocked', 'ok'], $results);
        $this->assertSame(1, JpHold::where('status', JpHold::HELD)->count());
        $this->assertSame(20, app(JpBalanceService::class)->for($user)['available_balance']);
    }

    private function race(User $user, array $commands): array
    {
        DB::beginTransaction();
        User::query()->lockForUpdate()->findOrFail($user->id);
        try {
            $processes = [];
            foreach ($commands as $command) {
                $code = $command[0];
                $arguments = array_slice($command, 1);
                $process = new Process(['php', '-r', $code, ...array_map(strval(...), $arguments)], base_path());
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            usleep(300000);
        } finally {
            DB::commit();
        }

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = trim($process->getOutput());
        }
        sort($results);
        return $results;
    }

    private function credit(User $user): void
    {
        RewardTransaction::create(['beneficiary_user_id' => $user->id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $user->id, 'reward_type' => 'JP', 'currency' => 'JP', 'amount' => 100, 'status' => RewardTransaction::STATUS_AVAILABLE, 'available_at' => now()]);
    }

    private function unlock(): Unlock
    {
        return Unlock::create(['title' => 'Concurrent JP', 'slug' => (string) Str::uuid(), 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'free_user_eligible' => true, 'member_eligible' => true, 'jp_deposit' => 80, 'jp_completion_bonus' => 0, 'status' => Unlock::ACTIVE]);
    }
}
