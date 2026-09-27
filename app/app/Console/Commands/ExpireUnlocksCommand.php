<?php
namespace App\Console\Commands;
use App\Services\UnlockStatusService; use Illuminate\Console\Command;
class ExpireUnlocksCommand extends Command { protected $signature='unlocks:expire'; protected $description='Expire missed unlock goals'; public function handle(UnlockStatusService $service): int {$this->info((string)$service->expireMissedGoals());return self::SUCCESS;} }
