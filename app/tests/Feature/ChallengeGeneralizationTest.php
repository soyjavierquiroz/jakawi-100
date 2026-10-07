<?php
namespace Tests\Feature;

use App\Integrations\ShareContest\InspectionResult;
use App\Integrations\ShareContest\InspectionException;
use App\Integrations\ShareContest\Inspector;
use App\Jobs\RefreshChallengeSocialEntry;
use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ChallengeRewardGrant;
use App\Models\ChallengeSocialEntry;
use App\Models\Membership;
use App\Models\Location;
use App\Models\Redemption;
use App\Models\Partner;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\MembershipService;
use App\Services\RedemptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChallengeGeneralizationTest extends TestCase {
    use RefreshDatabase;

    private function challenge(array $attrs=[]): Challenge {
        return Challenge::create(array_merge([
            'title'=>'Reto', 'slug'=>'reto-'.Str::random(8), 'status'=>'open', 'description'=>'Participa',
            'allowed_platforms'=>['instagram'], 'required_hashtags'=>[], 'required_mentions'=>[],
            'evidence_type'=>'SOCIAL_POST', 'qualification_type'=>'VALID_EVIDENCE', 'selection_type'=>'ALL_QUALIFIED',
            'evaluation_mode'=>'CONTINUOUS', 'review_mode'=>'AUTOMATIC', 'participation_eligibility'=>'ALL_USERS', 'reward_eligibility'=>'ALL_USERS',
            'reward_type'=>'MANUAL_PRIZE', 'manual_prize_description'=>'Premio',
        ], $attrs));
    }
    private function participation(Challenge $c, ?User $user=null): ChallengeParticipation {
        $user ??= User::factory()->create();
        return ChallengeParticipation::create(['challenge_id'=>$c->id,'user_id'=>$user->id]);
    }
    private function entry(ChallengeParticipation $p, array $attrs=[]): ChallengeSocialEntry {
        $ref=Str::ulid();
        return ChallengeSocialEntry::create(array_merge(['challenge_id'=>$p->challenge_id,'participation_id'=>$p->id,
            'social_url'=>'https://instagram.com/p/'.$ref, 'normalized_url_hash'=>hash('sha256',(string)$ref),
            'external_reference'=>'test-'.$ref, 'validation_status'=>'valid', 'checked_at'=>now(), 'refresh_pending'=>false],$attrs));
    }
    private function adminPayload(array $attrs=[]): array {
        return array_merge(['title'=>'Reto admin','slug'=>'reto-admin','status'=>'draft','description'=>'Participa',
            'allowed_platforms'=>['instagram'],'required_hashtags'=>[],'required_mentions'=>[],
            'evidence_type'=>'SOCIAL_POST','qualification_type'=>'VALID_EVIDENCE','qualification_metric'=>null,'qualification_target'=>null,
            'selection_type'=>'ALL_QUALIFIED','selection_metric'=>null,'winner_limit'=>null,'evaluation_mode'=>'CONTINUOUS',
            'review_mode'=>'AUTOMATIC','participation_eligibility'=>'ALL_USERS','reward_eligibility'=>'ALL_USERS','max_entries_per_user'=>null,
            'reward_type'=>'MANUAL_PRIZE','manual_prize_description'=>'Premio'], $attrs);
    }

    public function test_multiple_entries_one_participation_immutable_references_and_limits(): void {
        Queue::fake(); $c=$this->challenge(['max_entries_per_user'=>2]); $u=User::factory()->create();
        $this->actingAs($u)->post("/retos/{$c->slug}/publicaciones",['url'=>'https://instagram.com/p/one?utm_source=x'])->assertRedirect();
        $this->actingAs($u)->post("/retos/{$c->slug}/publicaciones",['url'=>'https://instagram.com/p/two'])->assertRedirect();
        $this->assertSame(1,ChallengeParticipation::count()); $this->assertSame(2,ChallengeSocialEntry::count());
        $entries=ChallengeSocialEntry::orderBy('id')->get(); $this->assertNotSame($entries[0]->external_reference,$entries[1]->external_reference);
        $this->assertSame(2,Queue::pushed(RefreshChallengeSocialEntry::class)->count());
        $this->actingAs($u)->post("/retos/{$c->slug}/publicaciones",['url'=>'https://instagram.com/p/three'])->assertSessionHasErrors('url');
        $this->expectException(\DomainException::class); $entries[0]->update(['social_url'=>'https://instagram.com/p/changed']);
    }
    public function test_duplicate_url_across_users_and_identity_from_sharecontest_are_rejected(): void {
        Queue::fake(); $c=$this->challenge(); $a=User::factory()->create(); $b=User::factory()->create();
        $this->actingAs($a)->post("/retos/{$c->slug}/publicaciones",['url'=>'https://instagram.com/p/one?utm_source=x'])->assertRedirect();
        $this->actingAs($b)->post("/retos/{$c->slug}/publicaciones",['url'=>'http://INSTAGRAM.COM/p/one'])->assertSessionHasErrors('url');
        $this->assertSame(1,ChallengeSocialEntry::count());
        $first=ChallengeSocialEntry::sole(); $first->update(['platform'=>'instagram','social_external_id'=>'post-one']);
        $p=$this->participation($c,$b); $second=$this->entry($p,['refresh_pending'=>true]);
        $this->app->bind(Inspector::class,fn()=>new class implements Inspector {
            public function inspect(ChallengeSocialEntry $entry): InspectionResult { return new InspectionResult(['platform'=>'instagram','social_external_id'=>'post-one','views'=>null,'likes'=>3,'comments'=>null,'data_quality'=>'partial','validation_status'=>'valid','checked_at'=>now(),'sharecontest_id'=>'sc-one'],['validation'=>['status'=>'valid','checks'=>[]]]); }
        });
        (new RefreshChallengeSocialEntry($second->id))->handle(app(Inspector::class),app(ChallengeService::class));
        $this->assertSame('review_required',$second->fresh()->validation_status);
        $this->assertSame('duplicate_social_identity',$second->fresh()->integration_error_code);
        $this->assertNull($second->fresh()->social_external_id);
        $this->assertSame(2,ChallengeSocialEntry::count()); $this->assertSame(2,ChallengeParticipation::count());
    }
    public function test_refresh_updates_only_target_entry_and_never_creates_participation(): void {
        $c=$this->challenge(); $p=$this->participation($c); $first=$this->entry($p); $other=$this->entry($p);
        $reference=$first->external_reference; $otherReference=$other->external_reference;
        $this->app->bind(Inspector::class,fn()=>new class implements Inspector {
            public function inspect(ChallengeSocialEntry $entry): InspectionResult { return new InspectionResult(['platform'=>'instagram','social_external_id'=>'post-'.$entry->id,'views'=>10,'likes'=>20,'comments'=>0,'data_quality'=>'complete','validation_status'=>'valid','checked_at'=>now()],['validation'=>['status'=>'valid','checks'=>[]]]); }
        });
        (new RefreshChallengeSocialEntry($first->id))->handle(app(Inspector::class),app(ChallengeService::class));
        $this->assertSame(20,$first->fresh()->likes); $this->assertNull($other->fresh()->likes);
        $this->assertSame($reference,$first->fresh()->external_reference); $this->assertSame($otherReference,$other->fresh()->external_reference);
        $this->assertSame(1,ChallengeParticipation::count()); $this->assertSame(2,ChallengeSocialEntry::count());
    }
    public function test_any_qualifying_entry_qualifies_once_and_records_entry(): void {
        $c=$this->challenge(['qualification_type'=>'METRIC_THRESHOLD','qualification_metric'=>'likes','qualification_target'=>500]);
        $p=$this->participation($c); $low=$this->entry($p,['likes'=>100]); $high=$this->entry($p,['likes'=>600]);
        $service=app(ChallengeService::class); $service->evaluate($p); $service->evaluate($p);
        $this->assertSame('qualified',$p->fresh()->qualification_status); $this->assertSame($high->id,$p->fresh()->qualified_entry_id);
        $this->assertSame(1,ChallengeRewardGrant::count());
        $this->entry($p,['likes'=>700]); $service->evaluate($p);
        $this->assertSame(1,ChallengeRewardGrant::count());
    }
    public function test_first_n_counts_participants_and_free_user_does_not_reserve_slot(): void {
        $c=$this->challenge(['qualification_type'=>'METRIC_THRESHOLD','qualification_metric'=>'likes','qualification_target'=>500,
            'selection_type'=>'FIRST_N','winner_limit'=>1,'reward_eligibility'=>'ACTIVE_MEMBERS']);
        $free=$this->participation($c); $this->entry($free,['likes'=>600]); app(ChallengeService::class)->evaluate($free);
        $this->assertSame('qualified',$free->fresh()->qualification_status); $this->assertSame('pending',$free->fresh()->selection_status); $this->assertSame(0,ChallengeRewardGrant::count());
        $member=$this->participation($c); $admin=User::factory()->create(); app(MembershipService::class)->activate($member->user,$admin);
        $this->entry($member,['likes'=>700]); $this->entry($member,['likes'=>800]); app(ChallengeService::class)->evaluate($member);
        $this->assertSame(1,ChallengeRewardGrant::count()); $this->assertSame($member->id,ChallengeRewardGrant::sole()->participation_id);
        app(MembershipService::class)->activate($free->user,$admin);
        $this->assertSame(1,ChallengeRewardGrant::count()); $this->assertSame('pending',$free->fresh()->selection_status);
    }
    public function test_rejected_first_n_candidate_releases_slot_to_next_participant(): void {
        $c=$this->challenge(['selection_type'=>'FIRST_N','winner_limit'=>1,'review_mode'=>'REQUIRED']);
        $first=$this->participation($c); $this->entry($first); app(ChallengeService::class)->evaluate($first);
        $second=$this->participation($c); $this->entry($second); app(ChallengeService::class)->evaluate($second);
        $this->assertSame('candidate',$first->fresh()->selection_status);
        $this->assertSame('pending',$second->fresh()->selection_status);
        $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->post("/admin/retos/{$c->slug}/participaciones/{$first->id}/revisar",['decision'=>'rejected'])->assertRedirect();
        $this->assertSame('pending',$first->fresh()->selection_status);
        $this->assertSame('candidate',$second->fresh()->selection_status);
        $this->assertSame(0,ChallengeRewardGrant::count());
    }
    public function test_member_activation_rechecks_qualified_participant_and_grants_if_slot_open(): void {
        $c=$this->challenge(['reward_eligibility'=>'ACTIVE_MEMBERS']); $p=$this->participation($c); $this->entry($p); app(ChallengeService::class)->evaluate($p);
        $this->assertSame('qualified',$p->fresh()->qualification_status); $this->assertSame(0,ChallengeRewardGrant::count());
        app(MembershipService::class)->activate($p->user,User::factory()->create());
        $this->assertSame(1,ChallengeRewardGrant::count());
    }
    public function test_top_n_uses_best_eligible_entry_per_participant_and_threshold(): void {
        $c=$this->challenge(['qualification_type'=>'METRIC_THRESHOLD','qualification_metric'=>'likes','qualification_target'=>500,
            'selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>2,'evaluation_mode'=>'AT_CLOSE','review_mode'=>'REQUIRED','status'=>'closed']);
        $a=$this->participation($c); $a1=$this->entry($a,['likes'=>600,'final_metric_value'=>600,'final_checked_at'=>now()]); $a2=$this->entry($a,['likes'=>2300,'final_metric_value'=>2300,'final_checked_at'=>now()]);
        $b=$this->participation($c); $this->entry($b,['likes'=>800,'final_metric_value'=>800,'final_checked_at'=>now()]);
        $below=$this->participation($c); $this->entry($below,['likes'=>400,'final_metric_value'=>400,'final_checked_at'=>now()]);
        $null=$this->participation($c); $this->entry($null,['likes'=>null,'final_metric_value'=>null,'final_checked_at'=>now()]);
        app(ChallengeService::class)->finalize($c);
        $this->assertSame('candidate',$a->fresh()->selection_status); $this->assertSame($a2->id,$a->fresh()->selected_entry_id);
        $this->assertSame('candidate',$b->fresh()->selection_status); $this->assertSame('pending',$below->fresh()->selection_status);
        $this->assertSame('review_required',$null->fresh()->qualification_status); $this->assertSame(0,ChallengeRewardGrant::count());
        $admin=User::factory()->create(['is_admin'=>true]); $this->actingAs($admin)->post("/admin/retos/{$c->slug}/participaciones/{$a->id}/ganador")->assertRedirect();
        $this->assertSame($a2->id,ChallengeRewardGrant::sole()->winning_entry_id);
        $this->actingAs($admin)->post("/admin/retos/{$c->slug}/participaciones/{$a->id}/ganador")->assertSessionHasErrors('winner');
    }
    public function test_top_n_ties_resolve_by_qualification_time_then_participation_id(): void {
        $c=$this->challenge(['selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>1,'evaluation_mode'=>'AT_CLOSE','status'=>'closed']);
        $a=$this->participation($c); $b=$this->participation($c);
        $this->entry($a,['likes'=>100,'final_metric_value'=>100,'final_checked_at'=>now()]);
        $this->entry($b,['likes'=>100,'final_metric_value'=>100,'final_checked_at'=>now()]);
        app(ChallengeService::class)->finalize($c);
        $this->assertSame('selected',$a->fresh()->selection_status); $this->assertSame('pending',$b->fresh()->selection_status);
        $this->assertSame(1,ChallengeRewardGrant::count());
    }
    public function test_failed_final_inspection_goes_to_review_and_does_not_block_other_entries(): void {
        $c=$this->challenge(['selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>1,'evaluation_mode'=>'AT_CLOSE','status'=>'closed']);
        $good=$this->participation($c); $this->entry($good,['likes'=>900,'final_metric_value'=>900,'final_checked_at'=>now()]);
        $bad=$this->participation($c); $entry=$this->entry($bad,['refresh_pending'=>true]);
        (new RefreshChallengeSocialEntry($entry->id,true))->failed(new InspectionException('unavailable',false));
        $this->assertSame('review_required',$entry->fresh()->validation_status);
        $this->assertNotNull($entry->fresh()->final_checked_at);
        $this->assertSame('selected',$good->fresh()->selection_status);
        $this->assertSame('review_required',$bad->fresh()->qualification_status);
        $this->assertSame(1,ChallengeRewardGrant::count());
    }
    public function test_manual_evidence_has_no_sharecontest_and_requires_admin_review(): void {
        Queue::fake(); $c=$this->challenge(['evidence_type'=>'MANUAL','qualification_type'=>'MANUAL','selection_type'=>'MANUAL','review_mode'=>'REQUIRED']);
        $u=User::factory()->create(); $this->actingAs($u)->post("/retos/{$c->slug}/participar")->assertRedirect();
        $p=ChallengeParticipation::sole(); $this->assertSame(0,ChallengeSocialEntry::count()); Queue::assertNotPushed(RefreshChallengeSocialEntry::class);
        $admin=User::factory()->create(['is_admin'=>true]); $this->actingAs($admin)->post("/admin/retos/{$c->slug}/participaciones/{$p->id}/revisar",['decision'=>'approved'])->assertRedirect();
        $this->assertSame('qualified',$p->fresh()->qualification_status); $this->assertSame(0,ChallengeRewardGrant::count());
        $this->actingAs($admin)->post("/admin/retos/{$c->slug}/participaciones/{$p->id}/ganador")->assertRedirect();
        $this->assertSame(1,ChallengeRewardGrant::count());
    }
    public function test_reward_types_keep_single_grant_and_jp_ledger(): void {
        $c=$this->challenge(['reward_type'=>'JP','reward_jp_amount'=>500,'manual_prize_description'=>null]); $p=$this->participation($c); $this->entry($p);
        app(ChallengeService::class)->evaluate($p); app(ChallengeService::class)->evaluate($p);
        $grant=ChallengeRewardGrant::sole(); $this->assertSame('JP',$grant->reward_type); $this->assertSame(500,$grant->jp_amount);
        $this->assertSame($grant->id,RewardTransaction::sole()->social_challenge_reward_grant_id);
        $this->assertSame(1,ChallengeRewardGrant::count());
        $partner=Partner::factory()->create(); $benefit=Benefit::factory()->forPartner($partner)->create(['access_mode'=>'social_challenge_grant']);
        $other=$this->challenge(['partner_id'=>$partner->id,'reward_type'=>'BENEFIT','benefit_id'=>$benefit->id,'manual_prize_description'=>null]);
        $bp=$this->participation($other); $this->entry($bp); app(ChallengeService::class)->evaluate($bp);
        $this->assertSame($benefit->id,$bp->grant->benefit_id);
    }
    public function test_exclusive_benefit_redemption_and_manual_prize_fulfillment_stay_intact(): void {
        $partner=Partner::factory()->published()->create();
        $location=Location::factory()->published()->withPartner($partner)->create(); $location->setRedemptionPin('123456'); $location->save();
        $benefit=Benefit::factory()->published()->forPartner($partner)->create(['access_mode'=>'social_challenge_grant','applies_to_all_locations'=>true]);
        $c=$this->challenge(['partner_id'=>$partner->id,'reward_type'=>'BENEFIT','benefit_id'=>$benefit->id,'manual_prize_description'=>null]);
        $u=User::factory()->create(); $p=$this->participation($c,$u); $this->entry($p); app(ChallengeService::class)->evaluate($p);
        $grant=$p->fresh()->grant; $this->assertSame($benefit->id,$grant->benefit_id);
        $this->actingAs(User::factory()->create())->post("/beneficios/{$benefit->slug}/canjear",['location_id'=>$location->id,'social_challenge_reward_grant_id'=>$grant->id])->assertSessionHasErrors('redemption');
        $this->actingAs($u)->post("/beneficios/{$benefit->slug}/canjear",['location_id'=>$location->id,'social_challenge_reward_grant_id'=>$grant->id])->assertRedirect();
        $redemption=Redemption::sole(); $this->assertNull($redemption->membership_id);
        app(RedemptionService::class)->confirm($redemption->code,'123456');
        $this->assertSame('fulfilled',$grant->fresh()->status);
        $manual=$this->challenge(['evidence_type'=>'MANUAL','qualification_type'=>'MANUAL','selection_type'=>'MANUAL','review_mode'=>'REQUIRED']);
        $mp=$this->participation($manual); $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->post("/admin/retos/{$manual->slug}/participaciones/{$mp->id}/revisar",['decision'=>'approved'])->assertRedirect();
        $this->actingAs($admin)->post("/admin/retos/{$manual->slug}/participaciones/{$mp->id}/ganador")->assertRedirect();
        $this->actingAs($admin)->post("/admin/retos/{$manual->slug}/participaciones/{$mp->id}/entregar")->assertRedirect();
        $this->assertSame('fulfilled',$mp->grant->fresh()->status);
    }
    public function test_admin_contract_validation_and_public_rules(): void {
        $admin=User::factory()->create(['is_admin'=>true]); $this->actingAs($admin);
        $this->post('/admin/retos',$this->adminPayload(['qualification_type'=>'METRIC_THRESHOLD','qualification_metric'=>'likes','qualification_target'=>500,
            'selection_type'=>'TOP_N','selection_metric'=>'likes','winner_limit'=>10,'evaluation_mode'=>'AT_CLOSE','review_mode'=>'REQUIRED']))->assertRedirect()->assertSessionHasNoErrors();
        $c=Challenge::sole(); $this->assertSame('TOP_N',$c->selection_type); $this->assertSame(500,$c->rulesContract()['qualification_target']);
        $this->post('/admin/retos',$this->adminPayload(['slug'=>'bad','selection_type'=>'TOP_N','evaluation_mode'=>'CONTINUOUS']))->assertSessionHasErrors('selection_type');
    }
}
