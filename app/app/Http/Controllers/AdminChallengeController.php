<?php
namespace App\Http\Controllers;

use App\Jobs\RefreshChallengeSocialEntry;
use App\Models\AuditLog;
use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ChallengeSocialEntry;
use App\Services\ChallengeService;
use App\Services\JpLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminChallengeController extends Controller {
    public function index() { return Inertia::render('admin/social-challenges/index',['challenges'=>Challenge::latest()->get(['id','title','slug','status','evidence_type','qualification_type','selection_type','ends_at'])]); }
    public function form(?Challenge $challenge=null) { return Inertia::render('admin/social-challenges/form',['challenge'=>$challenge,'partners'=>\App\Models\Partner::orderBy('name')->get(['id','name']),'cities'=>\App\Models\City::orderBy('name')->get(['id','name']),'benefits'=>Benefit::where('access_mode','social_challenge_grant')->get(['id','partner_id','title'])]); }
    public function save(Request $request, ?Challenge $challenge=null) {
        $data = $request->validate([
            'partner_id'=>['nullable','integer','exists:partners,id'], 'city_id'=>['nullable','integer','exists:cities,id'], 'title'=>['required','string','max:255'],
            'slug'=>['required','alpha_dash','max:255',Rule::unique('challenges','slug')->ignore($challenge?->id)], 'description'=>['nullable','string'],
            'status'=>['required',Rule::in(['draft','open','closed','cancelled'])], 'starts_at'=>['nullable','date'], 'ends_at'=>['nullable','date','after:starts_at'],
            'evidence_type'=>['required',Rule::in(['SOCIAL_POST','MANUAL'])], 'qualification_type'=>['required',Rule::in(['VALID_EVIDENCE','METRIC_THRESHOLD','MANUAL'])],
            'qualification_metric'=>['nullable',Rule::in(['views','likes','comments'])], 'qualification_target'=>['nullable','integer','min:1'],
            'selection_type'=>['required',Rule::in(['ALL_QUALIFIED','FIRST_N','TOP_N','MANUAL'])], 'selection_metric'=>['nullable',Rule::in(['views','likes','comments'])],
            'winner_limit'=>['nullable','integer','min:1'], 'evaluation_mode'=>['required',Rule::in(['CONTINUOUS','AT_CLOSE'])],
            'review_mode'=>['required',Rule::in(['AUTOMATIC','REQUIRED'])], 'participation_eligibility'=>['required',Rule::in(['ALL_USERS','ACTIVE_MEMBERS'])],
            'reward_eligibility'=>['required',Rule::in(['ALL_USERS','ACTIVE_MEMBERS'])], 'max_entries_per_user'=>['nullable','integer','min:1'],
            'allowed_platforms'=>['present','array'], 'allowed_platforms.*'=>['string','max:50'], 'required_hashtags'=>['present','array'], 'required_hashtags.*'=>['string','max:100'],
            'required_mentions'=>['present','array'], 'required_mentions.*'=>['string','max:100'], 'published_from'=>['nullable','date'], 'published_until'=>['nullable','date','after:published_from'],
            'reward_type'=>['required',Rule::in(['BENEFIT','MANUAL_PRIZE','JP'])], 'benefit_id'=>['nullable','integer','exists:benefits,id'], 'manual_prize_description'=>['nullable','string'],
            'reward_jp_amount'=>[Rule::requiredIf($request->input('reward_type')==='JP'),Rule::prohibitedIf($request->input('reward_type')!=='JP'),'nullable','integer','min:1'],
        ]);
        if ($data['qualification_type']==='METRIC_THRESHOLD' && (empty($data['qualification_metric']) || empty($data['qualification_target']) || $data['evidence_type']!=='SOCIAL_POST')) throw ValidationException::withMessages(['qualification_metric'=>'El umbral requiere publicación, métrica y objetivo.']);
        if ($data['evidence_type']==='MANUAL' && $data['qualification_type']!=='MANUAL') throw ValidationException::withMessages(['qualification_type'=>'La evidencia manual requiere revisión de calificación.']);
        if ($data['selection_type']==='TOP_N' && ($data['evidence_type']!=='SOCIAL_POST' || $data['evaluation_mode']!=='AT_CLOSE' || empty($data['selection_metric']) || empty($data['winner_limit']))) throw ValidationException::withMessages(['selection_type'=>'Top N requiere publicación, cierre, métrica y cantidad de ganadores.']);
        if ($data['selection_type']==='FIRST_N' && empty($data['winner_limit'])) throw ValidationException::withMessages(['winner_limit'=>'First N requiere cantidad de ganadores.']);
        if ($data['selection_type']==='MANUAL' && ($data['review_mode']!=='REQUIRED' || empty($data['winner_limit']))) throw ValidationException::withMessages(['review_mode'=>'La selección manual requiere revisión y límite de ganadores.']);
        if ($data['selection_type']==='ALL_QUALIFIED' && !empty($data['winner_limit'])) throw ValidationException::withMessages(['winner_limit'=>'Todos los calificados no usa límite.']);
        if ($data['reward_type']==='BENEFIT') { $benefit=Benefit::find($data['benefit_id'] ?? null); if (!$benefit || !$data['partner_id'] || $benefit->partner_id!=(int)$data['partner_id'] || $benefit->access_mode!=='social_challenge_grant') throw ValidationException::withMessages(['benefit_id'=>'Selecciona un beneficio exclusivo del mismo Partner.']); }
        if ($data['reward_type']==='MANUAL_PRIZE' && empty($data['manual_prize_description'])) throw ValidationException::withMessages(['manual_prize_description'=>'Describe el premio.']);
        $data['reward_jp_amount']=$data['reward_type']==='JP' ? (int)$data['reward_jp_amount'] : null;
        $data['benefit_id']=$data['reward_type']==='BENEFIT' ? ($data['benefit_id'] ?? null) : null;
        $data['manual_prize_description']=$data['reward_type']==='MANUAL_PRIZE' ? ($data['manual_prize_description'] ?? null) : null;
        if (!$challenge && in_array($data['status'],['closed','cancelled'],true)) throw ValidationException::withMessages(['status'=>'Crea el reto como borrador o abierto.']);
        if ($data['status']==='closed' && $challenge?->status!=='closed') throw ValidationException::withMessages(['status'=>'Cierra el reto desde su detalle.']);
        if ($challenge && in_array($challenge->status,['closed','cancelled'],true) && $data['status']!==$challenge->status) throw ValidationException::withMessages(['status'=>'Este reto no se puede reabrir.']);
        if ($challenge && $challenge->participations()->exists()) foreach (['evidence_type','qualification_type','qualification_metric','qualification_target','selection_type','selection_metric','winner_limit','evaluation_mode','review_mode','participation_eligibility','reward_eligibility','max_entries_per_user','reward_type','reward_jp_amount','benefit_id','manual_prize_description','partner_id'] as $field) if (($challenge->$field ?? null) != ($data[$field] ?? null)) throw ValidationException::withMessages([$field=>'No se puede cambiar esta regla después de recibir participaciones.']);
        $challenge ? $challenge->update($data) : $challenge=Challenge::create($data);
        return to_route('admin.social-challenges.show',$challenge->slug);
    }
    public function show(Challenge $challenge) {
        $rows = $challenge->participations()->with(['user','socialEntries','selectedEntry','qualifiedEntry','grant.jpCredit.reversal'])->orderBy('created_at')->get();
        $ranking = $rows->filter(fn ($p) => $p->selectedEntry && in_array($p->selection_status,['candidate','selected'],true))->sort(fn ($a,$b) => ($b->selectedEntry->final_metric_value <=> $a->selectedEntry->final_metric_value) ?: ($a->qualified_at <=> $b->qualified_at) ?: ($a->id <=> $b->id))->values()->map(fn ($p,$i) => ['participation_id'=>$p->id,'position'=>$i+1,'value'=>$p->selectedEntry->final_metric_value])->all();
        return Inertia::render('admin/social-challenges/show',['challenge'=>$challenge,'participations'=>$rows->map(fn ($p) => $p->toArray()+['user_name'=>$p->user->name]),'ranking'=>$ranking]);
    }
    public function participation(Challenge $challenge, ChallengeParticipation $participation) {
        abort_unless($participation->challenge_id===$challenge->id,404);
        return Inertia::render('admin/social-challenges/participation',['challenge'=>$challenge->only(['slug','title']),'participation'=>$participation->load(['user','socialEntries','qualifiedEntry','selectedEntry','grant.jpCredit.reversal'])->toArray()]);
    }
    public function refresh(Challenge $challenge, ?ChallengeParticipation $participation=null) {
        if ($challenge->evidence_type !== 'SOCIAL_POST') return back();
        if ($participation) { abort_unless($participation->challenge_id===$challenge->id,404); $participation->socialEntries()->select('id')->chunkById(100,fn ($rows) => $rows->each(fn ($e) => $this->queue($e,$challenge->status==='closed'))); }
        else $challenge->socialEntries()->select('id')->chunkById(100,fn ($rows) => $rows->each(fn ($e) => $this->queue($e,$challenge->status==='closed')));
        return back()->with('success','Verificación en cola.');
    }
    private function queue(ChallengeSocialEntry $entry, bool $final): void { if (ChallengeSocialEntry::whereKey($entry->id)->where('refresh_pending',false)->update(['refresh_pending'=>true])) RefreshChallengeSocialEntry::dispatch($entry->id,$final)->afterCommit(); }
    public function close(Request $request, Challenge $challenge, ChallengeService $service) {
        DB::transaction(function () use ($request,$challenge,$service) {
            $c=Challenge::lockForUpdate()->findOrFail($challenge->id); if ($c->status!=='open') return;
            $c->update(['status'=>'closed']); $this->audit($request,'challenge_closed',$c);
            if ($c->evidence_type==='SOCIAL_POST') $c->socialEntries()->select('id')->chunkById(100,fn ($rows) => $rows->each(fn ($e) => $this->queue($e,true)));
            else $service->finalize($c);
        }); return back();
    }
    public function review(Request $request, Challenge $challenge, ChallengeParticipation $participation, ChallengeService $service) {
        abort_unless($participation->challenge_id===$challenge->id,404);
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])]]);
        DB::transaction(function () use ($request,$participation,$data,$service,$challenge) {
            $p=ChallengeParticipation::lockForUpdate()->findOrFail($participation->id);
            $p->update(['review_status'=>$data['decision']]); $service->evaluate($p);
            if ($data['decision']==='rejected') $service->reconcileAfterReview($challenge);
            $this->audit($request,'challenge_reviewed',$p,['decision'=>$data['decision']]);
        }); return back();
    }
    public function winner(Request $request, Challenge $challenge, ChallengeParticipation $participation, ChallengeService $service) {
        abort_unless($participation->challenge_id===$challenge->id,404);
        if ($challenge->selection_type==='MANUAL') {
            DB::transaction(function () use ($challenge,$participation) {
                $c=Challenge::lockForUpdate()->findOrFail($challenge->id); $p=ChallengeParticipation::lockForUpdate()->findOrFail($participation->id);
                if ($p->qualification_status!=='qualified' || ($c->winner_limit!==null && $c->participations()->whereIn('selection_status',['candidate','selected'])->whereKeyNot($p->id)->count()>=$c->winner_limit)) throw ValidationException::withMessages(['winner'=>'Esta participación no puede seleccionarse.']);
                $p->update(['selection_status'=>'candidate','selected_entry_id'=>$p->qualified_entry_id,'selected_at'=>now()]);
            });
        }
        $service->confirm($participation,$request->user()->id);
        $this->audit($request,'challenge_winner_confirmed',$participation); return back();
    }
    public function grant(Request $request, Challenge $challenge, ChallengeParticipation $participation, ChallengeService $service) {
        abort_unless($participation->challenge_id===$challenge->id,404);
        if ($challenge->review_mode==='REQUIRED') $service->confirm($participation,$request->user()->id);
        else $service->selectAndMaybeGrant($participation,$request->user()->id);
        return back();
    }
    public function fulfill(Request $request, Challenge $challenge, ChallengeParticipation $participation) {
        abort_unless($participation->challenge_id===$challenge->id,404);
        DB::transaction(function () use ($request,$participation) { $grant=$participation->grant()->lockForUpdate()->firstOrFail(); abort_unless($grant->reward_type==='MANUAL_PRIZE' && $grant->status==='granted',422); $grant->update(['status'=>'fulfilled','fulfilled_at'=>now()]); $this->audit($request,'challenge_manual_fulfilled',$grant); }); return back();
    }
    public function cancelGrant(Request $request, Challenge $challenge, ChallengeParticipation $participation) {
        abort_unless($participation->challenge_id===$challenge->id,404);
        DB::transaction(function () use ($request,$participation) {
            $grant=$participation->grant()->lockForUpdate()->firstOrFail(); if ($grant->status==='cancelled') return;
            if ($grant->status!=='granted') throw ValidationException::withMessages(['grant'=>'Este premio ya no se puede cancelar.']);
            if ($grant->reward_type==='JP' && $grant->jpCredit) app(JpLedgerService::class)->reverse($grant->jpCredit,$request->user()->id,'challenge_reward_cancelled');
            $grant->update(['status'=>'cancelled','cancelled_at'=>now()]); $this->audit($request,'challenge_reward_cancelled',$grant);
        }); return back();
    }
    private function audit(Request $request,string $action,object $subject,array $metadata=[]): void { AuditLog::create(['actor_user_id'=>$request->user()->id,'action'=>$action,'subject_type'=>$subject::class,'subject_id'=>$subject->id,'metadata'=>$metadata]); }
}
