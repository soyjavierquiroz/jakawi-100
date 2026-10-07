<?php
namespace Tests\Feature;
use App\Integrations\ShareContest\InspectionException;
use App\Integrations\ShareContest\Inspector;
use App\Jobs\RefreshSocialChallengeParticipation;
use App\Models\Benefit;
use App\Models\SocialChallenge;
use App\Models\SocialChallengeParticipation;
use App\Models\SocialChallengeRewardGrant;
use App\Models\User;
use App\Services\SocialChallengeQualificationService;
use Database\Factories\BenefitFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
class SocialChallengeTest extends TestCase {
    use RefreshDatabase;
    private function challenge(array $attrs=[]): SocialChallenge { return SocialChallenge::create(array_merge(['title'=>'Un reto','slug'=>'un-reto','description'=>'Participa','status'=>'open','allowed_platforms'=>['instagram'],'required_hashtags'=>[],'required_mentions'=>[],'qualification_mode'=>'VALID_POST','evaluation_mode'=>'CONTINUOUS','reward_type'=>'MANUAL_PRIZE','manual_prize_description'=>'Un premio'], $attrs)); }
    private function participation(SocialChallenge $c,?User $u=null,array $attrs=[]): SocialChallengeParticipation { $u??=User::factory()->create(); return SocialChallengeParticipation::create(array_merge(['social_challenge_id'=>$c->id,'user_id'=>$u->id,'social_url'=>'https://instagram.com/p/'.uniqid(),'normalized_url_hash'=>hash('sha256',uniqid()),'external_reference'=>'test-'.uniqid()],$attrs)); }
    public function test_public_submission_and_stable_reference(): void { Queue::fake(); $c=$this->challenge(); $u=User::factory()->create(); $this->get('/retos/un-reto')->assertOk(); $this->actingAs($u)->post('/retos/un-reto/publicaciones',['url'=>'https://instagram.com/p/abc'])->assertRedirect(); $p=SocialChallengeParticipation::firstOrFail(); $ref=$p->external_reference; $this->assertTrue($p->refresh_pending); Queue::assertPushed(RefreshSocialChallengeParticipation::class); $this->actingAs($u)->post('/retos/un-reto/publicaciones',['url'=>'https://instagram.com/p/other'])->assertSessionHasErrors('url'); $this->assertSame($ref,$p->fresh()->external_reference); }
    public function test_client_preserves_null_and_business_validation(): void { $c=$this->challenge();$p=$this->participation($c); config(['services.sharecontest.url'=>'https://example.test','services.sharecontest.token'=>'test']); foreach(['valid','invalid','review_required'] as $status) { Http::swap(new \Illuminate\Http\Client\Factory); Http::fake(['example.test/*'=>Http::response(['success'=>true,'request_id'=>'r','data'=>['platform'=>'instagram','external_id'=>'abc','metrics'=>['views'=>null,'likes'=>5,'comments'=>null],'data_quality'=>'partial'],'validation'=>['status'=>$status,'checks'=>[]]],200)]); $result=app(Inspector::class)->inspect($p); $this->assertNull($result->data['views']);$this->assertSame($status,$result->data['validation_status']); Http::preventStrayRequests(); } }
    public function test_client_classifies_http_failures(): void { $c=$this->challenge();$p=$this->participation($c); config(['services.sharecontest.url'=>'https://example.test','services.sharecontest.token'=>'test']); foreach([401=>false,422=>false,429=>true,500=>true] as $status=>$retryable) { Http::swap(new \Illuminate\Http\Client\Factory); Http::fake(['example.test/*'=>Http::response([], $status)]); try { app(Inspector::class)->inspect($p); $this->fail('Expected exception'); } catch(InspectionException $e) { $this->assertSame($retryable,$e->retryable); } } }
    public function test_qualification_and_grant_idempotency(): void { $c=$this->challenge(); $p=$this->participation($c,null,['validation_status'=>'valid']); $service=app(SocialChallengeQualificationService::class); $service->evaluate($p);$service->evaluate($p);$this->assertSame('qualified',$p->fresh()->qualification_status);$this->assertSame(1,SocialChallengeRewardGrant::count()); $p->update(['validation_status'=>'invalid']);$service->evaluate($p);$this->assertSame('not_qualified',$p->fresh()->qualification_status); }
    public function test_threshold_null_pending_and_target(): void { $c=$this->challenge(['qualification_mode'=>'METRIC_THRESHOLD','metric'=>'likes','target'=>500]);$p=$this->participation($c,null,['validation_status'=>'valid']);$service=app(SocialChallengeQualificationService::class);$service->evaluate($p);$this->assertSame('review_required',$p->fresh()->qualification_status);$p->update(['likes'=>327]);$service->evaluate($p);$this->assertSame('pending',$p->fresh()->qualification_status);$p->update(['likes'=>500]);$service->evaluate($p);$this->assertSame('qualified',$p->fresh()->qualification_status); }
    public function test_admin_only_and_manual_prize(): void { $c=$this->challenge(['qualification_mode'=>'MANUAL']);$p=$this->participation($c);$u=User::factory()->create();$this->actingAs($u)->get('/admin/retos')->assertForbidden();$admin=User::factory()->create(['is_admin'=>true]);$this->actingAs($admin)->post("/admin/retos/{$c->slug}/participaciones/{$p->id}/revisar",['decision'=>'approved'])->assertRedirect();$this->assertSame('granted',$p->grant->status);$this->actingAs($admin)->post("/admin/retos/{$c->slug}/participaciones/{$p->id}/entregar")->assertRedirect();$this->assertSame('fulfilled',$p->grant->fresh()->status); }
    public function test_exclusive_benefit_requires_grant_and_is_consumed_only_on_confirm(): void {
        $partner=\App\Models\Partner::factory()->published()->create();
        $location=\App\Models\Location::factory()->published()->withPartner($partner)->create(); $location->setRedemptionPin('123456'); $location->save();
        $benefit=Benefit::factory()->published()->forPartner($partner)->create(['access_mode'=>'social_challenge_grant','applies_to_all_locations'=>true]);
        $c=$this->challenge(['partner_id'=>$partner->id,'reward_type'=>'BENEFIT','benefit_id'=>$benefit->id,'manual_prize_description'=>null]);
        $u=User::factory()->create(); $stranger=User::factory()->create(); $p=$this->participation($c,$u,['validation_status'=>'valid']);
        $grant=app(SocialChallengeQualificationService::class)->evaluate($p)->grant;
        $this->assertSame(0,\App\Models\Benefit::available()->publicAccess()->whereKey($benefit->id)->count());
        $this->actingAs($stranger)->get('/beneficios/'.$benefit->slug)->assertNotFound();
        $this->actingAs($stranger)->post('/beneficios/'.$benefit->slug.'/canjear',['location_id'=>$location->id,'social_challenge_reward_grant_id'=>$grant->id])->assertSessionHasErrors('redemption');
        $this->actingAs($u)->post('/beneficios/'.$benefit->slug.'/canjear',['location_id'=>$location->id,'social_challenge_reward_grant_id'=>$grant->id])->assertRedirect();
        $redemption=\App\Models\Redemption::sole(); $this->assertNull($redemption->membership_id); $this->assertSame('granted',$grant->fresh()->status);
        app(\App\Services\RedemptionService::class)->confirm($redemption->code,'123456');
        $this->assertSame('fulfilled',$grant->fresh()->status); $this->assertSame('confirmed',$redemption->fresh()->status);
        app(\App\Services\RedemptionService::class)->confirm($redemption->code,'123456');
        $this->assertSame(1,\App\Models\Redemption::where('status','confirmed')->count());
    }
    public function test_ranked_close_snapshots_and_admin_confirms_winner(): void {
        $c=$this->challenge(['qualification_mode'=>'RANKED','evaluation_mode'=>'AT_CLOSE','metric'=>'views','winner_count'=>1]);
        $p=$this->participation($c,null,['validation_status'=>'valid','views'=>200]);
        $null=$this->participation($c,null,['validation_status'=>'valid','views'=>null]);
        $admin=User::factory()->create(['is_admin'=>true]); \Illuminate\Support\Facades\Queue::fake();
        $this->actingAs($admin)->post('/admin/retos/un-reto/cerrar')->assertRedirect();
        $this->assertSame('closed',$c->fresh()->status);
        $this->assertSame(2,\Illuminate\Support\Facades\Queue::pushed(RefreshSocialChallengeParticipation::class)->count());
        $p->update(['final_metric_value'=>200,'final_checked_at'=>now()]);
        $null->update(['final_metric_value'=>null,'final_checked_at'=>now()]);
        $this->actingAs($admin)->post("/admin/retos/un-reto/participaciones/{$null->id}/ganador")->assertSessionHasErrors('winner');
        $this->actingAs($admin)->post("/admin/retos/un-reto/participaciones/{$p->id}/ganador")->assertRedirect();
        $this->assertSame(1,SocialChallengeRewardGrant::count());
    }

    public function test_refresh_updates_same_row_and_duplicate_identity_requires_review(): void {
        $c=$this->challenge(); $first=$this->participation($c,null,['platform'=>'instagram','social_external_id'=>'post-one']);
        $second=$this->participation($c,null,['refresh_pending'=>true]);
        $this->app->bind(Inspector::class,fn()=>new class implements Inspector {
            public function inspect(SocialChallengeParticipation $p): \App\Integrations\ShareContest\InspectionResult {
                return new \App\Integrations\ShareContest\InspectionResult(['platform'=>'instagram','social_external_id'=>'post-one','views'=>null,'likes'=>3,'comments'=>null,'data_quality'=>'partial','validation_status'=>'valid','checked_at'=>now(),'sharecontest_id'=>'sc-one'],['validation'=>['status'=>'valid','checks'=>[]]]);
            }
        });
        (new RefreshSocialChallengeParticipation($second->id))->handle(app(Inspector::class),app(SocialChallengeQualificationService::class));
        $this->assertSame(2,SocialChallengeParticipation::count()); $this->assertSame('review_required',$second->fresh()->validation_status); $this->assertSame('review_required',$second->fresh()->qualification_status); $this->assertNull($second->fresh()->views); $this->assertSame('post-one',$first->fresh()->social_external_id); $this->assertFalse($second->fresh()->refresh_pending);
    }
    public function test_user_refresh_limits_and_keeps_url(): void {
        $c=$this->challenge(); $u=User::factory()->create(); $p=$this->participation($c,$u); Queue::fake();
        $this->actingAs($u)->post('/retos/un-reto/actualizar')->assertRedirect();
        $this->actingAs($u)->post('/retos/un-reto/actualizar')->assertSessionHasErrors('refresh');
        $this->assertSame(1,SocialChallengeParticipation::count()); $this->assertSame($p->social_url,$p->fresh()->social_url); $this->assertSame($p->external_reference,$p->fresh()->external_reference);
    }
    public function test_client_timeout_is_retryable(): void {
        $c=$this->challenge(); $p=$this->participation($c); config(['services.sharecontest.url'=>'https://example.test','services.sharecontest.token'=>'test']);
        Http::fake(fn()=>throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        try { app(Inspector::class)->inspect($p); $this->fail('Expected timeout'); } catch (InspectionException $e) { $this->assertSame('connection',$e->errorCode); $this->assertTrue($e->retryable); }
    }

}
