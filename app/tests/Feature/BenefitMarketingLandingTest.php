<?php
namespace Tests\Feature;

use App\Models\{Benefit, LandingPresentation, Location, Membership, Partner, User};
use App\Services\{LandingPresentationDefaults, ProductLandingResolver, RedemptionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BenefitMarketingLandingTest extends TestCase
{
    use RefreshDatabase;

    private function offer(array $attributes = []): array
    {
        $partner=Partner::factory()->published()->create(['name'=>'BAFABO']);
        $location=Location::factory()->published()->withPartner($partner)->create(['name'=>'Sucursal Centro','city'=>'Cochabamba','city_id'=>\App\Models\City::where('slug','cochabamba')->sole()->id,'address'=>'Calle Real']);
        $location->setRedemptionPin('123456'); $location->save();
        $benefit=Benefit::factory()->published()->forPartner($partner)->create($attributes+['title'=>'50% OFF','short_description'=>'Descuento en tu visita','terms'=>'Presenta tu código antes de pedir.','ends_at'=>now()->addDays(10)]);
        $benefit->syncLocations([$location]);
        $landing=$benefit->landingPresentations()->create(['name'=>'Oferta','slug'=>'benefit-campaign','status'=>'PUBLISHED','default_scope'=>'NONE','campaign_key'=>'benefit-creator']);
        return [$benefit,$landing,$location];
    }

    private function member(): User
    {
        $user=User::factory()->create();
        Membership::create(['user_id'=>$user->id,'status'=>'active','starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth(),'amount_paid'=>100]);
        return $user;
    }

    public function test_renderer_real_value_partner_places_conditions_contextual_faq_and_attribution(): void
    {
        [$benefit,$landing]=$this->offer(['image_path'=>'benefit-real.jpg','estimated_savings'=>999]);
        $this->get('/l/benefit-campaign?utm_source=qr')->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('landing-presentations/benefit')->where('copy.value','50% OFF')->where('copy.partner','BAFABO')
            ->where('copy.places.0.name','Sucursal Centro')->where('copy.places.0.city','Cochabamba')
            ->where('copy.rules.4','Presenta tu código antes de pedir.')->has('copy.faq',5)
            ->where('copy.action.label','QUIERO ESTE BENEFICIO')->where('copy.action.kind','guest')
            ->where('heroUrl',asset('storage/benefit-real.jpg'))->where('noindex',true)->where('canonical',route('benefits.show',$benefit))
            ->where('copy.finalHeadline','TU PRÓXIMO PLAN EMPIEZA AQUÍ.'));
        $this->assertDatabaseHas('attribution_touches',['campaign_key'=>'benefit-creator','utm_source'=>'qr']);
        $this->assertDatabaseCount('campaigns',0); $this->assertDatabaseCount('conversions',0);
        $this->post(route('redemptions.start',$benefit))->assertRedirect(route('register'));
        $this->assertSame('BENEFIT',session('public_journey.continuation.journey'));
    }

    public function test_editorial_overrides_never_replace_the_offer_and_hero_fallback(): void
    {
        [$benefit,$landing]=$this->offer(['image_path'=>'real.jpg']);
        $landing->update(['eyebrow'=>'SAL CON JAKAWI','headline'=>'TU PRÓXIMO PLAN','subheadline'=>'DISFRUTA TU VISITA','hero_path'=>'editorial.jpg','reward_display_override'=>'DISFRUTA EL MOMENTO','final_cta_headline'=>'ELIGE TU PRÓXIMO PLAN']);
        $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.headline','TU PRÓXIMO PLAN')->where('copy.value','50% OFF')->where('copy.valueNote','DISFRUTA EL MOMENTO')->where('heroUrl',asset('storage/editorial.jpg'))->where('copy.finalHeadline','ELIGE TU PRÓXIMO PLAN'));
        $landing->update(['headline'=>'90% OFF','reward_display_override'=>'100% GRATIS']);
        $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.headline','50% OFF')->where('copy.value','50% OFF')->where('copy.valueNote',null));
        $benefit->update(['ends_at'=>null,'redemption_limit_per_member'=>null,'title'=>'Postre de cortesía']);
        $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.value','Postre de cortesía')->where('copy.deadline',null)->has('copy.faq',3));
    }

    public function test_member_membership_expired_unavailable_and_redeemed_states(): void
    {
        [$benefit,$landing,$location]=$this->offer();
        $user=$this->member(); $this->actingAs($user);
        $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','USAR BENEFICIO')->where('copy.action.href',route('benefits.show',$benefit)));
        $redemption=app(RedemptionService::class)->start($user,$benefit,$location);
        app(RedemptionService::class)->confirm($redemption->code,'123456');
        $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.action.kind','unavailable')->where('copy.action.label','YA USASTE ESTE BENEFICIO SEGÚN SUS CONDICIONES'));
        $this->actingAs(User::factory()->create());
        $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','ACTIVAR MEMBRESÍA')->where('copy.action.kind','membership'));
        $benefit->update(['ends_at'=>now()->subDay()]);
        $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.action.kind','unavailable'));
        $benefit->update(['ends_at'=>null,'status'=>'paused']);
        $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.action.kind','unavailable'));
    }

    public function test_social_challenge_offer_is_not_public_even_with_published_all_landing(): void
    {
        [$benefit,$landing]=$this->offer(['access_mode'=>'social_challenge_grant']);
        app(LandingPresentationDefaults::class)->choose($benefit,$landing,'ALL');
        $this->get('/l/benefit-campaign')->assertNotFound();
        $this->actingAs($this->member())->get('/l/benefit-campaign')->assertNotFound();
        $this->assertDatabaseCount('attribution_touches',0);
        $this->get('/explorar?type=benefits')->assertOk()->assertInertia(fn(Assert $p)=>$p->has('opportunities',0));
    }

    public function test_entitled_challenge_winner_gets_existing_reward_path_without_membership_acquisition(): void
    {
        [$benefit]=$this->offer(['access_mode'=>'social_challenge_grant']);
        $user=User::factory()->create();
        $challenge=\App\Models\Challenge::create(['title'=>'Reto','slug'=>'reward-test','status'=>'open','review_status'=>'APPROVED',
            'description'=>'Participa','instructions'=>'Publica una foto','allowed_platforms'=>['instagram'],'required_hashtags'=>[],'required_mentions'=>[],
            'evidence_type'=>'SOCIAL_POST','qualification_type'=>'VALID_EVIDENCE','selection_type'=>'ALL_QUALIFIED','evaluation_mode'=>'CONTINUOUS',
            'review_mode'=>'AUTOMATIC','participation_eligibility'=>'ALL_USERS','reward_eligibility'=>'ALL_USERS','reward_type'=>'BENEFIT','benefit_id'=>$benefit->id]);
        $participation=\App\Models\ChallengeParticipation::create(['challenge_id'=>$challenge->id,'user_id'=>$user->id,'qualification_status'=>'qualified']);
        $grant=\App\Models\ChallengeRewardGrant::create(['challenge_id'=>$challenge->id,'participation_id'=>$participation->id,'user_id'=>$user->id,
            'reward_type'=>'BENEFIT','benefit_id'=>$benefit->id,'status'=>'granted','granted_at'=>now(),'snapshot'=>[]]);
        $this->actingAs($user)->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','VER MI PREMIO')->where('copy.action.href',route('social-challenges.show',$challenge))->where('copy.action.kind','native')->where('hasActiveMembership',false));
        $grant->update(['status'=>'fulfilled']);
        $this->get('/l/benefit-campaign')->assertNotFound();
    }

    public function test_audience_navigation_one_item_direct_native_and_seo(): void
    {
        [$benefit,$landing]=$this->offer();
        foreach(['NONE','GUESTS','ALL'] as $scope){
            app(LandingPresentationDefaults::class)->choose($benefit,$scope==='NONE'?null:$landing,$scope);
            foreach([false,true] as $authenticated){
                if($authenticated) $this->actingAs(User::factory()->create()); else auth()->forgetGuards();
                $url=$scope==='ALL'||($scope==='GUESTS'&&!$authenticated)?route('landing-presentations.show',$landing):route('benefits.show',$benefit);
                $this->assertSame($url,app(ProductLandingResolver::class)->navigationUrl($benefit,$authenticated));
                $this->get('/explorar?type=benefits')->assertOk()->assertInertia(fn(Assert $p)=>$p->has('opportunities',1)->where('opportunities.0.destination_url',$url));
                $this->get(route('benefits.show',$benefit))->assertOk()->assertInertia(fn(Assert $p)=>$p->component('benefits/show'));
                $this->get('/l/benefit-campaign')->assertOk()->assertInertia(fn(Assert $p)=>$p->component('landing-presentations/benefit')->where('noindex',$scope!=='ALL')->where('canonical',$scope==='ALL'?route('landing-presentations.show',$landing):route('benefits.show',$benefit)));
            }
        }
    }

    public function test_admin_reuses_management_create_edit_preview_publish_defaults_and_archive(): void
    {
        [$benefit]=$this->offer(); $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $base="/admin/beneficios/{$benefit->slug}/landings";
        $this->get($base)->assertOk()->assertInertia(fn(Assert $p)=>$p->component('admin/landing-presentations/index')->where('baseUrl',$base)->where('productUrl',route('admin.benefits.edit',$benefit)));
        $this->get(route('admin.benefits.edit',$benefit))->assertOk()->assertInertia(fn(Assert $p)=>$p->component('admin/resources/form')->where('item.slug',$benefit->slug));
        $this->post($base,['name'=>'Nueva','slug'=>'new-benefit'])->assertRedirect($base);
        $landing=LandingPresentation::where('slug','new-benefit')->firstOrFail();
        $this->put("$base/new-benefit",['name'=>'Editada','slug'=>'new-benefit','headline'=>'DISFRUTA TU VISITA'])->assertRedirect($base);
        $this->get("$base/new-benefit/preview")->assertOk()->assertInertia(fn(Assert $p)=>$p->component('landing-presentations/benefit')->where('preview',true));
        $this->get('/l/new-benefit')->assertNotFound();
        $this->post("$base/new-benefit/publish")->assertRedirect();
        foreach(['GUESTS','ALL'] as $scope){$this->post("$base/new-benefit/default",['default_scope'=>$scope])->assertRedirect();$this->assertSame($scope,$landing->fresh()->default_scope);}
        $this->post("$base/default-product")->assertRedirect(); $this->assertSame('NONE',$landing->fresh()->default_scope);
        $this->post("$base/new-benefit/archive")->assertRedirect(); $this->get('/l/new-benefit')->assertNotFound();
    }
}
