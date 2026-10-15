<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\City;
use App\Models\CommunityInteraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CityCommunityConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    // Child processes need committed fixtures; clean only this test's data below.
    protected array $connectionsToTransact = [];

    public function test_two_simultaneous_first_exposures_count_once_even_with_different_sessions(): void
    {
        $city = City::where('slug', 'cochabamba')->firstOrFail();
        $previous = $city->only(['community_enabled', 'community_name', 'community_invite_url']);
        $city->update(['community_enabled' => true, 'community_name' => 'Comunidad QA', 'community_invite_url' => 'https://chat.whatsapp.com/'.str_repeat('A', 22)]);
        $user = User::factory()->create();
        $barrier = sys_get_temp_dir().'/community-race-'.bin2hex(random_bytes(8));
        $processes = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/community-race.php'), (string) $user->id, $city->slug, str_repeat((string) ($i + 1), 40), $barrier, (string) $i], base_path());
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (! file_exists($barrier.'.0') || ! file_exists($barrier.'.1')) {
                $this->assertLessThan($deadline, microtime(true), 'Children did not reach the concurrency barrier.');
                usleep(10000);
            }
            touch($barrier.'.go');
            $accepted = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $accepted[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['accepted'];
            }
            sort($accepted);
            $this->assertSame([false, true], $accepted);
            $this->assertSame(1, CommunityInteraction::sole()->exposure_count);
            $this->assertSame(1, AnalyticsEvent::where('event_name', 'community_presented')->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            AnalyticsEvent::where('user_id', $user->id)->delete();
            $user->delete();
            $city->update($previous);
            foreach (glob($barrier.'.*') as $file) {
                unlink($file);
            }
        }
    }
}
