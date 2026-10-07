<?php
namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSocialEntry;
use Illuminate\Http\Request;

final class ChallengeRanking {
    public function forLanding(Challenge $challenge, Request $request): ?array {
        if ($challenge->selection_type !== 'TOP_N' || $challenge->ranking_visibility === 'NONE') return null;
        if ($challenge->ranking_visibility === 'PARTICIPANTS_ONLY' && (!$request->user() || !$challenge->participations()->where('user_id',$request->user()->id)->exists())) return null;
        $finalReady = $challenge->status === 'closed' && !$challenge->socialEntries()->whereNull('final_checked_at')->exists();
        $official = $finalReady
            && !$challenge->participations()->whereIn('selection_status',['candidate','review_required'])->exists();
        $rows = [];
        foreach ($challenge->participations()->with(['user:id,name','socialEntries','selectedEntry','grant'])->get() as $p) {
            if (!$official && $challenge->reward_eligibility === 'ACTIVE_MEMBERS' && !$p->user?->hasActiveMembership()) continue;
            if ($challenge->qualification_type === 'MANUAL' && $p->qualification_status !== 'qualified') continue;
            if ($official && (!$p->grant || $p->grant->status === 'cancelled')) continue;
            $best = $official ? $p->selectedEntry : $p->socialEntries->filter(fn (ChallengeSocialEntry $e) => $e->validation_status === 'valid' && $e->moderation_status !== 'rejected' && $e->checked_at && $e->{$challenge->selection_metric} !== null
                && ($challenge->qualification_type !== 'METRIC_THRESHOLD' || ($e->{$challenge->qualification_metric} !== null && $e->{$challenge->qualification_metric} >= $challenge->qualification_target))
                && (!$finalReady || $e->final_metric_value !== null))->sortByDesc($finalReady ? 'final_metric_value' : $challenge->selection_metric)->first();
            if (!$best) continue;
            $parts = preg_split('/\s+/u', trim((string)$p->user?->name));
            $display = ($parts[0] ?? 'Participante').(count($parts)>1 ? ' '.mb_substr(end($parts),0,1).'.' : '');
            $rows[] = ['name'=>$display,'score'=>(int)($finalReady ? $best->final_metric_value : $best->{$challenge->selection_metric}),'updated_at'=>($finalReady ? $best->final_checked_at : $best->checked_at)->toIso8601String(),
                'is_you'=>$request->user()?->id === $p->user_id,'confirmed'=>$p->grant !== null && $p->grant->status !== 'cancelled'];
        }
        usort($rows, fn ($a,$b) => ($b['score'] <=> $a['score']) ?: strcmp($a['name'],$b['name']));
        foreach ($rows as $i => &$row) { $row['position']=$i+1; $row['provisional_top']=$i < $challenge->winner_limit; } unset($row);
        return ['rows'=>$rows,'metric'=>$challenge->selection_metric,'updated_at'=>count($rows) ? max(array_column($rows,'updated_at')) : null,
            'threshold'=>$challenge->qualification_type === 'METRIC_THRESHOLD' && $challenge->qualification_metric === $challenge->selection_metric ? $challenge->qualification_target : null,
            'official'=>$official];
    }
}
