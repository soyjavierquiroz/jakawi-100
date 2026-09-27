<?php
namespace App\Console\Commands;
use App\Services\UnlockStatusService; use Illuminate\Console\Command;
class ExpireUnlocksCommand extends Command { protected $signature='unlocks:expire'; protected $description='Process unlock goals and participation deadlines'; public function handle(UnlockStatusService $service, \App\Services\UnlockParticipationService $participations): int {$this->info((string)$service->expireMissedGoals());$this->line(json_encode($participations->processDeadlines()));return self::SUCCESS;} }
