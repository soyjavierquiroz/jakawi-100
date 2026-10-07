<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ChallengeRewardGrant;
use App\Models\ChallengeSocialEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChallengeService {
    public function evaluate(ChallengeParticipation $participation, bool $final = false): ChallengeParticipation {
        return DB::transaction(function () use ($participation, $final) {
            $c = Challenge::query()->lockForUpdate()->findOrFail($participation->challenge_id);
            $p = ChallengeParticipation::query()->lockForUpdate()->findOrFail($participation->id);
            $entries = $c->evidence_type === 'SOCIAL_POST' ? $p->socialEntries()->orderBy('checked_at')->orderBy('id')->get() : collect();
            $qualifiers = $entries->filter(fn ($e) => $this->entryQualifies($c, $e));
            $qualifiedEntry = $qualifiers->first();
            $status = match (true) {
                $p->review_status === 'rejected' => 'not_qualified',
                $c->qualification_type === 'MANUAL' || $c->evidence_type === 'MANUAL' => $p->review_status === 'approved' ? 'qualified' : 'review_required',
                $qualifiedEntry !== null => 'qualified',
                $entries->contains(fn ($e) => $e->validation_status === 'review_required' || ($c->qualification_type === 'METRIC_THRESHOLD' && $e->validation_status === 'valid' && $e->{$c->qualification_metric} === null)) => 'review_required',
                $final || $c->status === 'closed' => 'not_qualified',
                default => 'pending',
            };
            $wasQualified = $p->qualification_status === 'qualified';
            $p->qualification_status = $status;
            if ($status === 'qualified') {
                if (!$wasQualified) $p->qualified_at = $qualifiedEntry?->checked_at ?? now();
                if ($qualifiedEntry && (!$p->qualified_entry_id || !$wasQualified)) $p->qualified_entry_id = $qualifiedEntry->id;
            } elseif (!$p->grant()->exists() && in_array($p->selection_status, ['candidate','selected'], true)) {
                $p->selection_status = 'pending';
                $p->selected_entry_id = null;
                $p->selected_at = null;
            }
            $p->save();
            if ($status === 'qualified' && !$wasQualified) $this->event('challenge_qualified', $p);
            if ($status === 'qualified' && $c->evaluation_mode === 'CONTINUOUS' && in_array($c->selection_type, ['ALL_QUALIFIED','FIRST_N'], true)) $this->selectAndMaybeGrant($p);
            return $p->fresh();
        });
    }

    private function entryQualifies(Challenge $c, ChallengeSocialEntry $e): bool {
        if ($e->validation_status !== 'valid' || $e->checked_at === null || $e->moderation_status === 'rejected') return false;
        return match ($c->qualification_type) {
            'VALID_EVIDENCE' => true,
            'METRIC_THRESHOLD' => $e->{$c->qualification_metric} !== null && $e->{$c->qualification_metric} >= $c->qualification_target,
            default => false,
        };
    }

    public function selectAndMaybeGrant(ChallengeParticipation $participation, ?int $adminId = null): ?ChallengeRewardGrant {
        return DB::transaction(function () use ($participation, $adminId) {
            $c = Challenge::query()->lockForUpdate()->findOrFail($participation->challenge_id);
            $p = ChallengeParticipation::query()->lockForUpdate()->findOrFail($participation->id);
            if ($p->qualification_status !== 'qualified' || $p->review_status === 'rejected') return null;
            if ($c->selection_type === 'TOP_N' || $c->selection_type === 'MANUAL') return null;
            if ($c->evaluation_mode === 'AT_CLOSE' && $c->status !== 'closed') return null;
            if (!$this->rewardEligible($c, $p)) { $p->update(['selection_status'=>'pending']); return null; }
            $selected = $c->participations()->whereIn('selection_status', ['candidate','selected'])->whereKeyNot($p->id)->count();
            $limit = $c->winner_limit;
            if ($limit !== null && $selected >= $limit) return null;
            $p->update(['selection_status'=>$c->review_mode === 'REQUIRED' ? 'candidate' : 'selected', 'selected_entry_id'=>$p->qualified_entry_id, 'selected_at'=>$p->selected_at ?? now()]);
            if ($c->review_mode === 'REQUIRED') return null;
            return $this->grantLocked($c, $p, $adminId);
        });
    }

    public function finalize(Challenge $challenge): void {
        DB::transaction(function () use ($challenge) {
            $c = Challenge::query()->lockForUpdate()->findOrFail($challenge->id);
            if ($c->status !== 'closed') return;
            if ($c->evidence_type === 'SOCIAL_POST' && $c->socialEntries()->whereNull('final_checked_at')->exists()) return;
            foreach ($c->participations()->orderBy('id')->get() as $p) $this->evaluate($p, true);
            if ($c->selection_type === 'TOP_N') {
                $rank = [];
                foreach ($c->participations()->with('socialEntries')->where('qualification_status','qualified')->get() as $p) {
                    if (!$this->rewardEligible($c, $p)) continue;
                    $best = $p->socialEntries->filter(fn ($e) => $this->entryQualifies($c, $e) && $e->final_checked_at && $e->final_metric_value !== null)
                        ->sort(fn ($a, $b) => ($b->final_metric_value <=> $a->final_metric_value) ?: (($a->checked_at ?? $a->created_at) <=> ($b->checked_at ?? $b->created_at)) ?: ($a->id <=> $b->id))->first();
                    if ($best) $rank[] = ['p'=>$p, 'entry'=>$best, 'score'=>$best->final_metric_value];
                    elseif ($p->socialEntries->contains(fn ($e) => $this->entryQualifies($c, $e) && $e->final_metric_value === null)) $p->update(['selection_status'=>'review_required']);
                }
                usort($rank, fn ($a,$b) => ($b['score'] <=> $a['score']) ?: (($a['p']->qualified_at ?? $a['entry']->created_at) <=> ($b['p']->qualified_at ?? $b['entry']->created_at)) ?: ($a['p']->id <=> $b['p']->id));
                $grantedIds = $c->grants()->pluck('participation_id')->all();
                $c->participations()->whereIn('selection_status',['candidate','selected'])->whereDoesntHave('grant')->update(['selection_status'=>'pending','selected_entry_id'=>null,'selected_at'=>null]);
                $available = $c->winner_limit === null ? count($rank) : max(0, $c->winner_limit - count($grantedIds));
                $remaining = array_values(array_filter($rank, fn ($row) => !in_array($row['p']->id, $grantedIds, true)));
                foreach (array_slice($remaining, 0, $available) as $position => $row) {
                    $p = $row['p'];
                    $p->update(['selection_status'=>$c->review_mode === 'REQUIRED' ? 'candidate' : 'selected', 'selected_entry_id'=>$row['entry']->id, 'selected_at'=>$p->selected_at ?? now()]);
                    if ($c->review_mode === 'AUTOMATIC') $this->grantLocked($c, $p, null, count($grantedIds) + $position + 1);
                }
            } elseif (in_array($c->selection_type, ['ALL_QUALIFIED','FIRST_N'], true)) {
                foreach ($c->participations()->where('qualification_status','qualified')->orderBy('qualified_at')->orderBy('id')->get() as $p) $this->selectAndMaybeGrant($p);
            }
        });
    }

    public function confirm(ChallengeParticipation $participation, int $adminId): ChallengeRewardGrant {
        return DB::transaction(function () use ($participation, $adminId) {
            $c = Challenge::query()->lockForUpdate()->findOrFail($participation->challenge_id);
            $p = ChallengeParticipation::query()->lockForUpdate()->findOrFail($participation->id);
            if ($p->selection_status !== 'candidate' || $p->qualification_status !== 'qualified' || !$this->rewardEligible($c, $p)) throw ValidationException::withMessages(['winner'=>'Esta participación no es candidata elegible.']);
            $p->update(['selection_status'=>'selected','review_status'=>'approved']);
            return $this->grantLocked($c, $p, $adminId);
        });
    }

    public function reconcileAfterReview(Challenge $challenge): void {
        $c = Challenge::findOrFail($challenge->id);
        if ($c->selection_type === 'TOP_N' && $c->status === 'closed') { $this->finalize($c); return; }
        if ($c->selection_type === 'FIRST_N') {
            foreach ($c->participations()->where('qualification_status','qualified')->where('selection_status','pending')->orderBy('qualified_at')->orderBy('id')->get() as $p) $this->selectAndMaybeGrant($p);
        }
    }

    private function rewardEligible(Challenge $c, ChallengeParticipation $p): bool { return $c->reward_eligibility === 'ALL_USERS' || $p->user->hasActiveMembership(); }

    private function grantLocked(Challenge $c, ChallengeParticipation $p, ?int $adminId, ?int $rankingPosition = null): ChallengeRewardGrant {
        if ($p->selection_status !== 'selected' || $p->qualification_status !== 'qualified' || !$this->rewardEligible($c, $p)) throw ValidationException::withMessages(['grant'=>'La participación no es elegible para premio.']);
        $existing = $p->grant()->first();
        if ($existing) return $existing;
        $count = $c->grants()->count();
        if ($c->winner_limit !== null && $count >= $c->winner_limit) throw ValidationException::withMessages(['grant'=>'No quedan premios disponibles.']);
        $entry = $p->selectedEntry;
        $grant = ChallengeRewardGrant::create([
            'challenge_id'=>$c->id, 'participation_id'=>$p->id, 'user_id'=>$p->user_id, 'reward_type'=>$c->reward_type,
            'benefit_id'=>$c->reward_type === 'BENEFIT' ? $c->benefit_id : null, 'jp_amount'=>$c->reward_type === 'JP' ? $c->reward_jp_amount : null,
            'winning_entry_id'=>$entry?->id, 'status'=>'granted', 'granted_at'=>now(), 'granted_by_user_id'=>$adminId,
            'snapshot'=>['evidence_type'=>$c->evidence_type, 'qualification_type'=>$c->qualification_type, 'selection_type'=>$c->selection_type,
                'qualified_entry_id'=>$p->qualified_entry_id, 'winning_entry_id'=>$entry?->id, 'selection_metric'=>$c->selection_metric,
                'metric_value'=>$entry?->final_metric_value ?? ($entry && $c->selection_metric ? $entry->{$c->selection_metric} : null),
                'ranking_position'=>$rankingPosition, 'reward_type'=>$c->reward_type, 'jp_amount'=>$c->reward_type === 'JP' ? $c->reward_jp_amount : null],
        ]);
        if ($grant->reward_type === 'JP') app(JpLedgerService::class)->socialChallengeCredit($grant);
        $this->event('challenge_reward_granted', $p);
        AuditLog::create(['actor_user_id'=>$adminId, 'action'=>'challenge_reward_granted', 'subject_type'=>ChallengeRewardGrant::class, 'subject_id'=>$grant->id, 'metadata'=>['participation_id'=>$p->id]]);
        return $grant;
    }

    public function event(string $name, ChallengeParticipation $p): void {
        app(AnalyticsTracker::class)->record($name, ['user_id'=>$p->user_id], ['challenge_id'=>$p->challenge_id, 'evidence_type'=>$p->challenge->evidence_type, 'qualification_type'=>$p->challenge->qualification_type, 'reward_type'=>$p->challenge->reward_type]);
    }
}
