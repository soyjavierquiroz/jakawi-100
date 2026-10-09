<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureVisitorId;
use App\Models\{AnalyticsEvent, AttributionTouch, Benefit, Challenge, ChallengeParticipation, Experience, ExperienceReservation, ExperienceSession, LandingPresentation, Membership, MembershipPurchaseRequest, Partner, Unlock, UnlockParticipation, User};
use App\Services\{GrowthMeasurementService, MembershipService, UnlockParticipationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GrowthMeasurementTest extends TestCase
{
    use RefreshDatabase;

    private function landing(string $scope = 'NONE'): LandingPresentation
    {
        $challenge = Challenge::create(['title'=>'Growth', 'slug'=>'growth-challenge', 'status'=>'open', 'review_status'=>'APPROVED',
            'description'=>'Participa', 'instructions'=>'Foto', 'allowed_platforms'=>['instagram'], 'required_hashtags'=>[], 'required_mentions'=>[],
            'evidence_type'=>'MANUAL', 'qualification_type'=>'VALID_EVIDENCE', 'selection_type'=>'ALL_QUALIFIED', 'evaluation_mode'=>'CONTINUOUS',
            'review_mode'=>'AUTOMATIC', 'participation_eligibility'=>'ALL_USERS', 'reward_eligibility'=>'ALL_USERS', 'reward_type'=>'JP', 'reward_jp_amount'=>25]);
        return $challenge->landingPresentations()->create(['name'=>'Growth', 'slug'=>'growth', 'status'=>'PUBLISHED', 'default_scope'=>$scope, 'campaign_key'=>'acquisition-only']);
    }

    private function viewLanding(LandingPresentation $landing): AnalyticsEvent
    {
        $this->get('/l/'.$landing->slug.'?utm_source=tiktok&utm_medium=paid&utm_campaign=launch&utm_content=hero&utm_term=food&email=secret@example.test&token=SECRET')->assertOk();
        $event = AnalyticsEvent::where('event_name', 'landing_view')->sole();
        $this->withUnencryptedCookie(config('jakawi.analytics.visitor_cookie'), $event->visitor_id);
        return $event;
    }

    public function test_null_campaign_key_remains_null_in_landing_view_and_cta(): void
    {
        $landing = $this->landing();
        $landing->update(['campaign_key' => null]);
        $view = $this->viewLanding($landing);
        $this->assertNull($view->campaign_key);
        $this->post('/analytics/landing-presentations/growth/cta', ['cta_kind'=>'signup', 'cta_location'=>'hero', 'destination'=>'/register'])->assertNoContent();
        $this->assertNull(AnalyticsEvent::where('event_name','landing_cta_click')->sole()->campaign_key);
    }

    public function test_real_campaign_key_is_preserved_in_landing_view_and_cta(): void
    {
        $landing = $this->landing();
        $landing->update(['campaign_key' => 'meta-benefit-launch']);
        $view = $this->viewLanding($landing);
        $this->assertSame('meta-benefit-launch', $view->campaign_key);
        $this->post('/analytics/landing-presentations/growth/cta', ['cta_kind'=>'signup', 'cta_location'=>'hero', 'destination'=>'/register'])->assertNoContent();
        $this->assertSame('meta-benefit-launch', AnalyticsEvent::where('event_name','landing_cta_click')->sole()->campaign_key);
    }

    public function test_empty_and_whitespace_campaign_keys_normalize_in_shared_growth_writer(): void
    {
        foreach (['', '   ', '  meta-benefit-launch  '] as $value) {
            $expected = trim($value) === '' ? null : trim($value);
            foreach (['landing_view', 'landing_cta_click'] as $name) {
                $event = app(GrowthMeasurementService::class)->record($name, ['campaign_key'=>$value]);
                $this->assertSame($expected, $event->campaign_key);
            }
        }
    }

    public function test_guest_cta_continuity_and_privacy_and_repeated_clicks(): void
    {
        $landing = $this->landing('GUESTS'); $view = $this->viewLanding($landing);
        $this->assertTrue(Str::isUuid($view->event_id)); $this->assertTrue(Str::isUuid($view->visitor_id));
        $this->assertNull($view->user_id); $this->assertSame($landing->id, $view->landing_presentation_id);
        $this->assertSame('GUESTS', $view->metadata['default_scope']);
        $this->assertSame('acquisition-only', $view->campaign_key);
        foreach (['source'=>'tiktok', 'medium'=>'paid', 'campaign'=>'launch', 'content'=>'hero', 'term'=>'food'] as $key=>$value) $this->assertSame($value, $view->{'utm_'.$key});
        $this->assertSame(AttributionTouch::sole()->id, $view->attribution_touch_id);
        $payload = ['cta_kind'=>'signup', 'cta_location'=>'hero', 'destination'=>'/register?email=secret@example.test&token=SECRET'];
        $this->post('/analytics/landing-presentations/growth/cta', $payload)->assertNoContent();
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'landing_cta_click')->count());
        $this->post('/analytics/landing-presentations/growth/cta', $payload)->assertNoContent();
        $clicks = AnalyticsEvent::where('event_name', 'landing_cta_click')->get();
        $this->assertCount(2, $clicks);
        foreach ($clicks as $click) {
            $this->assertSame($view->visitor_id, $click->visitor_id); $this->assertSame($view->attribution_touch_id, $click->attribution_touch_id);
            $this->assertSame('/register', $click->metadata['destination']);
            $this->assertSame('signup', $click->metadata['cta_kind']); $this->assertSame('hero', $click->metadata['cta_location']);
            $this->assertStringNotContainsString('SECRET', $click->toJson()); $this->assertStringNotContainsString('secret@example', $click->toJson());
        }
        $this->assertSame(3, AnalyticsEvent::pluck('event_id')->unique()->count());
        $this->assertDatabaseCount('conversions', 0);
    }

    public function test_signup_membership_activation_and_return_keep_acquisition_context(): void
    {
        $landing=$this->landing(); $view=$this->viewLanding($landing);
        $this->post('/retos/growth-challenge/participar', ['landing_presentation_slug'=>'growth'])->assertRedirect();
        $this->post('/register', ['name'=>'Private Name', 'email'=>'growth@example.test', 'whatsapp'=>'71234567'])->assertRedirect();
        $user=User::where('email', 'growth@example.test')->sole();
        $signup=AnalyticsEvent::where('event_name', 'signup_completed')->sole();
        $this->assertSame($user->id, $signup->user_id); $this->assertSame($view->visitor_id, $signup->visitor_id);
        $this->assertSame($view->attribution_touch_id, $signup->attribution_touch_id); $this->assertSame($landing->id, $signup->landing_presentation_id);
        app(GrowthMeasurementService::class)->signupCompleted($user);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'signup_completed')->count());
        $benefit=Benefit::factory()->create();
        $this->actingAs($user)->get('/membresia?journey=BENEFIT&action=REDEEM&resource_id='.$benefit->id)->assertOk();
        $this->post('/membresia/solicitar')->assertRedirect(); $this->post('/membresia/solicitar')->assertRedirect();
        $intent=AnalyticsEvent::where('event_name', 'membership_purchase_requested')->sole();
        $this->assertSame($view->visitor_id, $intent->visitor_id); $this->assertSame($view->attribution_touch_id, $intent->attribution_touch_id);
        $admin=User::factory()->create(['is_admin'=>true]);
        AttributionTouch::create(['user_id'=>$user->id, 'anonymous_id'=>(string)Str::uuid(), 'utm_source'=>'later', 'occurred_at'=>now()->addSecond()]);
        $item=MembershipPurchaseRequest::sole();
        $payload=['user_id'=>$user->id,'membership_purchase_request_id'=>$item->id,'manual_reference'=>'GROWTH-1','idempotency_key'=>(string)Str::uuid()];
        $this->actingAs($admin)->post('/admin/sales', $payload)->assertRedirect(); $this->post('/admin/sales', $payload)->assertRedirect();
        $activated=AnalyticsEvent::where('event_name','membership_activated')->sole();
        $this->assertSame($view->visitor_id,$activated->visitor_id); $this->assertSame($view->attribution_touch_id,$activated->attribution_touch_id);
        $this->assertSame($user->id,$activated->user_id); $this->assertSame($landing->id,$activated->landing_presentation_id);
        $this->assertStringNotContainsString('Private Name',$signup->toJson()); $this->assertStringNotContainsString('growth@example',$signup->toJson());
        $this->assertStringNotContainsString('71234567',$signup->toJson());
        $this->actingAs($user)->post('/membresia/volver/'.$item->id)->assertRedirect();
        $this->assertSame($view->attribution_touch_id,$item->fresh()->attribution_touch_id);
    }

    public function test_native_challenge_and_unlock_outcomes_are_idempotent_and_no_economics(): void
    {
        $landing=$this->landing(); $user=User::factory()->create(); $this->actingAs($user);
        $this->post('/retos/growth-challenge/participar')->assertRedirect(); $this->post('/retos/growth-challenge/participar')->assertRedirect();
        $joined=AnalyticsEvent::where('event_name','challenge_joined')->sole();
        $this->assertNull($joined->landing_presentation_id); $this->assertSame($user->id,$joined->user_id);
        $this->assertSame(ChallengeParticipation::sole()->id,$joined->metadata['participation_id']);
        $unlock=Unlock::create(['title'=>'Growth Unlock','slug'=>'growth-unlock','status'=>Unlock::ACTIVE,'minimum_commitments'=>10,'free_user_eligible'=>true,'member_eligible'=>true]);
        $p=app(UnlockParticipationService::class)->commit($unlock,$user); app(UnlockParticipationService::class)->commit($unlock,$user);
        $committed=AnalyticsEvent::where('event_name','unlock_committed')->sole();
        $this->assertSame($p->id,$committed->source_id); $this->assertSame($p->id,$committed->metadata['participation_id']);
        $this->assertDatabaseCount('jp_holds',0); $this->assertDatabaseCount('reward_transactions',0); $this->assertDatabaseCount('conversions',0);
    }

    public function test_rollback_does_not_record_outcome_and_expired_touch_is_not_reused(): void
    {
        $this->landing(); $user=User::factory()->create(); $this->actingAs($user);
        AttributionTouch::create(['user_id'=>$user->id, 'utm_source'=>'expired', 'occurred_at'=>now()->subDays(31)]);
        try {
            DB::transaction(function () use ($user) {
                ChallengeParticipation::create(['challenge_id'=>Challenge::sole()->id, 'user_id'=>$user->id]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {}
        $this->assertSame(0,AnalyticsEvent::where('event_name','challenge_joined')->count());
        $this->post('/retos/growth-challenge/participar')->assertRedirect();
        $this->assertNull(AnalyticsEvent::where('event_name','challenge_joined')->sole()->attribution_touch_id);
    }

    public function test_preview_prefetch_and_partial_reload_do_not_count_acquisition(): void
    {
        $landing=$this->landing('ALL');
        $this->withHeader('Purpose','prefetch')->get('/l/growth')->assertOk();
        $this->assertSame(0,AnalyticsEvent::where('event_name','landing_view')->count());
        $this->flushHeaders(); $this->viewLanding($landing);
        $this->withHeaders(['X-Inertia'=>'true','X-Inertia-Partial-Component'=>'landing-presentations/challenge','X-Inertia-Partial-Data'=>'copy'])->get('/l/growth')->assertOk();
        $this->assertSame(1,AnalyticsEvent::where('event_name','landing_view')->count());
        $this->flushHeaders(); $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->get('/admin/retos/growth-challenge/landings/'.$landing->slug.'/preview')->assertOk();
        $this->assertSame(1,AnalyticsEvent::where('event_name','landing_view')->count());
    }

    public function test_all_four_landings_record_one_server_view_with_subject_context(): void
    {
        $challengeLanding = $this->landing();
        $subjects = [Benefit::factory()->published()->create(), Experience::factory()->published()->create(),
            Unlock::create(['title'=>'Landing Unlock', 'slug'=>'landing-unlock', 'status'=>Unlock::ACTIVE, 'minimum_commitments'=>10])];
        $landings = [$challengeLanding];
        foreach ($subjects as $index => $subject) $landings[] = $subject->landingPresentations()->create([
            'name'=>'Landing '.$index, 'slug'=>'landing-'.$index, 'status'=>'PUBLISHED', 'default_scope'=>'NONE']);
        foreach ($landings as $landing) {
            $before = AnalyticsEvent::where('event_name','landing_view')->count();
            $this->get('/l/'.$landing->slug)->assertOk();
            $this->assertSame($before + 1, AnalyticsEvent::where('event_name','landing_view')->count());
            $event = AnalyticsEvent::where('landing_presentation_id',$landing->id)->where('event_name','landing_view')->sole();
            $this->assertSame($landing->subject_type,$event->subject_type);
            $this->assertSame($landing->subject_id,$event->subject_id);
            $this->assertSame($landing->slug,$event->landing_slug);
        }
    }

    public function test_authenticated_product_action_keeps_guest_identity_and_landing(): void
    {
        $landing=$this->landing(); $view=$this->viewLanding($landing);
        $this->post('/register',['name'=>'Growth User','email'=>'product@example.test','whatsapp'=>'71234567'])->assertRedirect();
        $user=User::where('email','product@example.test')->sole();
        $user->markEmailAsVerified();
        $this->actingAs($user)->post('/retos/growth-challenge/participar')->assertRedirect();
        $joined=AnalyticsEvent::where('event_name','challenge_joined')->sole();
        $this->assertSame($view->visitor_id,$joined->visitor_id); $this->assertSame($user->id,$joined->user_id);
        $this->assertSame($landing->id,$joined->landing_presentation_id);
        $this->assertSame($view->attribution_touch_id,$joined->attribution_touch_id);
    }

    public function test_external_cta_is_intent_only_and_unknown_categories_are_rejected(): void
    {
        $this->viewLanding($this->landing());
        $payload=['cta_kind'=>'external','cta_location'=>'final','destination'=>'https://wa.me/59171234567?text=Private'];
        $this->post('/analytics/landing-presentations/growth/cta',$payload)->assertNoContent();
        $event=AnalyticsEvent::where('event_name','landing_cta_click')->sole();
        $this->assertSame('external',$event->metadata['destination']);
        $this->assertSame('final',$event->metadata['cta_location']);
        $this->assertStringNotContainsString('71234567',$event->toJson());
        foreach (['experience_reserved','signup_completed','membership_activated'] as $name) $this->assertSame(0,AnalyticsEvent::where('event_name',$name)->count());
        $this->post('/analytics/landing-presentations/growth/cta', ['cta_kind'=>'signup','cta_location'=>'invented','destination'=>'/register'])->assertSessionHasErrors('cta_location');
        $this->assertSame(1,AnalyticsEvent::where('event_name','landing_cta_click')->count());
    }

    public function test_native_signup_still_has_visitor_without_landing_or_touch(): void
    {
        $this->post('/register',['name'=>'Native User','email'=>'native@example.test','whatsapp'=>'71234567'])->assertRedirect();
        $event=AnalyticsEvent::where('event_name','signup_completed')->sole();
        $this->assertTrue(Str::isUuid($event->visitor_id)); $this->assertNotNull($event->user_id);
        $this->assertNull($event->landing_presentation_id); $this->assertNull($event->attribution_touch_id);
    }

    public function test_tracking_failure_does_not_roll_back_valid_domain_operation(): void
    {
        $this->landing(); $user=User::factory()->create(); $this->actingAs($user);
        \Illuminate\Support\Facades\Schema::rename('analytics_events','analytics_events_unavailable');
        try {
            DB::transaction(fn () => ChallengeParticipation::create(['challenge_id'=>Challenge::sole()->id, 'user_id'=>$user->id]));
            $this->assertSame(1,ChallengeParticipation::where('user_id',$user->id)->count());
        } finally {
            \Illuminate\Support\Facades\Schema::rename('analytics_events_unavailable','analytics_events');
        }
        $this->assertSame(0,AnalyticsEvent::where('event_name','challenge_joined')->count());
    }

    public function test_storage_enforces_unique_event_id_and_domain_identity(): void
    {
        $first=AnalyticsEvent::create(['event_name'=>'signup_completed', 'source_type'=>User::class, 'source_id'=>1, 'occurred_at'=>now()]);
        foreach ([['event_id'=>$first->event_id], ['source_type'=>User::class,'source_id'=>1]] as $duplicate) {
            try {
                DB::transaction(fn()=>AnalyticsEvent::create($duplicate+['event_name'=>'signup_completed','occurred_at'=>now()]));
                $this->fail('Duplicate accepted');
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {}
        }
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'signup_completed')->count());
    }
}
