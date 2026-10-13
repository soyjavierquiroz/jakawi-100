<?php
namespace Tests\Feature;

use App\Models\{Benefit, Challenge, Experience, ExperienceSession, LandingPresentation, Location, Partner, User};
use App\Services\PublicJourneyContinuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChallengeDirectJoinTest extends TestCase
{
    use RefreshDatabase;
    private function challenge(array $overrides = []): Challenge
    {
        return Challenge::create(array_replace([
            'title' => 'Reto Cena de Ciudad — DEMO', 'slug' => 'delivery-cena',
            'partner_id' => Partner::factory()->published()->create(['name' => 'Brasa Prisma — DEMO'])->id,
            'status' => 'open', 'review_status' => 'APPROVED', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
            'description' => 'Comparte tu plan gastronómico.', 'instructions' => 'Publica una foto original de tu cena y mantén la publicación pública.',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => ['#jakawidemocena'], 'required_mentions' => [],
            'evidence_type' => 'SOCIAL_POST', 'qualification_type' => 'METRIC_THRESHOLD', 'qualification_metric' => 'likes', 'qualification_target' => 50,
            'selection_type' => 'FIRST_N', 'winner_limit' => 10, 'review_mode' => 'REQUIRED', 'evaluation_mode' => 'CONTINUOUS',
            'participation_eligibility' => 'ALL_USERS', 'reward_eligibility' => 'ALL_USERS', 'max_entries_per_user' => 1,
            'reward_type' => 'MANUAL_PRIZE', 'manual_prize_description' => 'Cena para dos — premio DEMO.',
        ], $overrides));
    }

    private function landing(Challenge $challenge): LandingPresentation
    {
        return $challenge->landingPresentations()->create(['name' => 'Delivery QA', 'slug' => 'delivery-'.$challenge->slug, 'status' => 'PUBLISHED', 'default_scope' => 'ALL']);
    }


    public function test_authenticated_explicit_landing_action_joins_directly_and_is_idempotent(): void
    {
        $c=$this->challenge();$l=$this->landing($c);$u=User::factory()->unverified()->create();
        $this->actingAs($u)->get(route('landing-presentations.show',$l))->assertInertia(fn(Assert $p)=>$p->where('landingDirectAction.endpoint',route('social-challenges.intent',$c,false))->where('landingDirectAction.data.landing_presentation_slug',$l->slug));
        $this->assertDatabaseCount('challenge_participations',0);
        for($i=0;$i<2;$i++)$this->post(route('social-challenges.intent',$c),['landing_presentation_slug'=>$l->slug])->assertRedirect(route('landing-presentations.result',$l).'#participar');
        $this->assertDatabaseCount('challenge_participations',1);
        $this->get(route('landing-presentations.result',$l))->assertInertia(fn(Assert $p)=>$p->where('conversionStage','result')->has('participation.id')->where('canSubmit',false));
        $this->assertNull($u->fresh()->email_verified_at);
    }

    public function test_legacy_activation_get_never_joins_or_renders_confirmation(): void
    {
        $c=$this->challenge();$l=$this->landing($c);$this->actingAs(User::factory()->unverified()->create());
        $this->assertFalse($c->requiresParticipationChoice());
        $this->get(route('landing-presentations.activation',$l))->assertRedirect(route('landing-presentations.show',$l));
        $this->assertDatabaseCount('challenge_participations',0);
        $this->post(route('social-challenges.intent',$c),['landing_presentation_slug'=>$l->slug]);
        $this->get(route('landing-presentations.activation',$l))->assertRedirect(route('landing-presentations.result',$l));
    }

    public function test_final_guest_participation_intent_registers_and_joins_without_second_confirmation(): void
    {
        Notification::fake();$c=$this->challenge();$l=$this->landing($c);
        $this->post(route('landing-presentations.acquisition',$l),['method'=>'register','intended_action'=>'PARTICIPATE'])->assertNoContent()->assertSessionHas('public_journey.continuation.intended_action','PARTICIPATE');
        $this->assertDatabaseCount('challenge_participations',0);
        $this->post('/register',['name'=>'Direct Guest','email'=>'direct@example.test','whatsapp'=>'71234567'])->assertRedirect(route('landing-presentations.result',$l,false))->assertSessionMissing('public_journey.continuation');
        $u=User::where('email','direct@example.test')->sole();$this->assertAuthenticatedAs($u);$this->assertNull($u->email_verified_at);
        $this->assertDatabaseHas('challenge_participations',['user_id'=>$u->id,'challenge_id'=>$c->id]);
        $this->get(route('landing-presentations.result',$l))->assertOk();
        $this->assertDatabaseCount('challenge_social_entries',0);$this->assertDatabaseCount('challenge_reward_grants',0);
    }

    public function test_explicit_login_intent_joins_directly(): void
    {
        $c=$this->challenge();$l=$this->landing($c);$u=User::factory()->unverified()->create();
        $this->post(route('landing-presentations.acquisition',$l),['method'=>'login','intended_action'=>'PARTICIPATE'])->assertNoContent();
        $this->post('/login',['email'=>$u->email,'password'=>'password'])->assertRedirect(route('landing-presentations.result',$l,false));
        $this->assertDatabaseHas('challenge_participations',['user_id'=>$u->id,'challenge_id'=>$c->id]);
    }

    public function test_generic_signup_and_incidental_context_do_not_join_without_explicit_intent(): void
    {
        Notification::fake();$c=$this->challenge();$l=$this->landing($c);
        $this->postJson(route('landing-presentations.acquisition',$l),['method'=>'register'])->assertUnprocessable()->assertJsonValidationErrors('intended_action');
        $this->postJson(route('landing-presentations.acquisition',$l),['method'=>'register','intended_action'=>'RESERVE'])->assertUnprocessable()->assertJsonValidationErrors('intended_action');
        $this->post('/register',['name'=>'Generic','email'=>'generic@example.test','whatsapp'=>'71234567'])->assertRedirect('/dashboard');$this->post('/logout');
        app(PublicJourneyContinuation::class)->setLanding($l);
        $this->post('/register',['name'=>'Context only','email'=>'context@example.test','whatsapp'=>'71234568'])->assertRedirect(route('landing-presentations.show',$l,false));
        $this->assertDatabaseCount('challenge_participations',0);
        $this->assertNull(app(PublicJourneyContinuation::class)->destination(['journey'=>'SOCIAL_CHALLENGE','resource_id'=>$c->id,'action'=>'PARTICIPATE','intended_action'=>'RESERVE','landing_presentation_id'=>$l->id]));
    }

    public function test_explicit_intent_cannot_bypass_membership_or_mismatched_subject(): void
    {
        Notification::fake();$c=$this->challenge(['participation_eligibility'=>'ACTIVE_MEMBERS']);$l=$this->landing($c);
        $this->post(route('landing-presentations.acquisition',$l),['method'=>'register','intended_action'=>'PARTICIPATE']);
        $this->post('/register',['name'=>'Not eligible','email'=>'ineligible@example.test','whatsapp'=>'71234567'])->assertRedirect(route('landing-presentations.show',$l,false));
        $this->assertDatabaseCount('challenge_participations',0);
        $intent=['journey'=>'SOCIAL_CHALLENGE','resource_id'=>$c->id+1,'action'=>'PARTICIPATE','intended_action'=>'PARTICIPATE','landing_presentation_id'=>$l->id];
        $this->assertNull(app(PublicJourneyContinuation::class)->afterAuthentication(request(),$intent));
    }

    public function test_experience_session_choice_keeps_activation_and_does_not_reserve(): void
    {
        Notification::fake();$e=Experience::factory()->published()->create();ExperienceSession::factory()->for($e)->upcoming()->count(2)->create();$l=$e->landingPresentations()->create(['name'=>'Dates','slug'=>'choose-dates','status'=>'PUBLISHED','default_scope'=>'ALL']);
        $this->post(route('landing-presentations.acquisition',$l),['method'=>'register'])->assertNoContent();
        $this->post('/register',['name'=>'Date guest','email'=>'dates@example.test','whatsapp'=>'71234567'])->assertRedirect(route('landing-presentations.activation',$l,false));
        $this->get(route('landing-presentations.activation',$l))->assertInertia(fn(Assert $p)=>$p->where('conversionStage','activation')->has('experience.sessions',2));
        $this->assertDatabaseCount('experience_reservations',0);
    }

    public function test_benefit_location_choice_keeps_activation_and_does_not_redeem(): void
    {
        Notification::fake();$partner=Partner::factory()->published()->create();$b=Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations'=>true]);
        foreach(['A','B'] as $name){$location=Location::factory()->published()->withPartner($partner)->create(['name'=>$name]);$location->setRedemptionPin('123456');$location->save();}
        $l=$b->landingPresentations()->create(['name'=>'Locations','slug'=>'choose-locations','status'=>'PUBLISHED','default_scope'=>'ALL']);
        $this->postJson(route('landing-presentations.acquisition',$l),['method'=>'register','intended_action'=>'PARTICIPATE'])->assertUnprocessable()->assertJsonValidationErrors('intended_action');
        $this->post(route('landing-presentations.acquisition',$l),['method'=>'register'])->assertNoContent();
        $this->post('/register',['name'=>'Location guest','email'=>'locations@example.test','whatsapp'=>'71234567'])->assertRedirect(route('landing-presentations.activation',$l,false));
        $this->get(route('landing-presentations.activation',$l))->assertInertia(fn(Assert $p)=>$p->where('conversionStage','activation')->has('locations',2));
        $this->assertDatabaseCount('redemptions',0);
    }
}
