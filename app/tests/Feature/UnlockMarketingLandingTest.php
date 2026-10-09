<?php
namespace Tests\Feature;

use App\Models\{AnalyticsEvent, City, JpHold, LandingPresentation, Location, Membership, Partner, RewardTransaction, Unlock, UnlockParticipation, User};
use App\Services\{LandingPresentationDefaults, ProductLandingResolver, PublicJourneyContinuation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnlockMarketingLandingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $attributes = []): array
    {
        $partner = Partner::factory()->published()->create(['name'=>'Partner real']);
        $location = Location::factory()->published()->withPartner($partner)->create(['name'=>'Lugar real', 'city'=>'Cochabamba', 'city_id'=>City::where('slug', 'cochabamba')->sole()->id]);
        $unlock = Unlock::create($attributes + ['title'=>'Cena compartida', 'slug'=>'cena-real', 'short_description'=>'Una cena especial para compartir.', 'description'=>'Menú de temporada para quienes confirmen.', 'terms'=>'Presenta tu confirmación.', 'hero_path'=>'unlock.jpg', 'partner_id'=>$partner->id, 'minimum_commitments'=>10, 'maximum_capacity'=>20, 'status'=>Unlock::ACTIVE, 'free_user_eligible'=>true, 'member_eligible'=>true, 'free_user_offer'=>'Menú de temporada', 'member_offer'=>'Menú con bebida', 'commitment_deadline'=>now()->addDays(3)]);
        $unlock->locations()->attach($location);
        $landing = $unlock->landingPresentations()->create(['name'=>'Cena marketing', 'slug'=>'unlock-plan', 'status'=>'PUBLISHED', 'default_scope'=>'NONE', 'campaign_key'=>'unlock-qr']);
        return [$unlock, $landing];
    }

    private function participate(Unlock $unlock, string $status = UnlockParticipation::COMMITTED, ?User $user = null): UnlockParticipation
    {
        return $unlock->participations()->create(['user_id'=>($user ?? User::factory()->create())->id, 'status'=>$status]);
    }

    public function test_renderer_real_reward_progress_editorial_conditions_faq_and_attribution(): void
    {
        [$unlock, $landing] = $this->plan();
        foreach ([UnlockParticipation::COMMITTED, UnlockParticipation::CONFIRMED, UnlockParticipation::FULFILLED] as $status) $this->participate($unlock, $status);
        $this->participate($unlock, UnlockParticipation::INTERESTED);
        $this->participate($unlock, UnlockParticipation::CANCELLED_ON_TIME);
        $landing->update(['eyebrow'=>'ENTRE TODOS', 'headline'=>'HAGAMOS QUE SUCEDA', 'subheadline'=>'Un plan para compartir', 'hero_path'=>'editorial.jpg', 'reward_display_override'=>'Disfruta algo especial', 'final_cta_headline'=>'VAMOS JUNTOS']);
        $this->get('/l/unlock-plan?utm_source=qr')->assertOk()->assertInertia(fn (Assert $p) => $p->component('landing-presentations/unlock')
            ->where('copy.title', $unlock->title)->where('copy.reward', $unlock->description)->where('copy.offers.1.value','Menú con bebida')
            ->where('copy.eyebrow','ENTRE TODOS')->where('copy.headline','HAGAMOS QUE SUCEDA')->where('copy.subheadline','Un plan para compartir')
            ->where('copy.valueNote','Disfruta algo especial')->where('copy.finalHeadline','VAMOS JUNTOS')->where('copy.heroUrl',asset('storage/editorial.jpg'))
            ->where('copy.progress.current',3)->where('copy.progress.target',10)->where('copy.progress.remaining',7)->where('copy.progress.percent',30)
            ->where('copy.partner.name','Partner real')->where('copy.locations.0.name','Lugar real')->where('copy.terms',$unlock->terms)
            ->has('copy.conditions',5)->has('copy.faq',7)->has('copy.steps',3)->has('copy.benefits',2)->where('copy.action.label','QUIERO SUMARME')
            ->where('presentation.campaign_key','unlock-qr')->where('copy.deadline',$unlock->commitment_deadline->timezone('America/La_Paz')->format('d/m/Y H:i').' (hora de Bolivia)'));
        $this->assertSame(1, AnalyticsEvent::where('event_name','unlock_viewed')->where('unlock_id',$unlock->id)->count());
        $landing->update(['headline'=>'Meta 1000', 'subheadline'=>'Sin garantía', 'reward_display_override'=>'Premio garantizado', 'final_cta_headline'=>'Ya desbloqueado', 'eyebrow'=>'Gratis']);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.headline',$unlock->title)->where('copy.subheadline',$unlock->short_description)->where('copy.valueNote',null)->where('copy.eyebrow','DESBLOQUEO JAKAWI')->where('copy.progress.target',10)->where('copy.progress.current',3));
        $this->assertDatabaseHas('attribution_touches',['campaign_key'=>'unlock-qr','utm_source'=>'qr']);
        $event = AnalyticsEvent::where('event_name','landing_view')->firstOrFail();
        foreach (['landing_presentation_id'=>$landing->id,'subject_type'=>$unlock->getMorphClass(),'subject_id'=>$unlock->id,'default_scope'=>'NONE','campaign_key'=>'unlock-qr'] as $key=>$value) $this->assertSame($value,$event->metadata[$key]);
        $this->assertDatabaseCount('campaigns',0); $this->assertDatabaseCount('conversions',0);
        $this->assertDatabaseCount('reward_transactions',0); $this->assertDatabaseCount('jp_holds',0);
    }

    public function test_guest_continuation_eligible_auth_membership_and_existing_participation(): void
    {
        [$unlock] = $this->plan();
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.kind','guest')->where('copy.action.href',route('unlocks.commit',$unlock)));
        $this->post(route('unlocks.commit',$unlock))->assertRedirect(route('register'));
        $this->assertSame(['journey'=>'UNLOCK','resource_id'=>$unlock->id,'action'=>'COMMIT'],app(PublicJourneyContinuation::class)->get());
        $this->assertDatabaseCount('unlock_participations',0);
        $user=User::factory()->create(); $this->actingAs($user);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.viewer.eligible',true)->where('copy.action.label','SUMARME')->where('copy.action.href',route('unlocks.show',$unlock)));
        $unlock->update(['free_user_eligible'=>false]);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.label','ACTIVAR MEMBRESÍA')->where('copy.action.href','/membresia?'.http_build_query(['journey'=>'UNLOCK','action'=>'COMMIT','resource_id'=>$unlock->id])));
        Membership::create(['user_id'=>$user->id,'status'=>'active','starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth(),'amount_paid'=>100]);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.viewer.eligible',true)->where('copy.action.label','SUMARME'));
        $participation = $this->participate($unlock,UnlockParticipation::COMMITTED,$user);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.label','VER MI PARTICIPACIÓN')->where('copy.viewer.participationStatus','COMMITTED'));
        foreach ([UnlockParticipation::UNLOCKED_PENDING_CONFIRMATION, UnlockParticipation::CONFIRMED, UnlockParticipation::FULFILLED, UnlockParticipation::REMOVED, UnlockParticipation::EXPIRED, UnlockParticipation::CANCELLED_ON_TIME, UnlockParticipation::NO_SHOW] as $status) {
            $participation->update(['status'=>$status]);
            $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.kind','native')->where('copy.action.label','VER MI PARTICIPACIÓN')->where('copy.viewer.participationStatus',$status));
        }
    }

    public function test_not_eligible_jp_guard_balance_deadline_and_capacity_disable_actions(): void
    {
        [$unlock] = $this->plan(['free_user_eligible'=>false,'member_eligible'=>false]);
        $this->actingAs(User::factory()->create());
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.label','NO ELEGIBLE')->where('copy.action.kind','unavailable')->where('copy.viewer.eligible',false));
        $this->post(route('unlocks.commit',$unlock))->assertSessionHasErrors('unlock');
        $unlock->update(['free_user_eligible'=>true,'member_eligible'=>false]);
        $member = User::factory()->create();
        Membership::create(['user_id'=>$member->id,'status'=>'active','starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth(),'amount_paid'=>100]);
        $this->actingAs($member);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.label','NO ELEGIBLE')->where('copy.viewer.hasActiveMembership',true));
        $this->post(route('unlocks.commit',$unlock))->assertSessionHasErrors('unlock');
        $unlock->update(['free_user_eligible'=>true,'member_eligible'=>true,'jp_deposit'=>20]);
        config()->set('unlocks.jp_commitments_enabled',false);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.kind','unavailable')->where('copy.stateLabel','COMPROMISOS CON JP NO DISPONIBLES'));
        $this->post(route('unlocks.commit',$unlock))->assertSessionHasErrors('unlock');
        config()->set('unlocks.jp_commitments_enabled',true);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.label','JP INSUFICIENTES')->where('copy.jpRequired',20));
        $this->post(route('unlocks.commit',$unlock))->assertSessionHasErrors('jp');
        $unlock->update(['jp_deposit'=>0,'commitment_deadline'=>now()->subMinute()]);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.expired',true)->where('copy.failed',false)->where('copy.action.kind','unavailable')->where('copy.stateLabel','PLAZO DE COMPROMISOS FINALIZADO'));
        $this->post(route('unlocks.commit',$unlock))->assertSessionHasErrors('unlock');
        $unlock->update(['commitment_deadline'=>null,'maximum_capacity'=>1]); $this->participate($unlock);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.action.kind','unavailable')->where('copy.stateLabel','CAPACIDAD COMPLETA'));
        $this->post(route('unlocks.commit',$unlock))->assertSessionHasErrors('unlock');
        $this->assertDatabaseCount('jp_holds',0); $this->assertDatabaseCount('reward_transactions',0);
    }

    public function test_success_uses_native_result_and_does_not_solicit_contribution(): void
    {
        [$unlock,$landing] = $this->plan(['status'=>Unlock::UNLOCKED,'goal_reached_at'=>now()]);
        $landing->update(['headline'=>'AYÚDANOS A LLEGAR','final_cta_headline'=>'SUMA TU APOYO']);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.success',true)->where('copy.headline',$unlock->title)->where('copy.stateLabel','YA ESTÁ DESBLOQUEADO')->where('copy.finalHeadline','LO LOGRAMOS.')->where('copy.action.label','VER LO DESBLOQUEADO')->where('copy.action.href',route('unlocks.show',$unlock))->has('copy.benefits',1));
        $this->post(route('unlocks.commit',$unlock))->assertRedirect(route('register'));
        $this->actingAs(User::factory()->create())->post(route('unlocks.commit',$unlock))->assertSessionHasErrors('unlock');
        $unlock->update(['status'=>Unlock::GOAL_REACHED]);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.success',true));
    }

    public function test_visibility_terminal_preview_and_secret_disclosures_cannot_be_bypassed(): void
    {
        [$unlock,$landing] = $this->plan();
        foreach ([Unlock::DRAFT, Unlock::PENDING_REVIEW, Unlock::APPROVED, Unlock::SCHEDULED, Unlock::REJECTED, Unlock::GOAL_NOT_REACHED, Unlock::CANCELLED, Unlock::FULFILLMENT_ACTIVE, Unlock::COMPLETED] as $status) {
            $unlock->update(['status'=>$status]);
            $this->get('/l/unlock-plan')->assertNotFound(); $this->get(route('unlocks.show',$unlock))->assertNotFound();
        }
        $admin = User::factory()->create(['is_admin'=>true]); $this->actingAs($admin);
        foreach ([Unlock::GOAL_NOT_REACHED=>'NO SE ALCANZÓ LA META',Unlock::CANCELLED=>'DESBLOQUEO CANCELADO'] as $status=>$label) {
            $unlock->update(['status'=>$status,'jp_deposit'=>20]);
            $this->get('/admin/desbloqueos/cena-real/landings/unlock-plan/preview')->assertOk()->assertInertia(fn (Assert $p) => $p->where('preview',true)->where('noindex',true)->where('copy.failed',true)->where('copy.stateLabel',$label)->where('copy.action.kind','unavailable')->where('copy.conditions.2','Si el desbloqueo termina sin alcanzar la meta o se cancela, no se activa la propuesta. Los JP reservados se liberan.'));
        }
        $unlock->update(['status'=>Unlock::ACTIVE,'jp_deposit'=>0,'secret_mode'=>true,'hide_exact_offer_until_unlock'=>true,'hide_partner_until_unlock'=>true,'hide_location_until_unlock'=>true]);
        $landing->update(['headline'=>'OFERTA SECRETA REVELADA','hero_path'=>'spoiler.jpg','hero_alt'=>'Oferta oculta','reward_display_override'=>'Oferta oculta']);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.description',null)->has('copy.offers',0)->where('copy.partner',null)->has('copy.locations',0)->where('copy.heroUrl',null)->where('copy.heroAlt',$unlock->title)->where('copy.headline',$unlock->title)->where('copy.valueNote',null));
        $unlock->update(['status'=>Unlock::UNLOCKED,'goal_reached_at'=>now()]);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.reward',$unlock->description)->has('copy.offers',2)->where('copy.partner.name','Partner real')->has('copy.locations',1));
    }

    public function test_guarantee_copy_matches_release_and_forfeit_semantics_without_mutation(): void
    {
        [$unlock] = $this->plan(['jp_deposit'=>50,'jp_completion_bonus'=>10,'confirmation_deadline'=>now()->addDays(4),'cancellation_deadline'=>now()->addDays(2),'fulfillment_starts_at'=>now()->addDays(5),'fulfillment_ends_at'=>now()->addDays(6)]);
        $this->get('/l/unlock-plan')->assertOk()->assertInertia(function (Assert $p) {
            $p->where('copy.jpRequired',50)->has('copy.faq',8)->has('copy.benefits',3);
            $p->where('copy.guarantee',fn ($text) => str_contains($text,'50 JP quedan reservados') && str_contains($text,'Alcanzar la meta no libera') && str_contains($text,'Se libera al cumplir') && str_contains($text,'Si confirmas y no cumples') && str_contains($text,'se descuentan') && str_contains($text,'Puedes cancelar hasta'));
        });
        $this->assertSame(0,JpHold::count()); $this->assertSame(0,RewardTransaction::count());
    }

    public function test_progress_zero_target_over_target_and_media_fallback_without_fake_deadline(): void
    {
        [$unlock] = $this->plan(['minimum_commitments'=>0,'commitment_deadline'=>null,'partner_id'=>null,'hero_path'=>null]);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.progress.percent',null)->where('copy.progress.remaining',0)->where('copy.deadline',null)->where('copy.heroUrl',null)->where('copy.partner',null));
        $unlock->update(['minimum_commitments'=>1,'hero_path'=>'real.jpg']);
        $this->participate($unlock); $this->participate($unlock);
        $this->get('/l/unlock-plan')->assertInertia(fn (Assert $p) => $p->where('copy.progress.current',2)->where('copy.progress.target',1)->where('copy.progress.percent',200)->where('copy.progress.remaining',0)->where('copy.heroUrl',asset('storage/real.jpg')));
    }

    public function test_defaults_discovery_unique_direct_routes_and_seo(): void
    {
        [$unlock,$landing] = $this->plan();
        foreach (['NONE','GUESTS','ALL'] as $scope) {
            app(LandingPresentationDefaults::class)->choose($unlock,$scope==='NONE'?null:$landing,$scope);
            foreach ([false,true] as $authenticated) {
                if ($authenticated) $this->actingAs(User::factory()->create()); else auth()->forgetGuards();
                $url = $scope==='ALL' || ($scope==='GUESTS' && !$authenticated) ? route('landing-presentations.show',$landing) : route('unlocks.show',$unlock);
                $this->assertSame($url,app(ProductLandingResolver::class)->navigationUrl($unlock,$authenticated));
                $this->get('/explorar?type=unlocks')->assertOk()->assertInertia(fn (Assert $p) => $p->has('opportunities',1)->where('opportunities.0.destination_url',$url));
                $this->get(route('unlocks.show',$unlock))->assertOk()->assertInertia(fn (Assert $p) => $p->component('unlocks/show'));
                $this->get('/l/unlock-plan')->assertOk()->assertInertia(fn (Assert $p) => $p->component('landing-presentations/unlock')->where('noindex',$scope!=='ALL')->where('canonical',$scope==='ALL'?route('landing-presentations.show',$landing):route('unlocks.show',$unlock)));
            }
        }
    }

    public function test_generic_admin_create_edit_preview_publish_defaults_archive_and_subject_guards(): void
    {
        [$unlock] = $this->plan(); $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $base='/admin/desbloqueos/cena-real/landings';
        $this->get($base)->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/landing-presentations/index')->where('productUrl',route('admin.unlocks.edit',$unlock)));
        $this->post($base,['name'=>'Nueva','slug'=>'new-unlock'])->assertRedirect($base);
        $landing=LandingPresentation::where('slug','new-unlock')->sole();
        $this->put("$base/new-unlock",['name'=>'Editada','slug'=>'new-unlock','headline'=>'VAMOS JUNTOS','minimum_commitments'=>999,'jp_deposit'=>999])->assertRedirect($base);
        $this->assertSame(10,$unlock->fresh()->minimum_commitments); $this->assertSame(0,$unlock->fresh()->jp_deposit);
        $this->get("$base/new-unlock/preview")->assertOk()->assertInertia(fn (Assert $p) => $p->where('preview',true)->where('noindex',true)->where('copy.headline','VAMOS JUNTOS'));
        $this->get('/l/new-unlock')->assertNotFound();
        $this->post("$base/new-unlock/publish")->assertRedirect();
        foreach (['GUESTS','ALL'] as $scope) { $this->post("$base/new-unlock/default",['default_scope'=>$scope])->assertRedirect(); $this->assertSame($scope,$landing->fresh()->default_scope); }
        $this->post("$base/default-product")->assertRedirect(); $this->assertSame('NONE',$landing->fresh()->default_scope);
        $other=Unlock::create(['title'=>'Otro','slug'=>'other','minimum_commitments'=>2]);
        $this->get('/admin/desbloqueos/other/landings/new-unlock/preview')->assertNotFound();
        $this->post('/admin/desbloqueos/other/landings/new-unlock/publish')->assertNotFound();
        $unlock->update(['status'=>Unlock::DRAFT]);
        $this->post("$base/new-unlock/publish")->assertSessionHasErrors('presentation');
        $this->post("$base/new-unlock/archive")->assertRedirect(); $this->get('/l/new-unlock')->assertNotFound();
        $this->actingAs(User::factory()->create())->get($base)->assertForbidden();
    }
}
