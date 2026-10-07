<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ChallengeRewardGrant;
use App\Models\ChallengeSocialEntry;
use App\Models\User;
use App\Services\ChallengeService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChallengeFirstNConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['cities'];

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    public function test_two_qualified_users_racing_for_last_first_n_slot_get_one_grant(): void
    {
        $challenge = Challenge::create([
            'title' => 'Último cupo', 'slug' => 'ultimo-cupo-'.Str::random(8), 'status' => 'open',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => [], 'required_mentions' => [],
            'evidence_type' => 'SOCIAL_POST', 'qualification_type' => 'VALID_EVIDENCE',
            'selection_type' => 'FIRST_N', 'winner_limit' => 1, 'evaluation_mode' => 'CONTINUOUS',
            'review_mode' => 'AUTOMATIC', 'participation_eligibility' => 'ALL_USERS',
            'reward_eligibility' => 'ALL_USERS', 'reward_type' => 'MANUAL_PRIZE',
            'manual_prize_description' => 'Premio',
        ]);

        $participations = collect([User::factory()->create(), User::factory()->create()])->map(function (User $user) use ($challenge): ChallengeParticipation {
            $participation = ChallengeParticipation::create([
                'challenge_id' => $challenge->id, 'user_id' => $user->id,
                'qualification_status' => 'qualified', 'qualified_at' => now(),
            ]);
            $reference = (string) Str::ulid();
            $entry = ChallengeSocialEntry::create([
                'challenge_id' => $challenge->id, 'participation_id' => $participation->id,
                'social_url' => 'https://instagram.com/p/'.$reference,
                'normalized_url_hash' => hash('sha256', $reference),
                'external_reference' => 'test-'.$reference,
                'validation_status' => 'valid', 'inspection_status' => 'inspected', 'checked_at' => now(),
            ]);
            $participation->update(['qualified_entry_id' => $entry->id]);
            return $participation;
        });

        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config()->set('jakawi.analytics.enabled', false);
$participation = App\Models\ChallengeParticipation::findOrFail($argv[1]);
$grant = app(App\Services\ChallengeService::class)->selectAndMaybeGrant($participation);
echo $grant ? 'selected' : 'qualified_without_grant';
PHP;

        DB::beginTransaction();
        Challenge::query()->lockForUpdate()->findOrFail($challenge->id);
        try {
            $processes = [];
            foreach ($participations as $participation) {
                $process = new Process(['php', '-r', $code, (string) $participation->id], base_path());
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
        $this->assertSame(['qualified_without_grant', 'selected'], $results);

        $this->assertSame(1, ChallengeRewardGrant::count());
        $this->assertSame(1, ChallengeParticipation::where('challenge_id', $challenge->id)->where('selection_status', 'selected')->count());
        $loser = ChallengeParticipation::where('challenge_id', $challenge->id)->where('selection_status', 'pending')->sole();
        $this->assertSame('qualified', $loser->qualification_status);
        $this->assertNull($loser->grant);
        $this->assertNotNull(ChallengeRewardGrant::sole()->participation_id);

        foreach ($participations as $participation) app(ChallengeService::class)->selectAndMaybeGrant($participation);
        $this->assertSame(1, ChallengeRewardGrant::count());
        $this->assertSame('pending', $loser->fresh()->selection_status);
    }
}
