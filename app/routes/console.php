<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('unlocks:expire')->everyMinute()->withoutOverlapping();

Artisan::command('challenges:refresh-rankings', function () {
    $queued = 0;
    \App\Models\Challenge::query()->where('review_status','APPROVED')->where('status','open')
        ->where('evidence_type','SOCIAL_POST')->where('selection_type','TOP_N')->where('ranking_visibility','!=','NONE')
        ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at','<=',now()))
        ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at','>',now()))
        ->orderBy('id')->chunkById(50, function ($challenges) use (&$queued) {
            foreach ($challenges as $challenge) {
                if ($queued >= 40) break;
                $stale = $challenge->socialEntries()->where('refresh_pending',false)
                    ->where(fn ($q) => $q->whereNull('checked_at')->orWhere('checked_at','<=',now()->subMinutes(max(15,$challenge->ranking_refresh_interval_minutes ?? 30))))
                    ->where(fn ($q) => $q->whereNull('last_refresh_requested_at')->orWhere('last_refresh_requested_at','<=',now()->subMinutes(max(15,$challenge->ranking_refresh_interval_minutes ?? 30))))
                    ->orderBy('checked_at')->limit(40-$queued)->pluck('id');
                foreach ($stale as $id) {
                    if (\App\Models\ChallengeSocialEntry::whereKey($id)->where('refresh_pending',false)->update(['refresh_pending'=>true,'last_refresh_requested_at'=>now()])) {
                        \App\Jobs\RefreshChallengeSocialEntry::dispatch($id);
                        $queued++;
                    }
                }
            }
        });
    $this->info("{$queued} actualizaciones de ranking en cola.");
})->purpose('Queue stale Top N social entries');
Schedule::command('challenges:refresh-rankings')->everyMinute()->withoutOverlapping();

Artisan::command('challenges:close-expired', function () {
    $closed = 0;
    \App\Models\Challenge::query()->where('review_status', 'APPROVED')->where('status', 'open')
        ->whereNotNull('ends_at')->where('ends_at', '<=', now())->orderBy('id')
        ->chunkById(50, function ($challenges) use (&$closed) {
            foreach ($challenges as $challenge) {
                DB::transaction(function () use ($challenge, &$closed) {
                    $locked = \App\Models\Challenge::lockForUpdate()->find($challenge->id);
                    if (!$locked || $locked->status !== 'open' || $locked->ends_at?->isFuture()) return;
                    $locked->update(['status' => 'closed']);
                    $closed++;
                    if ($locked->evidence_type === 'SOCIAL_POST') {
                        $locked->socialEntries()->select('id')->chunkById(100, function ($entries) {
                            foreach ($entries as $entry) {
                                if (\App\Models\ChallengeSocialEntry::whereKey($entry->id)->where('refresh_pending', false)->update(['refresh_pending' => true])) {
                                    \App\Jobs\RefreshChallengeSocialEntry::dispatch($entry->id, true)->afterCommit();
                                }
                            }
                        });
                    }
                    app(\App\Services\ChallengeService::class)->finalize($locked);
                });
            }
        });
    $this->info("{$closed} retos cerrados.");
})->purpose('Close expired challenges and queue final social snapshots');
Schedule::command('challenges:close-expired')->everyMinute()->withoutOverlapping();
