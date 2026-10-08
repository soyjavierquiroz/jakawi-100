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
        return $challenge->landingPresentations()->create(array_merge(['name'=>$slug,'slug'=>$slug,'status'=>'DRAFT','default_scope'=>'NONE'],$attributes));
    }

    public function test_multiple_presentations_atomic_default_and_native_fallback(): void
    {
        $challenge = $this->challenge();
        $resolver = app(ProductLandingResolver::class);
        $defaults = app(LandingPresentationDefaults::class);
        $first = $this->landing($challenge,'first',['status'=>'PUBLISHED']);
        $second = $this->landing($challenge,'second',['status'=>'PUBLISHED']);
        $this->assertSame(route('social-challenges.show',$challenge),$resolver->navigationUrl($challenge,false));
        $defaults->choose($challenge,$first,'ALL');
        $this->assertSame(route('landing-presentations.show',$first),$resolver->navigationUrl($challenge,false));
        $defaults->choose($challenge,$second,'ALL');
        $this->assertSame('NONE',$first->fresh()->default_scope);
        $this->assertSame('ALL',$second->fresh()->default_scope);
        $this->assertSame(1,$challenge->landingPresentations()->where('default_scope','<>','NONE')->count());
        $defaults->choose($challenge,null,'NONE');
        $this->assertSame(route('social-challenges.show',$challenge),$resolver->navigationUrl($challenge,false));
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
            try { app(LandingPresentationDefaults::class)->choose($challenge,$landing,'ALL'); $this->fail('Unpublished default accepted.'); }
            catch (\Illuminate\Validation\ValidationException) {}
        }
        $this->assertSame(0,$challenge->landingPresentations()->where('default_scope','<>','NONE')->count());
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
        app(LandingPresentationDefaults::class)->choose($challenge,$landing,'ALL');
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
        $this->post("/admin/retos/{$challenge->slug}/landings/{$landing->slug}/default",['default_scope'=>'ALL'])->assertRedirect();
        $this->assertSame('ALL',$landing->fresh()->default_scope);
        $this->post("/admin/retos/{$challenge->slug}/landings/default-product")->assertRedirect();
        $this->assertSame('NONE',$landing->fresh()->default_scope);
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
        $this->assertSame(route('benefits.show','beneficio'),$resolver->navigationUrl((new Benefit(['slug'=>'beneficio']))->forceFill(['id'=>100]),false));
        $this->assertSame(route('experiences.show','experiencia'),$resolver->navigationUrl((new Experience(['slug'=>'experiencia']))->forceFill(['id'=>101]),false));
        $this->assertSame(route('unlocks.show','desbloqueo'),$resolver->navigationUrl((new Unlock(['slug'=>'desbloqueo']))->forceFill(['id'=>102]),false));
    }

    public function test_audience_matrix_for_every_product_type(): void
    {
        $resolver=app(ProductLandingResolver::class);
        $defaults=app(LandingPresentationDefaults::class);
        $subjects=[Benefit::factory()->create(), Experience::factory()->create(),
            Unlock::create(['title'=>'Unlock','slug'=>'matrix-unlock','origin'=>'JAKAWI','type'=>'BENEFIT','minimum_commitments'=>2,'status'=>Unlock::ACTIVE]), $this->challenge()];
        foreach ($subjects as $i=>$subject) {
            $landing=LandingPresentation::create(['subject_type'=>$subject->getMorphClass(),'subject_id'=>$subject->id,'name'=>'Matrix','slug'=>'matrix-'.$i,'status'=>'PUBLISHED']);
            foreach (['NONE','GUESTS','ALL'] as $scope) {
                $defaults->choose($subject,$scope==='NONE'?null:$landing,$scope);
                foreach ([false,true] as $authenticated) {
                    $expected=$scope==='ALL'||($scope==='GUESTS'&&!$authenticated)?route('landing-presentations.show',$landing):$resolver->nativeUrl($subject);
                    $this->assertSame($expected,$resolver->navigationUrl($subject,$authenticated));
                }
            }
        }
    }

    public function test_guests_navigation_direct_urls_and_seo_do_not_leak_between_viewers(): void
    {
        $challenge=$this->challenge();
        $landing=$this->landing($challenge,'audience',['status'=>'PUBLISHED','campaign_key'=>'guest-campaign']);
        app(LandingPresentationDefaults::class)->choose($challenge,$landing,'GUESTS');
        foreach ([false,true,false] as $authenticated) {
            if ($authenticated) $this->actingAs(User::factory()->create());
            else auth()->forgetGuards();
            $expected=$authenticated?route('social-challenges.show',$challenge):route('landing-presentations.show',$landing);
            $this->get('/')->assertOk()->assertInertia(fn (Assert $page)=>$page->has('challenges',1)->where('challenges.0.destination_url',$expected)->where('discovery.hero.destination_url',$expected));
            foreach (['/explorar?type=challenges','/explorar?q=Reto&type=challenges'] as $url) {
                $this->get($url)->assertOk()->assertInertia(fn (Assert $page)=>$page->has('opportunities',1)->where('opportunities.0.destination_url',$expected));
            }
            $this->get('/l/audience')->assertOk()->assertInertia(fn (Assert $page)=>$page->component('landing-presentations/challenge')->where('noindex',true)->where('canonical',route('social-challenges.show',$challenge))->where('presentation.default_scope','GUESTS'));
            $this->get('/retos/'.$challenge->slug)->assertOk()->assertInertia(fn (Assert $page)=>$page->component('social-challenges/show'));
        }
        foreach (['NONE','ALL'] as $scope) {
            app(LandingPresentationDefaults::class)->choose($challenge,$scope==='NONE'?null:$landing,$scope);
            foreach ([false,true] as $authenticated) {
                if ($authenticated) $this->actingAs(User::factory()->create());
                else auth()->forgetGuards();
                $this->get('/l/audience')->assertOk()->assertInertia(fn (Assert $page)=>$page->component('landing-presentations/challenge')->where('noindex',$scope!=='ALL')->where('canonical',$scope==='ALL'?route('landing-presentations.show',$landing):route('social-challenges.show',$challenge)));
                $this->get('/retos/'.$challenge->slug)->assertOk()->assertInertia(fn (Assert $page)=>$page->component('social-challenges/show'));
            }
        }
        $this->assertDatabaseHas('attribution_touches',['campaign_key'=>'guest-campaign']);
        $this->assertDatabaseCount('conversions',0);
        $this->assertDatabaseCount('campaigns',0);
    }

    public function test_admin_visitors_everyone_archive_and_invalid_scope(): void
    {
        $challenge=$this->challenge();
        $landing=$this->landing($challenge,'admin-audience',['status'=>'PUBLISHED']);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $base="/admin/retos/{$challenge->slug}/landings";
        foreach (['GUESTS','ALL'] as $scope) {
            $this->post("$base/{$landing->slug}/default",['default_scope'=>$scope])->assertRedirect();
            $this->assertSame($scope,$landing->fresh()->default_scope);
        }
        $this->post("$base/{$landing->slug}/default",['default_scope'=>'INVALID'])->assertSessionHasErrors('default_scope');
        $this->post("$base/{$landing->slug}/archive")->assertRedirect();
        $this->assertSame('NONE',$landing->fresh()->default_scope);
        $this->assertSame('ARCHIVED',$landing->fresh()->status);
    }

    public function test_database_rejects_invalid_scopes_unpublished_defaults_and_duplicate_defaults(): void
    {
        $challenge=$this->challenge();
        $first=$this->landing($challenge,'constraint-first',['status'=>'PUBLISHED','default_scope'=>'GUESTS']);
        $second=$this->landing($challenge,'constraint-second',['status'=>'PUBLISHED']);
        foreach ([['default_scope'=>'ALL'],['default_scope'=>'INVALID']] as $attributes) {
            try { DB::transaction(fn ()=>$second->update($attributes)); $this->fail('Invalid default accepted.'); }
            catch (\Illuminate\Database\QueryException) {}
        }
        foreach (['DRAFT','ARCHIVED'] as $status) {
            foreach (['GUESTS','ALL'] as $scope) {
                try { DB::transaction(fn ()=>$second->update(['status'=>$status,'default_scope'=>$scope])); $this->fail('Unpublished default accepted.'); }
                catch (\Illuminate\Database\QueryException) {}
            }
        }
        try { app(LandingPresentationDefaults::class)->choose($challenge,$second->fresh(),'INVALID'); $this->fail('Invalid scope accepted.'); }
        catch (\Illuminate\Validation\ValidationException) {}
        $this->assertSame('GUESTS',$first->fresh()->default_scope);
    }
    public function test_incremental_migration_preserves_legacy_and_qa_non_default(): void
    {
        $challenge=$this->challenge();
        $qa=$this->landing($challenge,'qa-bafabo-marketing',['status'=>'PUBLISHED']);
        $legacy=$this->landing($challenge,'legacy-default',['status'=>'PUBLISHED']);
        $migration=require database_path('migrations/2026_10_08_000003_add_landing_default_scope.php');
        $migration->down();
        DB::table('landing_presentations')->where('id',$legacy->id)->update(['is_default'=>true]);
        $migration->up();
        $this->assertSame('NONE',$qa->fresh()->default_scope);
        $this->assertSame('PUBLISHED',$qa->fresh()->status);
        $this->assertSame('ALL',$legacy->fresh()->default_scope);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('landing_presentations','is_default'));
    }

    public function test_native_routes_for_other_products_ignore_all_default(): void
    {
        $subjects=[Benefit::factory()->published()->create(),Experience::factory()->published()->create(),
            Unlock::create(['title'=>'Native unlock','slug'=>'native-unlock','origin'=>'JAKAWI','type'=>'BENEFIT','minimum_commitments'=>2,'status'=>Unlock::ACTIVE])];
        foreach ($subjects as $i=>$subject) {
            $landing=LandingPresentation::create(['subject_type'=>$subject->getMorphClass(),'subject_id'=>$subject->id,'name'=>'Native','slug'=>'native-'.$i,'status'=>'PUBLISHED','default_scope'=>'ALL']);
            foreach ([false,true] as $authenticated) {
                if ($authenticated) $this->actingAs(User::factory()->create());
                else auth()->forgetGuards();
                $component=match (true) { $subject instanceof Benefit=>'benefits/show', $subject instanceof Experience=>'experiences/show', default=>'unlocks/show' };
                $this->get(app(ProductLandingResolver::class)->nativeUrl($subject))->assertOk()->assertInertia(fn (Assert $page)=>$page->component($component));
            }
        }
    }

}
