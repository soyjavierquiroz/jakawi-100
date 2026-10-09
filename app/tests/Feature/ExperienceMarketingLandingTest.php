<?php
namespace Tests\Feature;

use App\Models\{Experience, ExperienceSession, LandingPresentation, Location, Membership, Partner, User};
use App\Services\{LandingPresentationDefaults, ProductLandingResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExperienceMarketingLandingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $attributes=[]): array
    {
        $partner=Partner::factory()->published()->create(['name'=>'Taller real']);
        $location=Location::factory()->published()->withPartner($partner)->create(['name'=>'Sala Centro','address'=>'Calle Real','city'=>'Cochabamba','city_id'=>\App\Models\City::where('slug','cochabamba')->sole()->id]);
        $experience=Experience::factory()->published()->create($attributes+['title'=>'Cerámica compartida','description'=>'Modela una pieza con arcilla.','terms'=>'Lleva ropa cómoda.','experience_type'=>'event','reservation_method'=>'jakawi','regular_price'=>120,'member_price'=>80,'currency'=>'BOB','cover_path'=>'real.jpg']);
        $experience->syncPartnersWithRoles([['partner_id'=>$partner->id,'role'=>'organizer']]);
        $session=ExperienceSession::factory()->for($experience)->upcoming()->withLocation($location)->create(['reservation_partner_id'=>$partner->id]);
        $landing=$experience->landingPresentations()->create(['name'=>'Plan','slug'=>'experience-plan','status'=>'PUBLISHED','default_scope'=>'NONE','campaign_key'=>'experience-qr']);
        return [$experience,$landing,$session];
    }

    public function test_renderer_facts_editorial_conditions_faq_and_campaign_isolation(): void
    {
        [$experience,$landing,$session]=$this->plan();
        $landing->update(['headline'=>'VIVE ALGO DISTINTO','hero_path'=>'editorial.jpg','final_cta_headline'=>'ELIGE TU PRÓXIMO PLAN']);
        $this->get('/l/experience-plan?utm_source=qr')->assertOk()->assertInertia(fn(Assert $p)=>$p->component('landing-presentations/experience')
            ->where('copy.title',$experience->title)->where('copy.headline','VIVE ALGO DISTINTO')->where('copy.description',$experience->description)
            ->where('copy.organizers.0.name','Taller real')->where('copy.next.place','Sala Centro')->where('copy.next.address','Calle Real')
            ->where('copy.next.date',$session->starts_at->timezone('America/La_Paz')->format('d/m/Y'))->where('copy.prices.0.value','BOB 120.00')
            ->where('copy.prices.1.value','BOB 80.00')->where('copy.terms','Lleva ropa cómoda.')->has('copy.faq',4)
            ->where('copy.finalHeadline','ELIGE TU PRÓXIMO PLAN')->where('copy.action.kind','guest')->where('heroUrl',asset('storage/editorial.jpg')));
        $landing->update(['headline'=>'GRATIS el viernes en otra calle','reward_display_override'=>'Precio 10','subheadline'=>'En otro lugar']);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.headline',$experience->title)->where('copy.valueNote',null)->where('copy.next.place','Sala Centro')->where('copy.prices.0.value','BOB 120.00'));
        $this->assertDatabaseHas('attribution_touches',['campaign_key'=>'experience-qr','utm_source'=>'qr']);
        $this->assertDatabaseCount('campaigns',0);$this->assertDatabaseCount('conversions',0);
    }

    public function test_guest_membership_member_multiple_sessions_and_existing_booking(): void
    {
        [$experience,$landing,$session]=$this->plan();
        $this->post(route('experiences.reservations.store',$experience),['experience_session_id'=>$session->id])->assertRedirect(route('register'));
        $this->assertSame('EXPERIENCE',session('public_journey.continuation.journey'));
        $user=User::factory()->create();$this->actingAs($user);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','ACTIVAR MEMBRESÍA')->where('copy.action.href','/membresia?'.http_build_query(['journey'=>'EXPERIENCE','action'=>'RESERVE','resource_id'=>$experience->id,'experience_session_id'=>$session->id])));
        Membership::create(['user_id'=>$user->id,'status'=>'active','starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth(),'amount_paid'=>100]);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','RESERVAR')->where('copy.action.href',route('experiences.show',$experience)));
        $this->post(route('experiences.reservations.store',$experience),['experience_session_id'=>$session->id])->assertRedirect();
        $this->assertDatabaseHas('experience_reservations',['user_id'=>$user->id,'experience_session_id'=>$session->id,'status'=>'pending']);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','VER MI RESERVA')->where('copy.viewer.reservationStatus','pending'));
        ExperienceSession::factory()->for($experience)->upcoming()->create();
        $this->actingAs(User::factory()->create());
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.sessionCount',2)->where('copy.action.href',route('experiences.show',$experience))->where('copy.action.kind','native'));
    }

    public function test_price_missing_free_external_unavailable_past_and_media_fallback(): void
    {
        [$experience,$landing,$session]=$this->plan(['regular_price'=>0,'member_price'=>null]);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.prices.0.value','GRATIS')->has('copy.prices',1)->where('heroUrl',asset('storage/real.jpg')));
        $experience->update(['regular_price'=>null,'reservation_method'=>'external','reservation_url'=>'https://example.com/book']);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->has('copy.prices',0)->where('copy.action.kind','native'));
        $experience->update(['reservation_url'=>null]);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','RESERVA NO DISPONIBLE'));
        $session->update(['starts_at'=>now()->subDay()]);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','FINALIZADA'));
        $session->update(['status'=>'cancelled']);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','NO HAY PRÓXIMAS FECHAS'));
        $experience->update(['status'=>'paused']);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('copy.action.label','NO DISPONIBLE'));
    }

    public function test_defaults_discovery_unique_native_marketing_and_seo(): void
    {
        [$experience,$landing]=$this->plan();
        foreach(['NONE','GUESTS','ALL'] as $scope){
            app(LandingPresentationDefaults::class)->choose($experience,$scope==='NONE'?null:$landing,$scope);
            foreach([false,true] as $authenticated){
                if($authenticated)$this->actingAs(User::factory()->create());else auth()->forgetGuards();
                $url=$scope==='ALL'||($scope==='GUESTS'&&!$authenticated)?route('landing-presentations.show',$landing):route('experiences.show',$experience);
                $this->assertSame($url,app(ProductLandingResolver::class)->navigationUrl($experience,$authenticated));
                $this->get('/explorar?type=experiences')->assertOk()->assertInertia(fn(Assert $p)=>$p->has('opportunities',1)->where('opportunities.0.destination_url',$url));
                $this->get(route('experiences.show',$experience))->assertOk()->assertInertia(fn(Assert $p)=>$p->component('experiences/show'));
                $this->get('/l/experience-plan')->assertOk()->assertInertia(fn(Assert $p)=>$p->component('landing-presentations/experience')->where('noindex',$scope!=='ALL')->where('canonical',$scope==='ALL'?route('landing-presentations.show',$landing):route('experiences.show',$experience)));
            }
        }
    }

    public function test_other_types_compact_sessions_and_no_invented_capacity_claims(): void
    {
        [$experience,$landing,$session]=$this->plan(['experience_type'=>'tour','cover_path'=>null,'image_path'=>'image.jpg']);
        $session->update(['capacity'=>1]);
        for($i=0;$i<7;$i++) ExperienceSession::factory()->for($experience)->create(['starts_at'=>now()->addDays($i+2)]);
        $this->get('/l/experience-plan')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('copy.experienceType','tour')->where('copy.sessionCount',8)->has('copy.sessions',6)->where('copy.action.href',route('experiences.show',$experience))->where('copy.action.kind','native')->where('heroUrl',asset('storage/image.jpg')));
        $experience->update(['image_path'=>null]);
        $this->get('/l/experience-plan')->assertInertia(fn(Assert $p)=>$p->where('heroUrl',null));
    }

    public function test_generic_admin_lifecycle_and_preview_subject_guard(): void
    {
        [$experience]=$this->plan();$this->actingAs(User::factory()->create(['is_admin'=>true]));
        $base="/admin/experiencias/{$experience->slug}/landings";
        $this->get($base)->assertOk()->assertInertia(fn(Assert $p)=>$p->component('admin/landing-presentations/index')->where('productUrl',route('admin.experiences.edit',$experience)));
        $this->post($base,['name'=>'Nueva','slug'=>'new-experience'])->assertRedirect($base);
        $landing=LandingPresentation::where('slug','new-experience')->sole();
        $this->put("$base/new-experience",['name'=>'Editada','slug'=>'new-experience','headline'=>'VIVE ALGO DISTINTO'])->assertRedirect($base);
        $this->get("$base/new-experience/preview")->assertOk()->assertInertia(fn(Assert $p)=>$p->where('preview',true)->where('noindex',true));
        $other=Experience::factory()->create();$this->get("/admin/experiencias/{$other->slug}/landings/new-experience/preview")->assertNotFound();
        $this->get('/l/new-experience')->assertNotFound();
        $this->post("$base/new-experience/publish")->assertRedirect();
        foreach(['GUESTS','ALL'] as $scope){$this->post("$base/new-experience/default",['default_scope'=>$scope])->assertRedirect();$this->assertSame($scope,$landing->fresh()->default_scope);}
        $this->post("$base/default-product")->assertRedirect();$this->assertSame('NONE',$landing->fresh()->default_scope);
        $this->post("$base/new-experience/archive")->assertRedirect();$this->get('/l/new-experience')->assertNotFound();
    }
}
