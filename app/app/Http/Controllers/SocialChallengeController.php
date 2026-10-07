<?php
namespace App\Http\Controllers;
use App\Jobs\RefreshSocialChallengeParticipation;
use App\Models\SocialChallenge;
use App\Models\SocialChallengeParticipation;
use App\Services\AnalyticsTracker;
use App\Services\PublicJourneyContinuation;
use App\Services\SelectedCity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class SocialChallengeController extends Controller {
    public function index(Request $request, SelectedCity $selectedCity) {
        $city=$selectedCity->resolve($request);
        return Inertia::render('social-challenges/index',['challenges'=>SocialChallenge::where('status','open')->where(fn($q)=>$q->whereNull('city_id')->orWhere('city_id',$city->id))->orderBy('ends_at')->get(['id','title','slug','description','ends_at','qualification_mode','reward_type'])]);
    }
    public function show(Request $request, SocialChallenge $challenge, AnalyticsTracker $analytics) {
        abort_unless(in_array($challenge->status,['open','closed'],true),404);
        $p=$request->user()?SocialChallengeParticipation::where('social_challenge_id',$challenge->id)->where('user_id',$request->user()->id)->with('grant')->first():null;
        $analytics->record('social_challenge_view',[],['challenge_id'=>$challenge->id]);
        $grant=$p?->grant;
        return Inertia::render('social-challenges/show',['challenge'=>$challenge->only(['id','slug','title','description','status','starts_at','ends_at','allowed_platforms','required_hashtags','required_mentions','qualification_mode','evaluation_mode','metric','target','winner_count','reward_type','manual_prize_description']),
            'participation'=>$p?->only(['id','social_url','platform','views','likes','comments','inspection_status','data_quality','validation_status','qualification_status','moderation_status','checked_at','sharecontest_payload','refresh_pending','last_refresh_requested_at','refresh_count_today','refresh_count_date']),
            'grant'=>$grant ? ['id'=>$grant->id,'status'=>$grant->status,'reward_type'=>$grant->reward_type,'benefit'=>$grant->benefit?->only(['title','slug']),'locations'=>$grant->benefit?->availableLocations()->get(['id','name'])] : null,
            'canParticipate'=>$challenge->acceptsParticipation(), 'canSubmit'=>$request->user()?->hasVerifiedEmail() ?? false,
            'verificationUrl'=>route('verification.notice')]);
    }
    public function intent(Request $request, SocialChallenge $challenge, PublicJourneyContinuation $continuation, AnalyticsTracker $analytics) {
        abort_unless($challenge->acceptsParticipation(),404);
        $analytics->record('social_challenge_intent_started',[],['challenge_id'=>$challenge->id]);
        if (!$request->user()) { $continuation->set('SOCIAL_CHALLENGE',$challenge->id,'PARTICIPATE'); return to_route('register'); }
        if (!$request->user()->hasVerifiedEmail()) return to_route('verification.notice');
        return redirect()->to(route('social-challenges.show',$challenge->slug).'#participar');
    }
    public function submit(Request $request, SocialChallenge $challenge, AnalyticsTracker $analytics) {
        $data=$request->validate(['url'=>['required','url','max:2048','starts_with:https://,http://']]);
        if (!$challenge->acceptsParticipation()) throw ValidationException::withMessages(['url'=>'Este reto ya no acepta participaciones.']);
        $url=$this->normalizeUrl($data['url']);
        if (SocialChallengeParticipation::where('social_challenge_id',$challenge->id)->where('user_id',$request->user()->id)->exists()) throw ValidationException::withMessages(['url'=>'Ya participaste en este reto.']);
        if (SocialChallengeParticipation::where('social_challenge_id',$challenge->id)->where('normalized_url_hash',hash('sha256',$url))->exists()) throw ValidationException::withMessages(['url'=>'Esta publicación ya participa en el reto.']);
        $p=SocialChallengeParticipation::create(['social_challenge_id'=>$challenge->id,'user_id'=>$request->user()->id,'social_url'=>$data['url'],'normalized_url_hash'=>hash('sha256',$url),'external_reference'=>'jakawi-social-'.Str::ulid(),'refresh_pending'=>true]);
        RefreshSocialChallengeParticipation::dispatch($p->id)->afterCommit();
        $analytics->record('social_participation_submitted',['user_id'=>$p->user_id],['challenge_id'=>$challenge->id,'qualification_mode'=>$challenge->qualification_mode]);
        return to_route('social-challenges.show',$challenge->slug)->with('success','Estamos verificando tu publicación.');
    }
    public function refresh(Request $request, SocialChallenge $challenge) {
        DB::transaction(function() use ($request,$challenge) {
            $p=SocialChallengeParticipation::where('social_challenge_id',$challenge->id)->where('user_id',$request->user()->id)->lockForUpdate()->firstOrFail();
            if ($p->refresh_pending) throw ValidationException::withMessages(['refresh'=>'Ya hay una verificación pendiente.']);
            if ($p->last_refresh_requested_at?->gt(now()->subMinutes(15))) throw ValidationException::withMessages(['refresh'=>'Puedes actualizar de nuevo en 15 minutos.']);
            $count=$p->refresh_count_date?->isToday() ? $p->refresh_count_today : 0;
            if ($count>=3) throw ValidationException::withMessages(['refresh'=>'Ya usaste tus 3 actualizaciones de hoy.']);
            $p->update(['refresh_pending'=>true,'last_refresh_requested_at'=>now(),'refresh_count_date'=>today(),'refresh_count_today'=>$count+1]);
            RefreshSocialChallengeParticipation::dispatch($p->id)->afterCommit();
        });
        return back()->with('success','Actualización solicitada.');
    }
    private function normalizeUrl(string $url): string {
        $parts=parse_url(trim($url));
        if (!$parts || !isset($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''),['http','https'],true)) throw ValidationException::withMessages(['url'=>'La URL no es válida.']);
        $host=strtolower(rtrim($parts['host'],'.')); $path=rtrim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);
        $query=array_diff_key($query,array_flip(['utm_source','utm_medium','utm_campaign','utm_content','utm_term','fbclid','igshid']));
        ksort($query);
        return $host.$path.($query ? '?'.http_build_query($query) : '');
    }
}
