<?php
namespace Tests\Feature;

use App\Models\{Benefit, Challenge, Experience, ExperienceSession, LandingPresentation, Location, Partner, Unlock, User};
use App\Services\{ProductLandingResolver, PublicJourneyContinuation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Notification, Password, Queue, URL};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MicroOptInJourneyTest extends TestCase
{
    use RefreshDatabase;
    private function subjects(): array
    {
        $partner = Partner::factory()->published()->create(['name' => 'Altura Nube Café']);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['title' => 'Café de cortesía con tu brunch', 'applies_to_all_locations' => true]);
        foreach (['Centro', 'Recoleta'] as $name) {
            $location = Location::factory()->published()->withPartner($partner)->create(['name' => $name]);
            $location->setRedemptionPin('123456');
            $location->save();
        }
        $experience = Experience::factory()->published()->create(['title' => 'Sunset JAKAWI']);
        ExperienceSession::factory()->for($experience)->upcoming()->create();
        $challenge = Challenge::create([
            'title' => 'Reto Cena de Ciudad', 'slug' => 'reto-cena', 'status' => 'open', 'review_status' => 'APPROVED',
            'description' => 'Comparte tu cena.', 'instructions' => 'Publica una foto.',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => [], 'required_mentions' => [],
            'evidence_type' => 'SOCIAL_POST', 'qualification_type' => 'VALID_EVIDENCE', 'selection_type' => 'ALL_QUALIFIED',
            'evaluation_mode' => 'CONTINUOUS', 'review_mode' => 'AUTOMATIC', 'participation_eligibility' => 'ALL_USERS',
            'reward_eligibility' => 'ALL_USERS', 'reward_type' => 'JP', 'reward_jp_amount' => 25,
        ]);
        $unlock = Unlock::create([
            'title' => 'Cena secreta', 'slug' => 'cena-secreta', 'status' => Unlock::ACTIVE,
            'minimum_commitments' => 10, 'free_user_eligible' => true, 'member_eligible' => true,
            'secret_mode' => true, 'hide_exact_offer_until_unlock' => true, 'hide_partner_until_unlock' => true,
            'hide_location_until_unlock' => true, 'description' => 'RESTRICTED-SOURCE-SENTINEL',
            'free_user_offer' => 'RESTRICTED-OFFER-SENTINEL', 'partner_id' => $partner->id,
        ]);

        return [$benefit, $experience, $challenge, $unlock];
    }

    private function landing($subject, string $scope = 'GUESTS'): LandingPresentation
    {
        return $subject->landingPresentations()->create([
            'name' => 'Acquisition QA', 'slug' => 'acquire-'.strtolower(class_basename($subject)).'-'.$subject->id,
            'status' => 'PUBLISHED', 'default_scope' => $scope,
        ]);
    }


    private function acquire($subject, string $email = 'carla@example.test', string $phone = '71234567') {
        $landing = $this->landing($subject, 'ALL');
        $this->post(route('landing-presentations.acquisition', $landing), ['method' => 'register'])->assertNoContent();
        $response = $this->post('/register', ['name' => 'Carla Pérez', 'email' => $email, 'whatsapp' => $phone]);
        return [$landing, $response];
    }
    private function member(): User {
        $user = User::factory()->create();
        \App\Models\Membership::create(['user_id'=>$user->id,'status'=>'active','starts_at'=>now()->subDay(),'ends_at'=>now()->addYear(),'amount_paid'=>100]);
        return $user;
    }
    private function noMutation(): void {
        foreach (['redemptions','experience_reservations','challenge_participations','unlock_participations','memberships','jp_holds'] as $table) $this->assertDatabaseCount($table, 0);
    }
    public function test_passwordless_identification_authenticates_and_returns_same_benefit_location_without_mutation(): void {
        Notification::fake();
        [$landing, $response] = $this->acquire($this->subjects()[0]);
        $response->assertRedirect(route('landing-presentations.activation', $landing, false));
        $user = User::where('email', 'carla@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse(Hash::check('carla@example.test', $user->password));
        $this->get(route('landing-presentations.activation', $landing))->assertInertia(fn(Assert $page)=>$page->component('landing-presentations/conversion')->where('conversionStage','activation')->where('acquisitionResumed',true)->where('newAcquisitionAccount',true)->where('benefit.id',$landing->subject_id)->has('locations',2));
        Notification::assertSentTo($user, \App\Notifications\SecureAccountAccess::class, fn($notification) => Password::broker()->tokenExists($user, $notification->token));
        $this->noMutation();
    }
    public function test_existing_email_is_not_authenticated_or_modified(): void {
        $user = User::factory()->create(['email'=>'Carla@Example.Test']);
        $password = $user->password;
        [, $response] = $this->acquire($this->subjects()[0]);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseCount('users',1);
        $this->noMutation();
    }
    public function test_normalized_existing_phone_cannot_create_duplicate_identity(): void {
        $user = User::factory()->create();
        $user->profile()->create(['whatsapp'=>'+59171234567']);
        [, $response] = $this->acquire($this->subjects()[0], 'different@example.test', '591 7123 4567');
        $response->assertSessionHasErrors('whatsapp');
        $this->assertGuest();
        $this->assertDatabaseCount('users',1);
        $this->noMutation();
    }
    public function test_access_email_is_queued_and_uses_existing_reset_token_without_verifying(): void {
        Queue::fake();
        $this->acquire($this->subjects()[0]);
        $user = User::where('email','carla@example.test')->sole();
        Queue::assertPushed(\Illuminate\Notifications\SendQueuedNotifications::class);
        $this->assertDatabaseHas('password_reset_tokens',['email'=>$user->email]);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->noMutation();
    }
    public function test_mail_failure_does_not_block_current_session(): void {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('isolated mail unavailable'));
        [$landing,$response] = $this->acquire($this->subjects()[0]);
        $response->assertRedirect(route('landing-presentations.activation',$landing,false));
        $this->assertAuthenticated();
        $this->assertFalse(session('acquisition_access_email_queued'));
        $this->noMutation();
    }
    public function test_existing_login_resumes_each_item_and_never_auto_mutates(): void {
        $user = User::factory()->create();
        foreach ($this->subjects() as $subject) {
            $landing=$this->landing($subject,'ALL');
            $this->post(route('landing-presentations.acquisition',$landing),['method'=>'login']);
            $this->post('/login',['email'=>$user->email,'password'=>'password'])->assertRedirect(route('landing-presentations.activation',$landing,false));
            $this->assertAuthenticatedAs($user);
            $this->get(route('landing-presentations.show',$landing))->assertInertia(fn(Assert $p)=>$p->where('acquisition.subjectId',$subject->id));
            $this->post('/logout');
        }
        $this->noMutation();
    }
    public function test_benefit_explicit_action_delivers_only_real_owned_code_location_and_terms(): void {
        $benefit=$this->subjects()[0];$location=$benefit->availableLocations()->first();
        $location->update(['address'=>'Calle QA 123']);$benefit->update(['terms'=>'Presenta antes de pedir.']);
        $user=$this->member();
        $this->actingAs($user)->post(route('redemptions.start',$benefit),['location_id'=>$location->id])->assertRedirect();
        $redemption=\App\Models\Redemption::sole();
        $this->get(route('redemptions.show',$redemption))->assertInertia(fn(Assert $p)=>$p->component('redemptions/show')->where('redemption.code',$redemption->code)->where('redemption.address','Calle QA 123')->where('redemption.location_name',$location->name)->where('redemption.terms','Presenta antes de pedir.'));
        $this->actingAs(User::factory()->create())->get(route('redemptions.show',$redemption))->assertForbidden();
    }
    public function test_experience_explicit_reservation_returns_pending_truth_to_same_landing(): void {
        $experience=$this->subjects()[1];$landing=$this->landing($experience,'ALL');
        $experience->update(['reservation_method'=>'jakawi']);$partner=Partner::factory()->published()->create();
        $experience->partners()->attach($partner->id,['role'=>'organizer']);
        $session=$experience->upcomingSessions()->first();$session->update(['reservation_partner_id'=>$partner->id]);
        $user=$this->member();
        $this->actingAs($user)->get(route('landing-presentations.activation',$landing))->assertOk();
        $this->get(route('landing-presentations.result',$landing))->assertNotFound();
        $this->assertDatabaseCount('experience_reservations',0);
        $this->get(route('landing-presentations.show',$landing))->assertInertia(fn(Assert $p)=>$p->where('landingDirectAction.data.experience_session_id',$session->id));
        $this->post(route('experiences.reservations.store',$experience),['experience_session_id'=>$session->id,'landing_presentation_id'=>$landing->id])->assertRedirect(route('landing-presentations.result',['presentation'=>$landing,'session'=>$session->id]));
        $reservation=\App\Models\ExperienceReservation::sole();
        $this->get(route('landing-presentations.result',['presentation'=>$landing,'session'=>$session->id]))->assertInertia(fn(Assert $p)=>$p->component('landing-presentations/conversion')->where('conversionStage','result')->where('restoredSessionId',$session->id)->where('experience.sessions.0.reservation.public_id',$reservation->public_id)->where('experience.sessions.0.reservation.status','pending'));
        $this->actingAs(User::factory()->create())->get(route('landing-presentations.result',$landing))->assertNotFound();
    }
    public function test_challenge_identification_preserves_contextual_verification_and_no_participation(): void {
        Notification::fake();[$landing,$response]=$this->acquire($this->subjects()[2]);
        $response->assertRedirect(route('landing-presentations.activation',$landing,false));
        $this->get(route('landing-presentations.show',$landing))->assertInertia(fn(Assert $p)=>$p->where('canSubmit',false)->where('participation',null));
        $this->post(route('social-challenges.intent',$landing->subject),['landing_presentation_slug'=>$landing->slug])->assertRedirect(route('landing-presentations.activation',$landing));
        $this->post(route('social-challenges.submit',$landing->subject),['url'=>'https://instagram.com/p/qa'])->assertSessionHasErrors('url');
        $this->noMutation();
    }
    public function test_verified_challenge_explicit_participation_delivers_instructions_and_state(): void {
        $challenge=$this->subjects()[2];$challenge->update(['evidence_type'=>'MANUAL','instructions'=>'Cocina y presenta tu cena.','ends_at'=>now()->addDays(5)]);
        $landing=$this->landing($challenge,'ALL');$user=User::factory()->create();
        $this->actingAs($user)->get(route('landing-presentations.show',$landing))->assertOk();$this->assertDatabaseCount('challenge_participations',0);
        $this->get(route('landing-presentations.result',$landing))->assertNotFound();
        $this->post(route('social-challenges.intent',$challenge),['landing_presentation_slug'=>$landing->slug])->assertRedirect(route('landing-presentations.result',$landing).'#participar');
        $this->get(route('landing-presentations.result',$landing))->assertInertia(fn(Assert $p)=>$p->component('landing-presentations/conversion')->where('conversionStage','result')->has('participation.id')->where('challenge.instructions','Cocina y presenta tu cena.')->where('challenge.evidence_type','MANUAL'));
        $this->actingAs(User::factory()->create())->get(route('landing-presentations.result',$landing))->assertNotFound();
    }
    public function test_challenge_verification_link_returns_directly_to_same_landing_without_participating(): void {
        Notification::fake();[$landing]=$this->acquire($this->subjects()[2]);
        $this->get(route('landing-presentations.show',$landing));
        $user=auth()->user();
        $url=URL::temporarySignedRoute('verification.verify',now()->addMinutes(10),['id'=>$user->id,'hash'=>sha1($user->email)]);
        $this->get($url)->assertRedirect(route('landing-presentations.activation',$landing,false));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->noMutation();
    }
    public function test_unlock_source_requires_explicit_domain_commit_reveal_and_authenticated_eligibility(): void {
        $unlock=$this->subjects()[3];$landing=$this->landing($unlock,'ALL');
        $landing->update(['vsl'=>['source'=>['type'=>'mp4','url'=>'https://example.test/RESTRICTED-SOURCE.mp4']]]);
        $unlock->update(['status'=>Unlock::UNLOCKED]);
        $this->get(route('landing-presentations.show',$landing))->assertDontSee('RESTRICTED-SOURCE')->assertDontSee('RESTRICTED-OFFER')->assertInertia(fn(Assert $p)=>$p->where('vsl',null));
        $user=User::factory()->create();
        $this->actingAs($user)->get(route('landing-presentations.show',$landing))->assertDontSee('RESTRICTED-SOURCE')->assertInertia(fn(Assert $p)=>$p->where('vsl',null));
        $this->noMutation();
        $unlock->update(['status'=>Unlock::ACTIVE,'minimum_commitments'=>1]);
        $this->get(route('landing-presentations.result',$landing))->assertNotFound();
        $this->from(route('landing-presentations.activation',$landing))->post(route('unlocks.commit',$unlock),['landing_presentation_id'=>$landing->id])->assertRedirect(route('landing-presentations.result',$landing));
        $this->get(route('landing-presentations.result',$landing))->assertInertia(fn(Assert $p)=>$p->component('landing-presentations/conversion')->where('unlock.revealable',true));
        $this->assertDatabaseCount('unlock_participations',1);
        $this->get(route('landing-presentations.show',$landing))->assertInertia(fn(Assert $p)=>$p->where('unlock.revealable',true)->where('vsl.source.url','https://example.test/RESTRICTED-SOURCE.mp4'));
        $unlock->update(['free_user_eligible'=>false]);
        $this->get(route('landing-presentations.show',$landing))->assertDontSee('RESTRICTED-SOURCE')->assertInertia(fn(Assert $p)=>$p->where('vsl',null));
        $unlock->update(['free_user_eligible'=>true]);
        $user->unlockParticipations()->update(['status'=>\App\Models\UnlockParticipation::CANCELLED_ON_TIME]);
        $this->get(route('landing-presentations.show',$landing))->assertDontSee('RESTRICTED-SOURCE');
        $this->assertDatabaseCount('jp_holds',0);
    }
}
