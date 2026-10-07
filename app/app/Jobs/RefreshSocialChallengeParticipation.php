<?php
namespace App\Jobs;
use App\Integrations\ShareContest\InspectionException;
use App\Integrations\ShareContest\Inspector;
use App\Models\SocialChallengeParticipation;
use App\Services\SocialChallengeQualificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
class RefreshSocialChallengeParticipation implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=6; public int $timeout=40; public function backoff(): array { return [15,30,60,120,240]; }
    public function __construct(public int $participationId, public bool $final=false) { $this->onConnection('database')->onQueue('social'); }
    public function handle(Inspector $inspector, SocialChallengeQualificationService $qualification): void {
        $p=SocialChallengeParticipation::find($this->participationId); if (!$p) return;
        if (!RateLimiter::attempt('sharecontest-social-inspect',50,fn()=>true,60)) { $this->release(max(2,RateLimiter::availableIn('sharecontest-social-inspect')+1)); return; }
        try { $result=$inspector->inspect($p); }
        catch (InspectionException $e) {
            $p->update(['inspection_status'=>'failed','integration_error_code'=>$e->errorCode,'refresh_pending'=>$e->retryable && $this->attempts() < $this->tries]);
            if ($e->retryable) throw $e;
            $this->fail($e);
            return;
        }
        DB::transaction(function () use ($p,$result,$qualification) {
            $locked=SocialChallengeParticipation::query()->lockForUpdate()->findOrFail($p->id);
            $data=$result->data;
            $identity=$data['social_external_id']; $platform=$data['platform'];
            if ($identity && $platform && SocialChallengeParticipation::where('social_challenge_id',$locked->social_challenge_id)->where('platform',$platform)->where('social_external_id',$identity)->whereKeyNot($locked->id)->exists()) {
                $data['social_external_id']=null;
                $data['validation_status']='review_required';
                $locked->integration_error_code='duplicate_social_identity';
            } else $locked->integration_error_code=null;
            $before=$locked->validation_status;
            $locked->fill($data); $locked->sharecontest_payload=$result->payload; $locked->inspection_status='inspected'; $locked->refresh_pending=false;
            if ($this->final && $locked->challenge->qualification_mode === 'RANKED') { $metric=$locked->challenge->metric; $locked->final_metric_value=$locked->$metric; $locked->final_checked_at=$locked->checked_at; }
            $locked->save();
            if ($before !== $locked->validation_status) $qualification->event('social_participation_validation_result',$locked);
            $qualification->evaluate($locked,$this->final);
        });
    }
    public function failed(?\Throwable $e): void { SocialChallengeParticipation::whereKey($this->participationId)->update(['refresh_pending'=>false,'inspection_status'=>'failed','integration_error_code'=>$e instanceof InspectionException ? $e->errorCode : 'retry_exhausted']); }
}
