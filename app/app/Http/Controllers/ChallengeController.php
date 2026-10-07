<?php
namespace App\Http\Controllers;

use App\Jobs\RefreshChallengeSocialEntry;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ChallengeSocialEntry;
use App\Services\AnalyticsTracker;
use App\Services\ChallengeService;
use App\Services\PublicJourneyContinuation;
use App\Services\SelectedCity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChallengeController extends Controller {
    public function index(Request $request, SelectedCity $selectedCity) {
        $city = $selectedCity->resolve($request);
        return Inertia::render('social-challenges/index', ['challenges'=>Challenge::where('status','open')->where(fn ($q) => $q->whereNull('city_id')->orWhere('city_id',$city->id))->orderBy('ends_at')->get(['id','title','slug','description','ends_at','evidence_type','qualification_type','reward_type'])]);
    }
    public function show(Request $request, Challenge $challenge, AnalyticsTracker $analytics) {
        abort_unless(in_array($challenge->status, ['open','closed'], true), 404);
        $p = $request->user() ? ChallengeParticipation::where('challenge_id',$challenge->id)->where('user_id',$request->user()->id)->with(['socialEntries','grant.jpCredit.reversal'])->first() : null;
        $analytics->record('challenge_view', [], ['challenge_id'=>$challenge->id]);
        $grant = $p?->grant;
        return Inertia::render('social-challenges/show', [
            'challenge'=>$challenge, 'rules'=>$challenge->rulesContract(),
            'participation'=>$p?->only(['id','qualification_status','qualified_entry_id','qualified_at','selection_status','selected_entry_id','review_status']),
            'entries'=>$p?->socialEntries->map(fn ($e) => $e->only(['id','social_url','platform','views','likes','comments','inspection_status','data_quality','validation_status','moderation_status','checked_at','sharecontest_payload','refresh_pending']))->all() ?? [],
            'grant'=>$grant ? ['id'=>$grant->id,'status'=>$grant->status,'reward_type'=>$grant->reward_type,'jp_amount'=>$grant->jp_amount,
                'jp_reversed'=>$grant->reward_type === 'JP' && $grant->jpCredit?->reversal !== null,
                'benefit'=>$grant->benefit?->only(['title','slug']), 'locations'=>$grant->benefit?->availableLocations()->get(['id','name'])] : null,
            'canParticipate'=>$challenge->acceptsParticipation(), 'canSubmit'=>($request->user()?->hasVerifiedEmail() ?? false) && ($challenge->participation_eligibility === 'ALL_USERS' || ($request->user()?->hasActiveMembership() ?? false)),
            'verificationUrl'=>route('verification.notice'),
        ]);
    }
    public function intent(Request $request, Challenge $challenge, PublicJourneyContinuation $continuation, AnalyticsTracker $analytics, ChallengeService $service) {
        abort_unless($challenge->acceptsParticipation(), 404);
        $analytics->record('challenge_intent_started', [], ['challenge_id'=>$challenge->id]);
        if (!$request->user()) { $continuation->set('SOCIAL_CHALLENGE',$challenge->id,'PARTICIPATE'); return to_route('register'); }
        if (!$request->user()->hasVerifiedEmail()) return to_route('verification.notice');
        $this->assertParticipationEligible($challenge, $request);
        if ($challenge->evidence_type === 'MANUAL') {
            $p = ChallengeParticipation::firstOrCreate(['challenge_id'=>$challenge->id,'user_id'=>$request->user()->id]);
            $service->evaluate($p);
        }
        return redirect()->to(route('social-challenges.show',$challenge->slug).'#participar');
    }
    public function submit(Request $request, Challenge $challenge, AnalyticsTracker $analytics) {
        $data = $request->validate(['url'=>['required','url','max:2048','starts_with:https://,http://']]);
        if ($challenge->evidence_type !== 'SOCIAL_POST' || !$challenge->acceptsParticipation()) throw ValidationException::withMessages(['url'=>'Este reto no acepta publicaciones.']);
        $this->assertParticipationEligible($challenge, $request);
        $hash = hash('sha256', $this->normalizeUrl($data['url']));
        try {
            $entry = DB::transaction(function () use ($challenge, $request, $data, $hash) {
                $c = Challenge::query()->lockForUpdate()->findOrFail($challenge->id);
                $p = ChallengeParticipation::firstOrCreate(['challenge_id'=>$c->id,'user_id'=>$request->user()->id]);
                if ($c->max_entries_per_user !== null && $p->socialEntries()->count() >= $c->max_entries_per_user) throw ValidationException::withMessages(['url'=>'Alcanzaste el máximo de publicaciones de este reto.']);
                if ($c->socialEntries()->where('normalized_url_hash',$hash)->exists()) throw ValidationException::withMessages(['url'=>'Esta publicación ya participa en el reto.']);
                $entry = ChallengeSocialEntry::create(['challenge_id'=>$c->id,'participation_id'=>$p->id,'social_url'=>$data['url'],
                    'normalized_url_hash'=>$hash,'external_reference'=>'jakawi-social-'.Str::ulid(),'refresh_pending'=>true]);
                RefreshChallengeSocialEntry::dispatch($entry->id)->afterCommit();
                return $entry;
            });
        } catch (UniqueConstraintViolationException) { throw ValidationException::withMessages(['url'=>'Esta publicación ya participa en el reto.']); }
        $analytics->record('challenge_social_entry_submitted',['user_id'=>$request->user()->id],['challenge_id'=>$challenge->id,'entry_id'=>$entry->id]);
        return to_route('social-challenges.show',$challenge->slug)->with('success','Estamos verificando tu publicación.');
    }
    public function refresh(Request $request, Challenge $challenge) {
        if ($challenge->status !== 'open' || $challenge->evidence_type !== 'SOCIAL_POST') throw ValidationException::withMessages(['refresh'=>'Este reto ya no acepta actualizaciones.']);
        $data = $request->validate(['entry_id'=>['nullable','integer']]);
        DB::transaction(function () use ($request,$challenge,$data) {
            $p = ChallengeParticipation::where('challenge_id',$challenge->id)->where('user_id',$request->user()->id)->firstOrFail();
            $e = $p->socialEntries()->when(isset($data['entry_id']),fn ($q) => $q->whereKey($data['entry_id']))->latest('id')->lockForUpdate()->firstOrFail();
            if ($e->refresh_pending) throw ValidationException::withMessages(['refresh'=>'Ya hay una verificación pendiente.']);
            if ($e->last_refresh_requested_at?->gt(now()->subMinutes(15))) throw ValidationException::withMessages(['refresh'=>'Puedes actualizar de nuevo en 15 minutos.']);
            $count = $e->refresh_count_date?->isToday() ? $e->refresh_count_today : 0;
            if ($count >= 3) throw ValidationException::withMessages(['refresh'=>'Ya usaste tus 3 actualizaciones de hoy.']);
            $e->update(['refresh_pending'=>true,'last_refresh_requested_at'=>now(),'refresh_count_date'=>today(),'refresh_count_today'=>$count+1]);
            RefreshChallengeSocialEntry::dispatch($e->id)->afterCommit();
        });
        return back()->with('success','Actualización solicitada.');
    }
    private function assertParticipationEligible(Challenge $challenge, Request $request): void {
        if ($challenge->participation_eligibility === 'ACTIVE_MEMBERS' && !$request->user()->hasActiveMembership()) throw ValidationException::withMessages(['participation'=>'Este reto requiere membresía activa para participar.']);
    }
    private function normalizeUrl(string $url): string {
        $parts = parse_url(trim($url));
        if (!$parts || !isset($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''),['http','https'],true)) throw ValidationException::withMessages(['url'=>'La URL no es válida.']);
        $host = strtolower(rtrim($parts['host'],'.')); $path = rtrim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);
        $query = array_diff_key($query,array_flip(['utm_source','utm_medium','utm_campaign','utm_content','utm_term','fbclid','igshid']));
        ksort($query);
        return $host.$path.($query ? '?'.http_build_query($query) : '');
    }
}
