<?php

namespace Tests\Feature;

use App\Models\{Experience, ExperienceReservation, ExperienceSession, Partner, User};
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\EvidenceDatabaseTruncation as DatabaseTruncation;
use Tests\TestCase;

class ExperienceCapacityConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    protected array $exceptTables = ['cities'];

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    private function fixture(): array
    {
        config(['jakawi.prelaunch_mode' => false]);
        $partner = Partner::factory()->published()->create();
        $actor = User::factory()->create(); $actor->partners()->attach($partner, ['role' => 'staff']);
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $experience->partners()->attach($partner, ['role' => 'organizer']);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create(['reservation_partner_id' => $partner->id, 'capacity' => 3]);
        return [$session, $actor];
    }

    /** Separate PHP processes/connections actually wait for the same PostgreSQL row lock. */
    private function race(ExperienceSession $session, array $arguments): array
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') throw new RuntimeException('Isolated race only');
config(['jakawi.prelaunch_mode' => false, 'jakawi.analytics.enabled' => false]);
Illuminate\Support\Facades\DB::select("select set_config('application_name', 'v3a-capacity-race', false)");
try {
    $service = app(App\Services\ExperienceReservationService::class);
    if ($argv[1] === 'confirm') {
        $service->respond(App\Models\User::findOrFail($argv[2]), App\Models\ExperienceReservation::findOrFail($argv[3]), 'confirmed');
        echo 'confirmed';
    } else {
        $session = App\Models\ExperienceSession::findOrFail($argv[3]);
        $r = $service->request(App\Models\User::findOrFail($argv[2]), $session->experience, $session->id, 2);
        echo (string) $r->id;
    }
} catch (Illuminate\Validation\ValidationException) { echo 'capacity_blocked'; }
PHP;
        DB::beginTransaction();
        ExperienceSession::query()->lockForUpdate()->findOrFail($session->id);
        $processes = [];
        try {
            foreach ($arguments as $args) {
                $p = new Process(['php', '-r', $code, ...array_map('strval', $args)], base_path());
                $p->setTimeout(30); $p->start(); $processes[] = $p;
            }
            $deadline = microtime(true) + 15;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                $waiting = (int) DB::selectOne("select count(*) as n from pg_stat_activity where application_name = 'v3a-capacity-race' and wait_event_type = 'Lock'")->n;
                if ($waiting === count($processes)) break;
                usleep(50000);
            } while (microtime(true) < $deadline);
            $this->assertSame(count($processes), $waiting, 'Both independent connections must contend for the session lock');
        } finally { DB::commit(); }
        return array_map(function (Process $p) { $p->wait(); $this->assertTrue($p->isSuccessful(), $p->getErrorOutput()); return trim($p->getOutput()); }, $processes);
    }

    public function test_two_concurrent_group_confirmations_cannot_oversell_last_seats(): void
    {
        [$session, $actor] = $this->fixture();
        $ids = [];
        foreach (range(1, 2) as $i) {
            $ids[] = ExperienceReservation::create(['user_id' => User::factory()->create()->id, 'experience_id' => $session->experience_id, 'experience_session_id' => $session->id, 'partner_id' => $session->reservation_partner_id, 'status' => 'pending', 'party_size' => 2])->id;
        }
        $results = $this->race($session, array_map(fn ($id) => ['confirm', $actor->id, $id], $ids)); sort($results);
        $this->assertSame(['capacity_blocked', 'confirmed'], $results);
        $this->assertSame(2, $session->confirmedAttendees());
        $this->assertSame(1, $session->reservations()->where('status', 'pending')->count());
    }

    public function test_concurrent_request_retries_create_only_one_reservation_for_original_group(): void
    {
        [$session] = $this->fixture();
        $member = User::factory()->create();
        $member->memberships()->create(['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek(), 'amount_paid' => 0]);
        $results = $this->race($session, [['request', $member->id, $session->id], ['request', $member->id, $session->id]]);
        $this->assertSame($results[0], $results[1]);
        $this->assertSame(1, $session->reservations()->count());
        $this->assertSame(2, $session->reservations()->sole()->party_size);
    }

    public function test_concurrent_confirmation_retries_preserve_one_response_and_occupancy(): void
    {
        [$session, $actor] = $this->fixture();
        $r = ExperienceReservation::create(['user_id' => User::factory()->create()->id, 'experience_id' => $session->experience_id, 'experience_session_id' => $session->id, 'partner_id' => $session->reservation_partner_id, 'status' => 'pending', 'party_size' => 3]);
        $this->assertSame(['confirmed', 'confirmed'], $this->race($session, [['confirm', $actor->id, $r->id], ['confirm', $actor->id, $r->id]]));
        $this->assertSame(3, $session->confirmedAttendees());
        $this->assertSame($actor->id, $r->fresh()->responded_by_user_id);
    }
}
