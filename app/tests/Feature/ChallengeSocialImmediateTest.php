<?php
namespace Tests\Feature;

use App\Integrations\ShareContest\InspectionException;
use App\Integrations\ShareContest\InspectionResult;
use App\Integrations\ShareContest\Inspector;
use App\Integrations\ShareContest\ShareContestClient;
use App\Jobs\RefreshChallengeSocialEntry;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ChallengeRewardGrant;
use App\Models\ChallengeSocialEntry;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\SocialEntryRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChallengeSocialImmediateTest extends TestCase {
    use RefreshDatabase;

    private function challenge(array $attributes = []): Challenge {
        return Challenge::create(array_merge([
            'title'=>'#bafabo', 'slug'=>'reto-'.Str::random(8), 'status'=>'open', 'review_status'=>'APPROVED',
            'allowed_platforms'=>['instagram'], 'required_hashtags'=>['#bafabo'], 'required_mentions'=>[],
            'evidence_type'=>'SOCIAL_POST', 'qualification_type'=>'VALID_EVIDENCE', 'selection_type'=>'ALL_QUALIFIED',
            'evaluation_mode'=>'CONTINUOUS', 'review_mode'=>'AUTOMATIC', 'participation_eligibility'=>'ALL_USERS',
            'reward_eligibility'=>'ALL_USERS', 'reward_type'=>'MANUAL_PRIZE', 'manual_prize_description'=>'Premio',
        ], $attributes));
    }

    private function entry(Challenge $challenge, ?User $user = null, array $attributes = []): ChallengeSocialEntry {
        $participation = ChallengeParticipation::firstOrCreate(['challenge_id'=>$challenge->id,'user_id'=>($user ?? User::factory()->create())->id]);
        $reference = (string) Str::ulid();
        return ChallengeSocialEntry::create(array_merge([
            'challenge_id'=>$challenge->id, 'participation_id'=>$participation->id,
            'social_url'=>'https://instagram.com/p/'.$reference, 'normalized_url_hash'=>hash('sha256',$reference),
            'external_reference'=>'test-'.$reference, 'refresh_pending'=>true,
        ], $attributes));
    }

    private function inspect(ChallengeSocialEntry $entry, ?string $caption = '#BAFABO', ?int $likes = 10, string $providerStatus = 'valid'): void {
        $this->app->bind(Inspector::class, fn () => new class($caption, $likes, $providerStatus) implements Inspector {
            public function __construct(private ?string $caption, private ?int $likes, private string $status) {}
            public function inspect(ChallengeSocialEntry $entry): InspectionResult {
                return new InspectionResult([
                    'platform'=>'instagram', 'social_external_id'=>'post-'.$entry->id, 'caption'=>$this->caption,
                    'likes'=>$this->likes, 'views'=>null, 'comments'=>null, 'data_quality'=>'partial',
                    'validation_status'=>$this->status, 'checked_at'=>now(),
                ], ['data'=>['is_public'=>true], 'validation'=>['status'=>$this->status,'checks'=>[]]]);
            }
        });
        (new RefreshChallengeSocialEntry($entry->id))->handle(app(Inspector::class), app(ChallengeService::class));
    }

    private function rule(ChallengeSocialEntry $entry, string $key): string {
        $rule = collect(app(SocialEntryRules::class)->requirements($entry->challenge, $entry))->firstWhere('key', $key);
        return $rule['status'];
    }

    public function test_bafabo_sharecontest_request_and_immediate_verification(): void {
        config(['services.sharecontest.url'=>'https://sharecontest.example', 'services.sharecontest.token'=>'test-token']);
        $challenge=$this->challenge(['max_entries_per_user'=>3]);
        $entries=[
            $this->entry($challenge,null,['social_url'=>'https://www.instagram.com/reels/DcWPm_ZMXPB/']),
            $this->entry($challenge),
            $this->entry($challenge),
        ];
        $response=fn (?string $caption, string $status, int $id) => [
            'request_id'=>'request-'.$id,
            'data'=>['sharecontest_id'=>$id,'platform'=>'instagram','canonical_url'=>'https://instagram.com/reel/'.$id,
                'external_id'=>'post-'.$id,'caption'=>$caption,'is_public'=>true,'published_at'=>null,
                'metrics'=>['views'=>null,'likes'=>10,'comments'=>1],'data_quality'=>'partial','checked_at'=>now()->toIso8601String()],
            'validation'=>['status'=>$status,'checks'=>['hashtags'=>['status'=>$status === 'invalid' ? 'failed' : 'passed']]],
        ];
        Http::fake(['https://sharecontest.example/api/v1/inspect'=>Http::sequence()
            ->push($response('#BaFaBo','valid',101),200)
            ->push($response('Sin la etiqueta','invalid',102),200)
            ->push($response(null,'not_requested',103),200)]);

        foreach ($entries as $entry) {
            (new RefreshChallengeSocialEntry($entry->id))->handle(app(ShareContestClient::class),app(ChallengeService::class));
        }

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request) use ($entries, $challenge): bool {
            $payload=$request->data();
            return $payload['url'] === $entries[0]->social_url
                && $payload['external_reference'] === $entries[0]->external_reference
                && $payload['campaign_reference'] === 'social-challenge-'.$challenge->id
                && $payload['rules'] === ['allowed_platforms'=>['instagram'],'required_hashtags'=>['#bafabo'],'required_mentions'=>[]]
                && !array_key_exists('platform',$payload)
                && !array_key_exists('visibility',$payload);
        });
        $this->assertSame('inspected',$entries[0]->fresh()->inspection_status);
        $this->assertNull($entries[0]->fresh()->integration_error_code);
        $this->assertSame('PASS',$this->rule($entries[0]->fresh(),'hashtag:bafabo'));
        $this->assertSame('valid',$entries[0]->fresh()->validation_status);
        $this->assertSame('qualified',$entries[0]->participation->fresh()->qualification_status);
        $this->assertSame(1,ChallengeRewardGrant::count());
        $this->assertSame('FAIL',$this->rule($entries[1]->fresh(),'hashtag:bafabo'));
        $this->assertSame('invalid',$entries[1]->fresh()->validation_status);
        $this->assertSame('UNKNOWN',$this->rule($entries[2]->fresh(),'hashtag:bafabo'));
        $this->assertSame('review_required',$entries[2]->fresh()->validation_status);
    }

    public function test_timestamp_bounds_are_not_serialized_as_provider_date_rules(): void {
        config(['services.sharecontest.url'=>'https://sharecontest.example', 'services.sharecontest.token'=>'test-token']);
        $challenge=$this->challenge(['published_from'=>now()->subDay(),'published_until'=>now()->addDay()]);
        $entry=$this->entry($challenge);
        Http::fake(['https://sharecontest.example/api/v1/inspect'=>Http::response([
            'data'=>['platform'=>'instagram','caption'=>'#bafabo','data_quality'=>'partial'],
            'validation'=>['status'=>'valid','checks'=>[]],
        ],200)]);
        app(ShareContestClient::class)->inspect($entry);
        Http::assertSent(fn (Request $request) => !array_key_exists('published_from',$request->data()['rules'])
            && !array_key_exists('published_until',$request->data()['rules']));
    }

    public function test_submit_is_single_interactive_entry_with_stable_reference_and_duplicate_protection(): void {
        Queue::fake(); $challenge=$this->challenge(); $user=User::factory()->create();
        $url='https://instagram.com/p/one?utm_source=qa';
        $this->actingAs($user)->post("/retos/{$challenge->slug}/publicaciones",['url'=>$url])->assertRedirect()->assertSessionHas('submitted_entry_id');
        $entry=ChallengeSocialEntry::sole();
        $this->assertSame(1,ChallengeParticipation::count());
        $this->assertNotEmpty($entry->external_reference);
        $this->assertTrue($entry->refresh_pending);
        Queue::assertPushed(RefreshChallengeSocialEntry::class, fn ($job) => $job->entryId === $entry->id && $job->queue === 'social-interactive' && $job->interactive);
        $this->actingAs($user)->post("/retos/{$challenge->slug}/publicaciones",['url'=>'http://INSTAGRAM.COM/p/one'])->assertSessionHasErrors('url');
        $this->assertSame(1,ChallengeSocialEntry::count());
        $this->assertSame(1,ChallengeParticipation::count());
        Queue::assertPushed(RefreshChallengeSocialEntry::class, 1);
    }

    public function test_status_is_owned_scoped_and_has_no_provider_payload_or_other_pii(): void {
        $challenge=$this->challenge(); $entry=$this->entry($challenge); $owner=$entry->participation->user;
        $entry->update(['inspection_status'=>'inspected','refresh_pending'=>false,'platform'=>'instagram','caption'=>'#bafabo',
            'validation_status'=>'valid','sharecontest_payload'=>['data'=>['is_public'=>true],'secret'=>'never expose']]);
        $path="/retos/{$challenge->slug}/publicaciones/{$entry->id}/estado";
        $this->get($path)->assertRedirect();
        $this->actingAs(User::factory()->create())->getJson($path)->assertNotFound();
        $other=$this->challenge();
        $this->actingAs($owner)->getJson("/retos/{$other->slug}/publicaciones/{$entry->id}/estado")->assertNotFound();
        $response=$this->actingAs($owner)->getJson($path)->assertOk()
            ->assertJsonPath('entry_id',$entry->id)->assertJsonPath('requirements.0.status','PASS')
            ->assertJsonPath('participation.id',$entry->participation_id);
        foreach (['sharecontest_payload','caption','author','username','integration_error_code','secret'] as $field) {
            $this->assertStringNotContainsString($field, $response->getContent());
        }
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_hashtag_casefold_missing_and_unavailable_data_have_distinct_results(): void {
        $challenge=$this->challenge(); $entry=$this->entry($challenge);
        $this->inspect($entry, 'Hazlo #BaFaBo');
        $this->assertSame('PASS',$this->rule($entry->fresh(),'hashtag:bafabo'));
        $this->assertSame('valid',$entry->fresh()->validation_status);
        $this->inspect($entry, 'Sin la etiqueta');
        $this->assertSame('FAIL',$this->rule($entry->fresh(),'hashtag:bafabo'));
        $this->assertSame('invalid',$entry->fresh()->validation_status);
        $this->inspect($entry, null);
        $this->assertSame('UNKNOWN',$this->rule($entry->fresh(),'hashtag:bafabo'));
        $this->assertSame('review_required',$entry->fresh()->validation_status);
    }

    public function test_platform_public_visibility_and_mentions_are_checked_without_guessing(): void {
        $challenge=$this->challenge(['required_mentions'=>['@jakawi']]);
        $entry=$this->entry($challenge,null,['caption'=>'#bafabo @JAKAWI','platform'=>'tiktok',
            'inspection_status'=>'inspected','sharecontest_payload'=>['data'=>['is_public'=>false]]]);
        $rules=app(SocialEntryRules::class);
        $this->assertSame('FAIL',$this->rule($entry,'platform'));
        $this->assertSame('FAIL',$this->rule($entry,'public'));
        $this->assertSame('PASS',$this->rule($entry,'mention:jakawi'));
        $this->assertSame('invalid',$rules->validationStatus($challenge,$entry,'valid'));
        $entry->update(['platform'=>null,'sharecontest_payload'=>['data'=>[]]]);
        $this->assertSame('UNKNOWN',$this->rule($entry->fresh(),'platform'));
        $this->assertSame('UNKNOWN',$this->rule($entry->fresh(),'public'));
        $this->assertSame('review_required',$rules->validationStatus($challenge,$entry->fresh(),'valid'));
    }

    public function test_valid_evidence_qualifies_once_and_metric_below_target_remains_valid(): void {
        $challenge=$this->challenge(); $entry=$this->entry($challenge);
        $this->inspect($entry);
        $this->assertSame('qualified',$entry->participation->fresh()->qualification_status);
        $this->assertSame(1,ChallengeRewardGrant::count());
        $this->inspect($entry);
        $this->assertSame(1,ChallengeRewardGrant::count());

        $metric=$this->challenge(['qualification_type'=>'METRIC_THRESHOLD','qualification_metric'=>'likes','qualification_target'=>500]);
        $low=$this->entry($metric);
        $this->inspect($low, '#bafabo',327);
        $this->assertSame('valid',$low->fresh()->validation_status);
        $this->assertSame('pending',$low->participation->fresh()->qualification_status);
        $this->assertSame(1,ChallengeRewardGrant::count());
    }

    public function test_integration_error_does_not_invalidate_entry_and_background_job_uses_social(): void {
        $challenge=$this->challenge(); $entry=$this->entry($challenge);
        (new RefreshChallengeSocialEntry($entry->id))->failed(new InspectionException('connection', false));
        $this->assertSame('failed',$entry->fresh()->inspection_status);
        $this->assertNotSame('invalid',$entry->fresh()->validation_status);
        $this->assertFalse($entry->fresh()->refresh_pending);
        $this->assertSame('social',(new RefreshChallengeSocialEntry($entry->id,true))->queue);
        $this->assertSame('social-interactive',(new RefreshChallengeSocialEntry($entry->id,false,true))->queue);
    }
}
