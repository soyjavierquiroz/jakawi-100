<?php
namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\City;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PartnerChallengeController extends Controller {
    private function authorizePartner(Request $request, Partner $partner): void {
        $role = $request->user()->partners()->whereKey($partner->id)->firstOrFail()->pivot->role;
        abort_unless(in_array($role, ['owner','manager'], true), 403);
    }
    private function own(Partner $partner, Challenge $challenge): void { abort_unless($challenge->partner_id === $partner->id, 404); }
    public function index(Request $request, Partner $partner) {
        $this->authorizePartner($request,$partner);
        return Inertia::render('partner/challenges/index',['partner'=>$partner->only('name','slug'),'challenges'=>Challenge::where('partner_id',$partner->id)->latest()->get(['title','slug','status','review_status','review_comment'])]);
    }
    public function form(Request $request, Partner $partner, ?Challenge $challenge=null) {
        $this->authorizePartner($request,$partner);
        if ($challenge) $this->own($partner,$challenge);
        return Inertia::render('partner/challenges/form',['partner'=>$partner->only('id','name','slug'),'challenge'=>$challenge,
            'heroUrl'=>$challenge ? app(\App\Services\MediaUrl::class)->url($challenge->hero_path,'hero') : null,
            'cities'=>City::orderBy('name')->get(['id','name']),
            'benefits'=>Benefit::where('partner_id',$partner->id)->where('access_mode','social_challenge_grant')->get(['id','title'])]);
    }
    public function save(Request $request, Partner $partner, ?Challenge $challenge=null) {
        $this->authorizePartner($request,$partner);
        if ($challenge) {
            $this->own($partner,$challenge);
            abort_unless(in_array($challenge->review_status,['DRAFT','CHANGES_REQUESTED'],true) && $challenge->status==='draft',403);
        }
        $request->merge(['partner_id'=>$partner->id,'status'=>'draft','slug'=>$challenge?->slug ?? Str::slug((string)$request->input('title')).'-'.Str::lower(Str::random(5))]);
        $request->attributes->set('partner_challenge',$partner->id);
        $request->attributes->set('partner_slug',$partner->slug);
        return app(AdminChallengeController::class)->save($request,$challenge);
    }
    public function submit(Request $request, Partner $partner, Challenge $challenge) {
        $this->authorizePartner($request,$partner); $this->own($partner,$challenge);
        if (!in_array($challenge->review_status,['DRAFT','CHANGES_REQUESTED'],true) || $challenge->status!=='draft') throw ValidationException::withMessages(['challenge'=>'Este reto no se puede enviar.']);
        $challenge->update(['review_status'=>'SUBMITTED','review_comment'=>null]);
        return to_route('partner.challenges.index',$partner);
    }
    public function preview(Request $request, Partner $partner, Challenge $challenge) {
        $this->authorizePartner($request,$partner); $this->own($partner,$challenge);
        return Inertia::render('social-challenges/show',['challenge'=>$challenge,'rewardLabel'=>$challenge->reward_type === 'BENEFIT' ? $challenge->benefit?->title : null,'heroUrl'=>app(\App\Services\MediaUrl::class)->url($challenge->hero_path,'hero'),'participation'=>null,'entries'=>[],'grant'=>null,'canParticipate'=>false,'canSubmit'=>false,'isMember'=>false,'preview'=>true,'verificationUrl'=>route('verification.notice')]);
    }
    public function image(Request $request, Partner $partner, Challenge $challenge) {
        $this->authorizePartner($request,$partner); $this->own($partner,$challenge);
        abort_unless(in_array($challenge->review_status,['DRAFT','CHANGES_REQUESTED'],true),403);
        return app(AdminChallengeController::class)->image($request,$challenge);
    }
}
