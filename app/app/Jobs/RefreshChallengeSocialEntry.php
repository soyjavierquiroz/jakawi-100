<?php
namespace App\Jobs;

use App\Integrations\ShareContest\InspectionException;
use App\Integrations\ShareContest\Inspector;
use App\Models\ChallengeSocialEntry;
use App\Services\ChallengeService;
use App\Services\SocialEntryRules;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class RefreshChallengeSocialEntry implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 6;
    public int $timeout = 40;
    public function backoff(): array { return [15,30,60,120,240]; }
    public function __construct(public int $entryId, public bool $final = false, public bool $interactive = false) { $this->onConnection('database')->onQueue($interactive ? 'social-interactive' : 'social'); }
    public function handle(Inspector $inspector, ChallengeService $service, ?SocialEntryRules $rules = null): void {
        $rules ??= app(SocialEntryRules::class);
        $entry = ChallengeSocialEntry::find($this->entryId);
        if (!$entry) return;
        if (!RateLimiter::attempt('sharecontest-social-inspect', 50, fn () => true, 60)) { $this->release(max(2, RateLimiter::availableIn('sharecontest-social-inspect') + 1)); return; }
        try { $result = $inspector->inspect($entry); }
        catch (InspectionException $e) {
            $entry->update(['inspection_status'=>'failed','integration_error_code'=>$e->errorCode,'refresh_pending'=>$e->retryable && $this->attempts() < $this->tries]);
            if ($e->retryable) throw $e;
            $this->fail($e);
            return;
        }
        DB::transaction(function () use ($entry, $result, $service, $rules) {
            $locked = ChallengeSocialEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $final = $this->final || $locked->challenge->status === 'closed';
            $data = $result->data;
            if ($data['social_external_id'] && $data['platform'] && ChallengeSocialEntry::where('challenge_id', $locked->challenge_id)->where('platform', $data['platform'])->where('social_external_id', $data['social_external_id'])->whereKeyNot($locked->id)->exists()) {
                $data['social_external_id'] = null;
                $data['validation_status'] = 'review_required';
                $locked->integration_error_code = 'duplicate_social_identity';
            } else $locked->integration_error_code = null;
            $locked->fill($data);
            $locked->sharecontest_payload = $result->payload;
            $locked->inspection_status = 'inspected';
            $locked->validation_status = $rules->validationStatus($locked->challenge, $locked, $data['validation_status'] ?? 'not_requested');
            $locked->refresh_pending = false;
            if ($final) {
                $metric = $locked->challenge->selection_metric;
                $locked->final_metric_value = $metric ? $locked->{$metric} : null;
                $locked->final_checked_at = $locked->checked_at ?? now();
            }
            $locked->save();
            $service->evaluate($locked->participation, $final);
            if ($final) $service->finalize($locked->challenge);
        });
    }
    public function failed(?\Throwable $e): void {
        DB::transaction(function () use ($e) {
            $entry = ChallengeSocialEntry::query()->lockForUpdate()->find($this->entryId);
            if (!$entry) return;
            $final = $this->final || $entry->challenge->status === 'closed';
            $entry->update(['refresh_pending'=>false, 'inspection_status'=>'failed',
                'integration_error_code'=>$e instanceof InspectionException ? $e->errorCode : 'retry_exhausted',
                'validation_status'=>$final ? 'review_required' : $entry->validation_status,
                'final_metric_value'=>$final ? null : $entry->final_metric_value,
                'final_checked_at'=>$final ? now() : $entry->final_checked_at]);
            if ($final) {
                app(ChallengeService::class)->evaluate($entry->participation, true);
                app(ChallengeService::class)->finalize($entry->challenge);
            }
        });
    }
}
