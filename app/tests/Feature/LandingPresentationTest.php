<?php
namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Unlock;
use App\Models\User;
use App\Services\ChallengeMarketingLandingPresenter;
use App\Services\LandingPresentationDefaults;
use App\Services\ProductLandingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function challenge(array $attributes = []): Challenge
    {
        return Challenge::create(array_merge([
            'title'=>'Reto de prueba', 'slug'=>'reto-presentacion', 'status'=>'open', 'review_status'=>'APPROVED',
            'description'=>'Participa en el reto', 'instructions'=>'Publica una foto',
            'allowed_platforms'=>['instagram'], 'required_hashtags'=>[], 'required_mentions'=>[],
            'evidence_type'=>'SOCIAL_POST', 'qualification_type'=>'VALID_EVIDENCE', 'selection_type'=>'ALL_QUALIFIED',
            'evaluation_mode'=>'CONTINUOUS', 'review_mode'=>'AUTOMATIC', 'participation_eligibility'=>'ALL_USERS', 'reward_eligibility'=>'ALL_USERS',
            'reward_type'=>'JP', 'reward_jp_amount'=>25,
        ], $attributes));
    }

    private function landing(Challenge $challenge, string $slug, array $attributes = []): LandingPresentation
    {
        return $challenge->landingPresentations()->create(array_merge(['name'=>$slug,'slug'=>$slug,'status'=>'DRAFT','is_default'=>false],$attributes));
    }

    public function test_multiple_presentations_atomic_default_and_native_fallback(): void
    {
        $challenge = $this->challenge();
        $resolver = app(ProductLandingResolver::class);
        $defaults = app(LandingPresentationDefaults::class);
        $first = $this->landing($challenge,'first',['status'=>'PUBLISHED']);
        $second = $this->landing($challenge,'second',['status'=>'PUBLISHED']);
        $this->assertSame(route('social-challenges.show',$challenge),$resolver->defaultUrl($challenge));
        $defaults->choose($challenge,$first);
        $this->assertSame(route('landing-presentations.show',$first),$resolver->defaultUrl($challenge));
        $defaults->choose($challenge,$second);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1,$challenge->landingPresentations()->where('is_default',true)->count());
        $defaults->choose($challenge,null);
        $this->assertSame(route('social-challenges.show',$challenge),$resolver->defaultUrl($challenge));
        $this->assertCount(2,$challenge->landingPresentations);
    }

    public function test_draft_and_archived_cannot_be_default_or_public(): void
    {
        $challenge = $this->challenge();
        $draft = $this->landing($challenge,'draft');
        $archived = $this->landing($challenge,'archived',['status'=>'ARCHIVED']);
        $this->get('/l/draft')->assertNotFound();
        $this->get('/l/archived')->assertNotFound();
        foreach ([$draft,$archived] as $landing) {
            try { app(LandingPresentationDefaults::class)->choose($challenge,$landing); $this->fail('Unpublished default accepted.'); }
            catch (\Illuminate\Validation\ValidationException) {}
        }
        $this->assertSame(0,$challenge->landingPresentations()->where('is_default',true)->count());
    }

    public function test_published_non_default_is_public_and_captures_campaign_without_economics(): void
    {
        $challenge = $this->challenge();
        $landing = $this->landing($challenge,'acquisition',['status'=>'PUBLISHED','campaign_key'=>'october-creator']);
        $this->get('/l/acquisition?utm_source=creator')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('landing-presentations/challenge')->where('presentation.id',$landing->id)
            ->where('copy.reward','25 JP')->where('noindex',true)->where('copy.steps.0.title','Publica'));
        $this->assertDatabaseHas('attribution_touches',['landing_page'=>'/l/acquisition','campaign_key'=>'october-creator','utm_source'=>'creator']);
        $this->assertDatabaseHas('analytics_events',['event_name'=>'landing_view']);
        $this->assertSame(0,DB::table('conversions')->count());
        $this->get('/retos/'.$challenge->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->component('social-challenges/show'));
    }

    public function test_default_changes_challenge_navigation_without_duplicating_the_item(): void
    {
        $challenge=$this->challenge();
        $landing=$this->landing($challenge,'featured',['status'=>'PUBLISHED']);
        $this->get('/retos')->assertOk()->assertInertia(fn (Assert $page) => $page->component('social-challenges/index')->has('challenges',1)->where('challenges.0.destination_url',route('social-challenges.show',$challenge)));
        app(LandingPresentationDefaults::class)->choose($challenge,$landing);
        $this->get('/retos')->assertOk()->assertInertia(fn (Assert $page) => $page->component('social-challenges/index')->has('challenges',1)->where('challenges.0.destination_url',route('landing-presentations.show',$landing)));
        $this->get('/explorar?type=challenges')->assertOk()->assertInertia(fn (Assert $page) => $page->component('explore')->has('opportunities',1)->where('opportunities.0.destination_url',route('landing-presentations.show',$landing)));
        $this->get('/l/featured')->assertOk()->assertInertia(fn (Assert $page) => $page->component('landing-presentations/challenge')->where('noindex',false));
    }

    public function test_admin_can_preview_publish_and_restore_product_default(): void
    {
        $challenge=$this->challenge();
        $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->post("/admin/retos/{$challenge->slug}/landings",['name'=>'Lanzamiento','slug'=>'lanzamiento','headline'=>'DESCUBRE EL RETO'])->assertRedirect();
        $landing=LandingPresentation::where('slug','lanzamiento')->firstOrFail();
        $this->actingAs($admin)->get("/admin/retos/{$challenge->slug}/landings/{$landing->slug}/preview")->assertOk()->assertInertia(fn (Assert $page) => $page->component('landing-presentations/challenge')->where('preview',true));
        $this->get('/l/lanzamiento')->assertNotFound();
        $this->post("/admin/retos/{$challenge->slug}/landings/{$landing->slug}/publish")->assertRedirect();
        $this->post("/admin/retos/{$challenge->slug}/landings/{$landing->slug}/default")->assertRedirect();
        $this->assertTrue($landing->fresh()->is_default);
        $this->post("/admin/retos/{$challenge->slug}/landings/default-product")->assertRedirect();
        $this->assertFalse($landing->fresh()->is_default);
        $this->assertSame('PUBLISHED',$landing->fresh()->status);
    }

    public function test_landing_exposes_participant_state_and_immediate_verification_context(): void
    {
        $challenge=$this->challenge(['reward_eligibility'=>'ACTIVE_MEMBERS']);
        $landing=$this->landing($challenge,'participant',['status'=>'PUBLISHED']);
        $user=User::factory()->create();
        $participation=\App\Models\ChallengeParticipation::create(['challenge_id'=>$challenge->id,'user_id'=>$user->id,'qualification_status'=>'qualified']);
        $this->actingAs($user)->get('/l/participant')->assertOk()->assertInertia(fn (Assert $page) => $page->component('landing-presentations/challenge')->where('participation.id',$participation->id)->where('isMember',false)->where('challenge.reward_eligibility','ACTIVE_MEMBERS')->where('verificationUrl',route('verification.notice')));
    }

    public function test_presenter_uses_domain_values_for_top_first_and_manual(): void
    {
        $challenge=$this->challenge(['qualification_type'=>'METRIC_THRESHOLD','qualification_metric'=>'views','qualification_target'=>100,'selection_type'=>'TOP_N','selection_metric'=>'views','winner_limit'=>5,'evaluation_mode'=>'AT_CLOSE']);
        $landing=$this->landing($challenge,'top',['headline'=>'MUESTRA TU MEJOR PUBLICACIÓN','status'=>'PUBLISHED']);
        $copy=app(ChallengeMarketingLandingPresenter::class)->present($challenge,$landing);
        $this->assertSame('MUESTRA TU MEJOR PUBLICACIÓN',$copy['headline']);
        $this->assertStringContainsString('5',$copy['selection']);
        $this->assertStringContainsString('100',$copy['qualification']);
        $landing->update(['headline'=>'ENTRA AL TOP 10']);
        $this->assertSame('SUPERA 100 vistas. ENTRA AL TOP 5.',app(ChallengeMarketingLandingPresenter::class)->present($challenge,$landing->fresh())['headline']);
        $landing->update(['headline'=>'ENTRA AL TOP diez']);
        $this->assertSame('SUPERA 100 vistas. ENTRA AL TOP 5.',app(ChallengeMarketingLandingPresenter::class)->present($challenge,$landing->fresh())['headline']);
        $challenge->update(['selection_type'=>'FIRST_N']);
        $this->assertStringContainsString('5',app(ChallengeMarketingLandingPresenter::class)->present($challenge,$landing)['selection']);
        $challenge->update(['evidence_type'=>'MANUAL','qualification_type'=>'MANUAL','selection_type'=>'MANUAL','review_mode'=>'REQUIRED']);
        $manual=app(ChallengeMarketingLandingPresenter::class)->present($challenge,$landing);
        $this->assertSame('Participa',$manual['steps'][0]['title']);
        $this->assertStringNotContainsString('Instagram',implode(' ',array_column($manual['steps'],'description')));
    }

    public function test_other_product_native_urls_remain_available(): void
    {
        $resolver=app(ProductLandingResolver::class);
        $this->assertSame(route('benefits.show','beneficio'),$resolver->defaultUrl((new Benefit(['slug'=>'beneficio']))->forceFill(['id'=>100])));
        $this->assertSame(route('experiences.show','experiencia'),$resolver->defaultUrl((new Experience(['slug'=>'experiencia']))->forceFill(['id'=>101])));
        $this->assertSame(route('unlocks.show','desbloqueo'),$resolver->defaultUrl((new Unlock(['slug'=>'desbloqueo']))->forceFill(['id'=>102])));
    }
}
