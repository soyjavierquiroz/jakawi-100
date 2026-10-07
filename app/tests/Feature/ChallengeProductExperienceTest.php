<?php
namespace Tests\Feature;

use App\Jobs\RefreshChallengeSocialEntry;
use App\Integrations\ShareContest\Inspector;
use App\Integrations\ShareContest\InspectionResult;
use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ChallengeSocialEntry;
use App\Models\ChallengeRewardGrant;
use App\Models\City;
use App\Models\Partner;
use App\Models\User;
use App\Services\ChallengeRanking;
use App\Services\ChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChallengeProductExperienceTest extends TestCase {
    use RefreshDatabase;

    private function challenge(array $fields=[]): Challenge {
        return Challenge::create(array_merge([
            'title'=>'Reto de prueba','slug'=>'reto-'.Str::lower(Str::random(8)),'description'=>'Haz algo',
            'status'=>'open','review_status'=>'APPROVED','evidence_type'=>'SOCIAL_POST','allowed_platforms'=>[],
            'required_hashtags'=>[],'required_mentions'=>[],'qualification_type'=>'METRIC_THRESHOLD',
            'qualification_metric'=>'likes','qualification_target'=>500,'selection_type'=>'ALL_QUALIFIED',
            'evaluation_mode'=>'CONTINUOUS','review_mode'=>'AUTOMATIC','participation_eligibility'=>'ALL_USERS',
            'reward_eligibility'=>'ALL_USERS','reward_type'=>'JP','reward_jp_amount'=>500,
        ],$fields));
    }
    private function payload(array $fields=[]): array {
        return array_merge([
            'title'=>'Reto Partner','description'=>'Haz una publicación','allowed_platforms'=>[],
            'required_hashtags'=>[],'required_mentions'=>[],'evidence_type'=>'SOCIAL_POST',
            'qualification_type'=>'VALID_EVIDENCE','selection_type'=>'ALL_QUALIFIED',
            'evaluation_mode'=>'CONTINUOUS','review_mode'=>'AUTOMATIC','participation_eligibility'=>'ALL_USERS',
            'reward_eligibility'=>'ALL_USERS','reward_type'=>'MANUAL_PRIZE','manual_prize_description'=>'Un premio',
        ],$fields);
    }
    private function entry(Challenge $c, User $user, int $likes): ChallengeSocialEntry {
        $p=ChallengeParticipation::firstOrCreate(['challenge_id'=>$c->id,'user_id'=>$user->id]);
        return ChallengeSocialEntry::create(['challenge_id'=>$c->id,'participation_id'=>$p->id,
            'social_url'=>'https://example.com/'.Str::random(12),'normalized_url_hash'=>hash('sha256',Str::random(12)),
            'external_reference'=>Str::ulid(),'validation_status'=>'valid','inspection_status'=>'inspected',
            'checked_at'=>now(),'likes'=>$likes]);
    }
    public function test_partner_submission_review_and_publication_gate(): void {
        $partner=Partner::factory()->create();
        $owner=User::factory()->create(); $owner->partners()->attach($partner,['role'=>'owner']);
        $this->actingAs($owner)->post("/partner/{$partner->slug}/retos",$this->payload(['status'=>'open']))->assertRedirect();
        $c=Challenge::sole();
        $this->assertSame('draft',$c->status); $this->assertSame('DRAFT',$c->review_status);
        $this->get("/retos/{$c->slug}")->assertNotFound();
        $this->post("/partner/{$partner->slug}/retos/{$c->slug}/enviar")->assertRedirect();
        $this->assertSame('SUBMITTED',$c->fresh()->review_status);
        $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->post("/admin/retos/{$c->slug}/revision-editorial",['decision'=>'CHANGES_REQUESTED','comment'=>'Aclarar instrucciones'])->assertRedirect();
        $this->assertSame('CHANGES_REQUESTED',$c->fresh()->review_status);
        $this->actingAs($owner)->put("/partner/{$partner->slug}/retos/{$c->slug}",$this->payload(['instructions'=>'Publica una foto']))->assertRedirect();
        $this->post("/partner/{$partner->slug}/retos/{$c->slug}/enviar")->assertRedirect();
        $this->actingAs($admin)->post("/admin/retos/{$c->slug}/revision-editorial",['decision'=>'APPROVED'])->assertRedirect();
        $this->assertSame('APPROVED',$c->fresh()->review_status);
        $this->get("/retos/{$c->slug}")->assertNotFound();
    }
    public function test_partner_cannot_use_another_partners_benefit(): void {
        $partner=Partner::factory()->create();$other=Partner::factory()->create();
        $benefit=Benefit::factory()->forPartner($other)->create(['access_mode'=>'social_challenge_grant']);
        $owner=User::factory()->create();$owner->partners()->attach($partner,['role'=>'owner']);
        $this->actingAs($owner)->post("/partner/{$partner->slug}/retos",$this->payload(['reward_type'=>'BENEFIT','benefit_id'=>$benefit->id]))->assertSessionHasErrors('benefit_id');
        $this->assertDatabaseCount('challenges',0);
    }
    public function test_public_landing_discovery_and_individual_progress(): void {
        $city=City::where('slug','cochabamba')->sole();
        $c=$this->challenge(['city_id'=>$city->id]);
        $other=$this->challenge(['review_status'=>'DRAFT']);
        $user=User::factory()->create();
        $this->entry($c,$user,327);
        $this->actingAs($user)->get("/retos/{$c->slug}")->assertInertia(fn (Assert $page)=>$page
            ->component('social-challenges/show')->where('challenge.qualification_target',500)
            ->has('entries',1)->where('ranking',null));
        $this->get("/retos/{$other->slug}")->assertNotFound();
        $this->withCookie('selected_city',$city->slug)->get('/explorar?type=challenges')->assertInertia(fn (Assert $page)=>$page
            ->has('opportunities',1)->where('opportunities.0.type','CHALLENGE'));
    }
    public function test_ranking_uses_one_best_eligible_entry_per_participant_and_visibility(): void {
        $c=$this->challenge(['selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>1,
            'evaluation_mode'=>'AT_CLOSE','ranking_visibility'=>'PUBLIC']);
        $a=User::factory()->create(['name'=>'Ana Pérez']);$b=User::factory()->create(['name'=>'Beto Gómez']);
        $this->entry($c,$a,600);$this->entry($c,$a,800);$this->entry($c,$b,450);
        $rank=app(ChallengeRanking::class)->forLanding($c,\Illuminate\Http\Request::create('/retos'));
        $this->assertCount(1,$rank['rows']);$this->assertSame(800,$rank['rows'][0]['score']);
        $this->assertSame('Ana P.',$rank['rows'][0]['name']);$this->assertNotNull($rank['updated_at']);
        $c->update(['ranking_visibility'=>'PARTICIPANTS_ONLY']);
        $this->assertNull(app(ChallengeRanking::class)->forLanding($c,\Illuminate\Http\Request::create('/retos')));
        $c->update(['ranking_visibility'=>'NONE']);
        $this->assertNull(app(ChallengeRanking::class)->forLanding($c,\Illuminate\Http\Request::create('/retos')));
    }
    public function test_scheduler_queues_only_stale_top_n_social_entries(): void {
        Queue::fake();
        $top=$this->challenge(['selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>2,
            'evaluation_mode'=>'AT_CLOSE','ranking_visibility'=>'PUBLIC']);
        $stale=$this->entry($top,User::factory()->create(),600);
        $stale->update(['checked_at'=>now()->subMinutes(31)]);
        $individual=$this->challenge();
        $this->entry($individual,User::factory()->create(),600)->update(['checked_at'=>now()->subMinutes(31)]);
        $this->artisan('challenges:refresh-rankings')->assertExitCode(0);
        Queue::assertPushed(RefreshChallengeSocialEntry::class,1);
        $this->assertTrue($stale->fresh()->refresh_pending);
    }
    public function test_first_n_slots_manual_landing_and_membership_return(): void {
        $first=$this->challenge(['selection_type'=>'FIRST_N','winner_limit'=>10,'reward_eligibility'=>'ACTIVE_MEMBERS']);
        $free=User::factory()->create();
        $p=ChallengeParticipation::create(['challenge_id'=>$first->id,'user_id'=>$free->id,'qualification_status'=>'qualified']);
        $this->actingAs($free)->get("/retos/{$first->slug}")->assertInertia(fn (Assert $page)=>$page
            ->where('slots.awarded',0)->where('slots.limit',10)->where('isMember',false));
        $this->assertSame('pending',$p->fresh()->selection_status);
        $this->get('/membresia?journey=SOCIAL_CHALLENGE&action=PARTICIPATE&resource_id='.$first->id)->assertOk();
        $manual=$this->challenge(['evidence_type'=>'MANUAL','qualification_type'=>'MANUAL','qualification_metric'=>null,
            'qualification_target'=>null,'selection_type'=>'MANUAL','winner_limit'=>1,'review_mode'=>'REQUIRED','reward_type'=>'MANUAL_PRIZE','reward_jp_amount'=>null,'manual_prize_description'=>'Premio presencial']);
        $this->get("/retos/{$manual->slug}")->assertInertia(fn (Assert $page)=>$page->where('challenge.evidence_type','MANUAL')->has('entries',0));
        $this->post("/retos/{$manual->slug}/participar")->assertRedirect();
        $this->assertSame(1,ChallengeParticipation::where('challenge_id',$manual->id)->count());
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $page)=>$page->has('challenges',2));
    }
    public function test_rules_cannot_change_after_participation(): void {
        $c=$this->challenge(['status'=>'draft']);
        ChallengeParticipation::create(['challenge_id'=>$c->id,'user_id'=>User::factory()->create()->id]);
        $admin=User::factory()->create(['is_admin'=>true]);
        $data=$this->payload(['slug'=>$c->slug,'status'=>'draft','description'=>'Haz algo','reward_type'=>'JP','reward_jp_amount'=>999,
            'manual_prize_description'=>null,'qualification_type'=>'METRIC_THRESHOLD','qualification_metric'=>'likes','qualification_target'=>500]);
        $this->actingAs($admin)->put("/admin/retos/{$c->slug}",$data)->assertSessionHasErrors('reward_jp_amount');
        $this->assertSame(500,$c->fresh()->reward_jp_amount);
    }
    public function test_closed_ranking_stays_provisional_until_candidate_is_confirmed(): void {
        $c=$this->challenge(['selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>1,
            'evaluation_mode'=>'AT_CLOSE','ranking_visibility'=>'PUBLIC','review_mode'=>'REQUIRED']);
        $user=User::factory()->create();
        $entry=$this->entry($c,$user,700);
        $entry->update(['final_metric_value'=>700,'final_checked_at'=>now()]);
        $p=$entry->participation;
        $p->update(['qualification_status'=>'qualified','selection_status'=>'candidate','selected_entry_id'=>$entry->id]);
        $c->update(['status'=>'closed']);
        $rank=app(ChallengeRanking::class)->forLanding($c,\Illuminate\Http\Request::create('/retos'));
        $this->assertFalse($rank['official']);
        ChallengeRewardGrant::create(['challenge_id'=>$c->id,'participation_id'=>$p->id,'user_id'=>$user->id,
            'reward_type'=>'JP','jp_amount'=>500,'winning_entry_id'=>$entry->id,'status'=>'granted',
            'granted_at'=>now(),'snapshot'=>[]]);
        $p->update(['selection_status'=>'selected']);
        $rank=app(ChallengeRanking::class)->forLanding($c,\Illuminate\Http\Request::create('/retos'));
        $this->assertTrue($rank['official']);
        $this->assertSame(700,$rank['rows'][0]['score']);
    }
    public function test_inflight_inspection_at_close_writes_final_snapshot(): void {
        $c=$this->challenge(['selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>1,
            'evaluation_mode'=>'AT_CLOSE','ranking_visibility'=>'PUBLIC']);
        $entry=$this->entry($c,User::factory()->create(),600);
        $entry->update(['refresh_pending'=>true]);
        $c->update(['status'=>'closed']);
        $this->app->bind(Inspector::class,fn()=>new class implements Inspector {
            public function inspect(ChallengeSocialEntry $entry): InspectionResult {
                return new InspectionResult(['platform'=>'instagram','social_external_id'=>'post-'.$entry->id,
                    'views'=>0,'likes'=>750,'comments'=>0,'data_quality'=>'complete','validation_status'=>'valid','checked_at'=>now()],[]);
            }
        });
        (new RefreshChallengeSocialEntry($entry->id))->handle(app(Inspector::class),app(ChallengeService::class));
        $this->assertSame(750,$entry->fresh()->final_metric_value);
        $this->assertNotNull($entry->fresh()->final_checked_at);
    }
    public function test_expired_social_challenge_closes_and_queues_final_snapshot(): void {
        Queue::fake();
        $expired=$this->challenge(['selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>1,
            'evaluation_mode'=>'AT_CLOSE','ranking_visibility'=>'PUBLIC','ends_at'=>now()->subMinute()]);
        $entry=$this->entry($expired,User::factory()->create(),600);
        $future=$this->challenge(['selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>1,
            'evaluation_mode'=>'AT_CLOSE','ranking_visibility'=>'PUBLIC','ends_at'=>now()->addHour()]);
        $this->artisan('challenges:close-expired')->assertExitCode(0);
        $this->assertSame('closed',$expired->fresh()->status);
        $this->assertSame('open',$future->fresh()->status);
        $this->assertTrue($entry->fresh()->refresh_pending);
        Queue::assertPushed(RefreshChallengeSocialEntry::class,fn ($job)=>$job->entryId===$entry->id && $job->final);
    }
}
