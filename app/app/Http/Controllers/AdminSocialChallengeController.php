<?php
namespace App\Http\Controllers;
use App\Jobs\RefreshSocialChallengeParticipation;
use App\Models\AuditLog;
use App\Models\Benefit;
use App\Models\SocialChallenge;
use App\Models\SocialChallengeParticipation;
use App\Services\SocialChallengeQualificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class AdminSocialChallengeController extends Controller {
    public function index() { return Inertia::render('admin/social-challenges/index',['challenges'=>SocialChallenge::latest()->get(['id','title','slug','status','qualification_mode','ends_at'])]); }
    public function form(?SocialChallenge $challenge=null) { return Inertia::render('admin/social-challenges/form',['challenge'=>$challenge,'partners'=>\App\Models\Partner::orderBy('name')->get(['id','name']),'cities'=>\App\Models\City::orderBy('name')->get(['id','name']),'benefits'=>Benefit::where('access_mode','social_challenge_grant')->get(['id','partner_id','title'])]); }
    public function save(Request $request, ?SocialChallenge $challenge=null) {
        $data=$request->validate([
            'partner_id'=>['nullable','integer','exists:partners,id'],'city_id'=>['nullable','integer','exists:cities,id'], 'title'=>['required','string','max:255'],
            'slug'=>['required','alpha_dash','max:255',Rule::unique('social_challenges','slug')->ignore($challenge?->id)], 'description'=>['nullable','string'],
            'status'=>['required',Rule::in(['draft','open','closed','cancelled'])], 'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date','after:starts_at'],
            'allowed_platforms'=>['required','array'],'allowed_platforms.*'=>['string','max:50'], 'required_hashtags'=>['required','array'],'required_hashtags.*'=>['string','max:100'],
            'required_mentions'=>['required','array'],'required_mentions.*'=>['string','max:100'], 'published_from'=>['nullable','date'], 'published_until'=>['nullable','date','after:published_from'],
            'qualification_mode'=>['required',Rule::in(['VALID_POST','METRIC_THRESHOLD','RANKED','MANUAL'])], 'evaluation_mode'=>['required',Rule::in(['CONTINUOUS','AT_CLOSE'])],
            'metric'=>['nullable',Rule::in(['views','likes','comments'])], 'target'=>['nullable','integer','min:1'], 'winner_count'=>['nullable','integer','min:1'],
            'reward_type'=>['required',Rule::in(['BENEFIT','MANUAL_PRIZE'])], 'benefit_id'=>['nullable','integer','exists:benefits,id'],'manual_prize_description'=>['nullable','string'],
        ]);
        if ($data['qualification_mode']==='RANKED' && ($data['evaluation_mode']!=='AT_CLOSE' || empty($data['metric']) || empty($data['winner_count']))) throw ValidationException::withMessages(['qualification_mode'=>'El ranking requiere evaluación al cierre, métrica y cantidad de ganadores.']);
        if ($data['qualification_mode']==='METRIC_THRESHOLD' && (empty($data['metric']) || empty($data['target']))) throw ValidationException::withMessages(['metric'=>'El umbral requiere métrica y objetivo.']);
        if ($data['reward_type']==='BENEFIT') { $benefit=Benefit::find($data['benefit_id'] ?? null); if (!$benefit || !$data['partner_id'] || $benefit->partner_id!=(int)$data['partner_id'] || $benefit->access_mode!=='social_challenge_grant') throw ValidationException::withMessages(['benefit_id'=>'Selecciona un beneficio exclusivo del mismo Partner.']); }
        if ($data['reward_type']==='MANUAL_PRIZE' && empty($data['manual_prize_description'])) throw ValidationException::withMessages(['manual_prize_description'=>'Describe el premio.']);
        if (!$challenge && in_array($data['status'],['closed','cancelled'],true)) throw ValidationException::withMessages(['status'=>'Crea el reto como borrador o abierto.']);
        if ($data['status']==='closed' && $challenge?->status!=='closed') throw ValidationException::withMessages(['status'=>'Cierra el reto desde su detalle.']);
        if ($challenge && in_array($challenge->status,['closed','cancelled'],true) && $data['status']!==$challenge->status) throw ValidationException::withMessages(['status'=>'Este reto no se puede reabrir.']);
        if ($challenge && $challenge->participations()->exists()) foreach (['qualification_mode','evaluation_mode','metric','target','winner_count','reward_type','benefit_id','partner_id'] as $field) if (($challenge->$field ?? null) != ($data[$field] ?? null)) throw ValidationException::withMessages([$field=>'No se puede cambiar esta regla después de recibir participaciones.']);
        $challenge ? $challenge->update($data) : $challenge=SocialChallenge::create($data);
        return to_route('admin.social-challenges.show',$challenge->slug);
    }
    public function show(SocialChallenge $challenge) {
        $rows=$challenge->participations()->with(['user','grant'])->orderBy('created_at')->get();
        $ranking=$challenge->qualification_mode==='RANKED' ? $rows->filter(fn($p)=>$p->final_checked_at && $p->final_metric_value!==null && $p->validation_status==='valid' && $p->moderation_status!=='rejected')->sort(fn($a,$b)=>($b->final_metric_value<=>$a->final_metric_value) ?: ($a->created_at<=>$b->created_at) ?: ($a->id<=>$b->id))->values()->map(fn($p,$i)=>['participation_id'=>$p->id,'position'=>$i+1,'value'=>$p->final_metric_value])->all() : [];
        return Inertia::render('admin/social-challenges/show',['challenge'=>$challenge,'participations'=>$rows->map(fn($p)=>$p->toArray()+['user_name'=>$p->user->name]),'ranking'=>$ranking]);
    }
    public function participation(SocialChallenge $challenge, SocialChallengeParticipation $participation) {
        abort_unless($participation->social_challenge_id===$challenge->id,404);
        return Inertia::render('admin/social-challenges/participation',['challenge'=>$challenge->only(['slug','title']),'participation'=>$participation->load(['user','grant'])->toArray()]);
    }
    public function refresh(SocialChallenge $challenge, ?SocialChallengeParticipation $participation=null) {
        if ($participation) { abort_unless($participation->social_challenge_id===$challenge->id,404); $this->queue($participation,$challenge->status==='closed'); }
        else $challenge->participations()->select('id')->chunkById(100,fn($rows)=>$rows->each(fn($p)=>$this->queue($p,$challenge->status==='closed')));
        return back()->with('success','Verificación en cola.');
    }
    private function queue(SocialChallengeParticipation $p, bool $final): void {
        if (SocialChallengeParticipation::whereKey($p->id)->where('refresh_pending',false)->update(['refresh_pending'=>true])) RefreshSocialChallengeParticipation::dispatch($p->id,$final)->afterCommit();
    }
    public function close(Request $request, SocialChallenge $challenge) {
        DB::transaction(function() use($request,$challenge) {
            $c=SocialChallenge::lockForUpdate()->findOrFail($challenge->id); if ($c->status!=='open') return;
            $c->update(['status'=>'closed']); $this->audit($request,'social_challenge_closed',$c);
            $c->participations()->select('id')->chunkById(100,fn($rows)=>$rows->each(fn($p)=>$this->queue($p,true)));
        }); return back();
    }
    public function review(Request $request, SocialChallenge $challenge, SocialChallengeParticipation $participation, SocialChallengeQualificationService $service) {
        abort_unless($participation->social_challenge_id===$challenge->id,404);
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])]]);
        DB::transaction(function() use($request,$participation,$data,$service,$challenge) {
            $p=SocialChallengeParticipation::lockForUpdate()->findOrFail($participation->id);
            $p->update(['moderation_status'=>$data['decision']]); $service->evaluate($p);
            if ($challenge->qualification_mode==='MANUAL' && $data['decision']==='approved') $service->grant($p->fresh(),$request->user()->id);
            $this->audit($request,'social_challenge_reviewed',$p,['decision'=>$data['decision']]);
        }); return back();
    }
    public function winner(Request $request, SocialChallenge $challenge, SocialChallengeParticipation $participation, SocialChallengeQualificationService $service) {
        abort_unless($challenge->qualification_mode==='RANKED' && $challenge->status==='closed' && $participation->social_challenge_id===$challenge->id,422);
        DB::transaction(function() use($request,$challenge,$participation,$service) {
            $p=SocialChallengeParticipation::lockForUpdate()->findOrFail($participation->id);
            if (!$p->final_checked_at || $p->final_metric_value===null || $p->validation_status!=='valid' || $p->moderation_status==='rejected') throw ValidationException::withMessages(['winner'=>'Esta publicación requiere revisión.']);
            $winners=\App\Models\SocialChallengeRewardGrant::where('social_challenge_id',$challenge->id)->count();
            if ($winners >= $challenge->winner_count && !$p->grant()->exists()) throw ValidationException::withMessages(['winner'=>'Ya se confirmó la cantidad máxima de ganadores.']);
            $p->update(['qualification_status'=>'qualified']); $service->grant($p,$request->user()->id,$winners+1); $this->audit($request,'social_challenge_winner_confirmed',$p);
        }); return back();
    }
    public function fulfill(Request $request, SocialChallenge $challenge, SocialChallengeParticipation $participation) {
        abort_unless($participation->social_challenge_id===$challenge->id,404);
        DB::transaction(function() use($request,$participation) { $grant=$participation->grant()->lockForUpdate()->firstOrFail(); abort_unless($grant->reward_type==='MANUAL_PRIZE' && $grant->status==='granted',422); $grant->update(['status'=>'fulfilled','fulfilled_at'=>now()]); $this->audit($request,'social_challenge_manual_fulfilled',$grant); }); return back();
    }
    public function grant(Request $request, SocialChallenge $challenge, SocialChallengeParticipation $participation, SocialChallengeQualificationService $service) {
        abort_unless($participation->social_challenge_id===$challenge->id && $challenge->qualification_mode!=='RANKED' && $challenge->qualification_mode!=='MANUAL',422);
        DB::transaction(function() use($request,$challenge,$participation,$service) {
            $p=SocialChallengeParticipation::lockForUpdate()->findOrFail($participation->id);
            if ($p->qualification_status!=='qualified' || ($challenge->evaluation_mode==='AT_CLOSE' && $challenge->status!=='closed')) throw ValidationException::withMessages(['grant'=>'La participación todavía no se puede premiar.']);
            $service->grant($p,$request->user()->id);
        }); return back();
    }
    public function cancelGrant(Request $request, SocialChallenge $challenge, SocialChallengeParticipation $participation) {
        abort_unless($participation->social_challenge_id===$challenge->id,404);
        DB::transaction(function() use($request,$participation) {
            $grant=$participation->grant()->lockForUpdate()->firstOrFail();
            if ($grant->status!=='granted') throw ValidationException::withMessages(['grant'=>'Este premio ya no se puede cancelar.']);
            $grant->update(['status'=>'cancelled','cancelled_at'=>now()]); $this->audit($request,'social_challenge_reward_cancelled',$grant);
        }); return back();
    }
    private function audit(Request $request,string $action,object $subject,array $metadata=[]): void { AuditLog::create(['actor_user_id'=>$request->user()->id,'action'=>$action,'subject_type'=>$subject::class,'subject_id'=>$subject->id,'metadata'=>$metadata]); }
}
