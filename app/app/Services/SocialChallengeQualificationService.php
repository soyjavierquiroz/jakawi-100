<?php
namespace App\Services;
use App\Models\SocialChallengeParticipation;
use App\Models\SocialChallengeRewardGrant;
use Illuminate\Support\Facades\DB;
final class SocialChallengeQualificationService {
    public function evaluate(SocialChallengeParticipation $participation, bool $final = false): SocialChallengeParticipation {
        return DB::transaction(function () use ($participation,$final) {
            $p=SocialChallengeParticipation::query()->lockForUpdate()->findOrFail($participation->id);
            $challenge=$p->challenge; $before=$p->qualification_status;
            $status=match (true) {
                $p->moderation_status === 'rejected' => 'not_qualified',
                $challenge->qualification_mode === 'MANUAL' => $p->moderation_status === 'approved' ? 'qualified' : 'review_required',
                $p->validation_status === 'invalid' => 'not_qualified',
                $p->validation_status === 'review_required' => 'review_required',
                $p->validation_status !== 'valid' => 'pending',
                $challenge->qualification_mode === 'VALID_POST' => 'qualified',
                $challenge->qualification_mode === 'RANKED' => $p->{$challenge->metric} === null ? 'review_required' : 'pending',
                $p->{$challenge->metric} === null => 'review_required',
                $p->{$challenge->metric} >= $challenge->target => 'qualified',
                $final || $challenge->status === 'closed' => 'not_qualified',
                default => 'pending',
            };
            if ($p->moderation_status === 'approved' && $p->validation_status === 'review_required' && $challenge->qualification_mode !== 'RANKED') $status='qualified';
            if ($status !== $before) {
                $p->qualification_status=$status; $p->save();
                if ($status === 'qualified') $this->event('social_challenge_qualified',$p);
            }
            if ($status === 'qualified' && $challenge->evaluation_mode === 'CONTINUOUS' && in_array($challenge->qualification_mode,['VALID_POST','METRIC_THRESHOLD'],true) && $p->moderation_status !== 'pending' && $p->moderation_status !== 'rejected') $this->grant($p);
            return $p;
        });
    }
    public function grant(SocialChallengeParticipation $p, ?int $adminId=null, ?int $rankingPosition=null): SocialChallengeRewardGrant {
        $c=$p->challenge;
        $grant=SocialChallengeRewardGrant::firstOrCreate(['participation_id'=>$p->id],[
            'social_challenge_id'=>$c->id,'user_id'=>$p->user_id,'reward_type'=>$c->reward_type,'benefit_id'=>$c->benefit_id,
            'status'=>'granted','granted_at'=>now(),'granted_by_user_id'=>$adminId,
            'snapshot'=>['qualification_mode'=>$c->qualification_mode,'metric'=>$c->metric,'metric_value'=>$c->metric ? ($p->final_metric_value ?? $p->{$c->metric}) : null,
                'validation_status'=>$p->validation_status,'data_quality'=>$p->data_quality,'checked_at'=>$p->checked_at?->toIso8601String(),'ranking_position'=>$rankingPosition],
        ]);
        if ($grant->wasRecentlyCreated) { $this->event('social_challenge_reward_granted',$p); \App\Models\AuditLog::create(['actor_user_id'=>$adminId,'action'=>'social_challenge_reward_granted','subject_type'=>SocialChallengeRewardGrant::class,'subject_id'=>$grant->id,'metadata'=>['participation_id'=>$p->id]]); }
        return $grant;
    }
    public function event(string $name, SocialChallengeParticipation $p): void {
        app(AnalyticsTracker::class)->record($name,['user_id'=>$p->user_id],[
            'challenge_id'=>$p->social_challenge_id,'qualification_mode'=>$p->challenge->qualification_mode,
            'platform'=>$p->platform ?? 'unknown','result'=>$p->qualification_status,'reward_type'=>$p->challenge->reward_type,
            'metric'=>$p->challenge->metric ?? 'none',
        ]);
    }
}
