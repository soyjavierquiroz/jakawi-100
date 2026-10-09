<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\AttributionTouch;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\CrmContactLink;
use App\Models\CrmDelivery;
use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\Membership;
use App\Models\MembershipPurchaseRequest;
use App\Models\Partner;
use App\Models\ProgramApplication;
use App\Models\Redemption;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\User;
use App\Services\Crm\CrmConfiguration;
use App\Services\Crm\CrmContactProjectionService;
use App\Services\Crm\CrmDeliveryDispatcher;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmFoundationTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['cities'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['crm.enabled' => true, 'crm.provider' => 'fluentcrm', 'crm.bridge_url' => 'https://crm.jakawi.com/wp-json/jakawi-fluentcrm/v1/events', 'crm.secret' => str_repeat('test', 8), 'jakawi.analytics.enabled' => false]);
        Http::preventStrayRequests();
        $this->app['request']->setLaravelSession($this->app['session.store']);
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    private function signup(array $extra = []): User
    {
        return app(CreateNewUser::class)->create($extra + ['name' => 'CRM User', 'email' => 'crm@example.test', 'whatsapp' => '71234567']);
    }

    public function test_disabled_and_incomplete_configuration_fail_closed(): void
    {
        config(['crm.enabled' => false]);
        $this->signup();
        $this->assertDatabaseCount('crm_deliveries', 0);
        $this->assertSame(0, app(CrmDeliveryDispatcher::class)->run());
        config(['crm.enabled' => true, 'crm.secret' => '']);
        $this->assertFalse(app(CrmConfiguration::class)->ready());
        config(['crm.secret' => str_repeat('a', 32), 'crm.bridge_url' => 'http://crm.jakawi.com/wp-json/jakawi-fluentcrm/v1/events']);
        $this->assertFalse(app(CrmConfiguration::class)->ready());
        Http::assertNothingSent();
    }

    public function test_signup_is_after_commit_encrypted_and_single_opt_in(): void
    {
        DB::beginTransaction();
        $u = $this->signup(['marketing_opt_in' => '1']);
        $this->assertDatabaseCount('crm_deliveries', 0);
        DB::commit();
        $d = CrmDelivery::sole();
        $this->assertTrue($u->marketing_opt_in);
        $this->assertSame('GRANT', $d->payload['marketing']['action']);
        $stored = DB::table('crm_deliveries')->value('payload');
        $this->assertStringNotContainsString('crm@example.test', $stored);
        $this->assertStringNotContainsString('CRM User', $stored);
        $this->assertStringNotContainsString(config('crm.secret'), json_encode($d->payload));
        $this->assertArrayNotHasKey('payload', $d->toArray());
        Http::assertNothingSent();
    }

    public function test_unchecked_and_missing_signup_are_false_and_existing_users_null(): void
    {
        $existing = User::factory()->create();
        $this->assertNull($existing->fresh()->marketing_opt_in);
        $u = $this->signup();
        $this->assertFalse($u->marketing_opt_in);
        $this->assertSame('REVOKE', CrmDelivery::sole()->payload['marketing']['action']);
        $other = $this->signup(['email' => 'other@example.test', 'marketing_opt_in' => '0']);
        $this->assertFalse($other->marketing_opt_in);
    }

    public function test_rollback_discards_signup_delivery(): void
    {
        DB::beginTransaction();
        $this->signup(['marketing_opt_in' => true]);
        DB::rollBack();
        $this->assertDatabaseCount('crm_deliveries', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_partner_email_eligible_phone_only_succeeds_without_delivery(): void
    {
        $base = ['business_name' => 'CRM Shop', 'contact_name' => 'Contact'];
        $this->post('/ciudades/la-paz/partner', $base + ['contact_phone' => '70000000'])->assertRedirect();
        $this->assertDatabaseCount('partner_applications', 1);
        $this->assertDatabaseCount('crm_deliveries', 0);
        $this->post('/ciudades/la-paz/partner', array_merge($base, ['business_name' => 'Email Shop', 'contact_email' => 'lead@example.test', 'marketing_opt_in' => true]))->assertRedirect();
        $v = CrmDelivery::sole()->payload;
        $this->assertSame(['leads'], $v['lists_add']);
        $this->assertContains('partner-applicant', $v['tags_add']);
        $this->assertNotContains('partners', $v['lists_add']);
        $this->assertSame('GRANT', $v['marketing']['action']);
    }

    public function test_program_uses_authenticated_user_identity(): void
    {
        $u = User::factory()->create();
        $app = DB::transaction(fn () => ProgramApplication::create(['user_id' => $u->id, 'program_type' => 'AFFILIATE', 'status' => 'SUBMITTED']));
        $v = CrmDelivery::sole()->payload;
        $this->assertSame($u->email, $v['contact']['email']);
        $this->assertSame((string) $u->id, $v['identity']['jakawi_user_id']);
        $this->assertContains('program-applicant', $v['tags_add']);
        $this->assertSame('UNCHANGED', $v['marketing']['action']);
    }

    public function test_membership_request_activation_and_domain_fields(): void
    {
        $u = User::factory()->create();
        DB::transaction(fn () => MembershipPurchaseRequest::create(['user_id' => $u->id, 'status' => 'REQUESTED', 'requested_at' => now()]));
        DB::transaction(fn () => Membership::create(['user_id' => $u->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addYear(), 'amount_paid' => 100]));
        $this->assertSame(['membership_purchase_requested', 'membership_activated'], CrmDelivery::orderBy('id')->get()->map(fn ($d) => $d->payload['source']['type'])->all());
        $v = CrmDelivery::latest('id')->first()->payload;
        $this->assertContains('membership-requested', CrmDelivery::orderBy('id')->first()->payload['tags_add']);
        $this->assertContains('membership-requested', $v['tags_remove']);
        $this->assertSame('membership_activated', $v['fields']['last_activity_type']);
        $this->assertSame('active', $v['fields']['membership_status']);
        $this->assertContains('member-active', $v['tags_add']);
        $this->assertContains('member-expired', $v['tags_remove']);
    }

    public function test_product_successes_signal_after_commit_without_http(): void
    {
        $u = User::factory()->create();
        $challenge = Challenge::create(['title' => 'CRM Challenge', 'slug' => 'crm-challenge', 'status' => 'open', 'review_status' => 'APPROVED', 'description' => 'Test', 'instructions' => 'Test', 'evidence_type' => 'MANUAL', 'reward_type' => 'JP', 'reward_jp_amount' => 1]);
        $unlock = Unlock::create(['title' => 'CRM Unlock', 'slug' => 'crm-unlock', 'status' => Unlock::ACTIVE, 'minimum_commitments' => 10]);
        $membership = Membership::withoutEvents(fn () => Membership::create(['user_id' => $u->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addYear(), 'amount_paid' => 100]));
        $partner = Partner::factory()->create();
        $experience = Experience::factory()->published()->create();
        $session = ExperienceSession::factory()->create(['experience_id' => $experience->id]);
        DB::beginTransaction();
        ChallengeParticipation::create(['user_id' => $u->id, 'challenge_id' => $challenge->id]);
        UnlockParticipation::create(['user_id' => $u->id, 'unlock_id' => $unlock->id, 'status' => UnlockParticipation::COMMITTED]);
        ExperienceReservation::create(['user_id' => $u->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'confirmed', 'party_size' => 1]);
        Redemption::create(['public_id' => (string) str()->ulid(), 'code' => 'CRM123', 'user_id' => $u->id, 'membership_id' => $membership->id, 'partner_name' => 'Partner', 'location_name' => 'Location', 'benefit_title' => 'Benefit', 'status' => 'confirmed', 'expires_at' => now()->addMinute(), 'confirmed_at' => now()]);
        $this->assertDatabaseCount('crm_deliveries', 0);
        DB::commit();
        $types = CrmDelivery::get()->map(fn ($d) => $d->payload['source']['type'])->all();
        foreach (['challenge_joined', 'unlock_committed', 'experience_reserved', 'benefit_redeemed'] as $type) {
            $this->assertContains($type, $types);
        }
        foreach (CrmDelivery::all() as $d) {
            $this->assertSame('UNCHANGED', $d->payload['marketing']['action']);
        } Http::assertNothingSent();
    }

    public function test_profile_grant_revoke_and_email_change_use_link(): void
    {
        $u = User::factory()->create();
        CrmContactLink::create(['provider' => 'fluentcrm', 'user_id' => $u->id, 'provider_contact_id' => '42', 'linked_at' => now()]);
        $this->actingAs($u)->patch('/settings/profile', ['name' => $u->name, 'email' => $u->email, 'marketing_opt_in' => '1'])->assertRedirect();
        $this->assertSame('GRANT', CrmDelivery::latest('id')->first()->payload['marketing']['action']);
        $this->patch('/settings/profile', ['name' => $u->name, 'email' => 'changed@example.test', 'marketing_opt_in' => '0'])->assertRedirect();
        $v = CrmDelivery::latest('id')->first()->payload;
        $this->assertSame('REVOKE', $v['marketing']['action']);
        $this->assertSame('42', $v['identity']['provider_contact_id']);
        $this->assertSame('changed@example.test', $v['contact']['email']);
    }

    public function test_first_latest_coherent_tie_order_and_none_has_no_organic_tag(): void
    {
        $u = User::factory()->create();
        $time = now()->subDay();
        $a = AttributionTouch::create(['user_id' => $u->id, 'occurred_at' => $time, 'acquisition_provider' => 'META', 'utm_source' => 'first', 'campaign_key' => 'first-key', 'landing_page' => 'https://jakawi.com/l/first']);
        $b = AttributionTouch::create(['user_id' => $u->id, 'occurred_at' => $time, 'acquisition_provider' => 'NONE', 'utm_source' => 'last', 'utm_campaign' => 'last-campaign']);
        $v = app(CrmContactProjectionService::class)->project($u, 'profile_updated');
        $this->assertSame($a->id, $v['source']['first_attribution']['id']);
        $this->assertSame($b->id, $v['source']['last_attribution']['id']);
        $this->assertSame('first', $v['fields']['first_utm_source']);
        $this->assertNull($v['fields']['first_utm_campaign']);
        $this->assertSame('first', $v['fields']['first_landing_slug']);
        $this->assertSame('last-campaign', $v['fields']['last_utm_campaign']);
        $this->assertNotContains('source-organic', $v['tags_add']);
        AttributionTouch::create(['user_id' => $u->id, 'occurred_at' => now(), 'acquisition_provider' => 'GOOGLE', 'utm_source' => 'new', 'utm_content' => 'creative', 'utm_term' => 'keyword', 'fbclid' => 'private-fb', 'gclid' => 'private-google', 'ttclid' => 'private-tiktok']);
        $new = app(CrmContactProjectionService::class)->project($u, 'profile_updated');
        $this->assertSame($v['fields']['first_utm_source'], $new['fields']['first_utm_source']);
        $this->assertContains('source-meta', $new['tags_add']);
        $this->assertNotContains('source-google', $new['tags_add']);
        $this->assertSame('GOOGLE', $new['fields']['last_acquisition_provider']);
        $this->assertNull($new['fields']['first_utm_content']);
        $this->assertNull($new['fields']['first_utm_term']);
        $this->assertSame($a->occurred_at->toISOString(), $new['fields']['first_touch_at']);
        $this->assertSame($a->occurred_at->toISOString(), $v['fields']['last_touch_at']);
    }

    public function test_projection_excludes_growth_and_click_ids_and_uses_domain_activity_time(): void
    {
        $u = User::factory()->create(['created_at' => now()->subDays(2), 'updated_at' => now()->subDay()]);
        $service = app(CrmContactProjectionService::class);
        AttributionTouch::create(['user_id' => $u->id, 'occurred_at' => now(), 'acquisition_provider' => 'NONE', 'utm_content' => 'creative', 'utm_term' => 'keyword', 'fbclid' => 'private', 'gclid' => 'private', 'ttclid' => 'private']);
        $v = $service->project($u, 'signup_completed');
        $this->assertSame($u->created_at->toISOString(), $v['fields']['signup_at']);
        $this->assertSame($u->updated_at->toISOString(), $v['fields']['last_activity_at']);
        $this->assertSame('creative', $v['fields']['first_utm_content']);
        $this->assertSame('keyword', $v['fields']['last_utm_term']);
        $this->assertSame([], array_values(array_filter($v['tags_add'], fn ($tag) => str_starts_with($tag, 'source-'))));
        foreach (['fbclid', 'gclid', 'ttclid'] as $key) {
            $this->assertStringNotContainsString($key, json_encode($v));
        }
        foreach (['landing_view', 'landing_cta_click'] as $event) {
            $this->assertNull($service->project($u, $event));
            $service->signal($u, $event);
        }
        $this->assertDatabaseCount('crm_deliveries', 0);
    }

    public function test_expiration_and_confirmed_partner_projection(): void
    {
        $u = User::factory()->create();
        $membership = Membership::withoutEvents(fn () => Membership::create(['user_id' => $u->id, 'status' => 'active', 'starts_at' => now()->subYear(), 'ends_at' => now()->subMinute(), 'amount_paid' => 100]));
        $service = app(CrmContactProjectionService::class);
        $v = $service->project($membership, 'membership_updated');
        $this->assertSame('expired', $v['fields']['membership_status']);
        $this->assertContains('member-expired', $v['tags_add']);
        $this->assertContains('member-active', $v['tags_remove']);
        $this->assertArrayNotHasKey('last_activity_type', $v['fields']);
        $partner = Partner::factory()->create();
        $u->partners()->attach($partner->id, ['role' => 'owner']);
        $v = $service->project($u, 'profile_updated');
        $this->assertContains('partners', $v['lists_add']);
        $this->assertContains('jakawi-partner', $v['tags_add']);
        $this->assertNotContains('partner-applicant', $v['tags_add']);
    }

    public function test_signed_dispatch_link_and_retry_body_are_stable(): void
    {
        $u = $this->signup(['marketing_opt_in' => true]);
        $bodies = [];
        Http::fake(function ($r) use (&$bodies) {
            $bodies[] = $r->body();
            $id = $r->header('X-Jakawi-Event-Id')[0];
            $ts = $r->header('X-Jakawi-Timestamp')[0];
            $this->assertSame(hash_hmac('sha256', $ts."\n".$id."\n".$r->body(), config('crm.secret')), $r->header('X-Jakawi-Signature')[0]);

            return count($bodies) === 1 ? Http::response([], 429, ['Retry-After' => '120']) : Http::response(['result_code' => 'OK', 'provider_contact_id' => 42]);
        });
        $dispatcher = app(CrmDeliveryDispatcher::class);
        $this->assertSame(1, $dispatcher->run());
        $this->assertSame('RETRY', CrmDelivery::sole()->status);
        $this->travel(3)->minutes();
        $dispatcher->run();
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertSame('SENT', CrmDelivery::sole()->status);
        $this->assertDatabaseHas('crm_contact_links', ['user_id' => $u->id, 'provider_contact_id' => '42']);
    }

    public function test_conflict_is_dead_and_does_not_merge_or_rollback_domain(): void
    {
        $u = $this->signup();
        Http::fake(['*' => Http::response(['result_code' => 'IDENTITY_CONFLICT'], 409)]);
        app(CrmDeliveryDispatcher::class)->run();
        $this->assertDatabaseHas('users', ['id' => $u->id]);
        $this->assertSame('DEAD', CrmDelivery::sole()->status);
        $this->assertDatabaseCount('crm_contact_links', 0);
    }

    public function test_storage_failure_does_not_rollback_signup(): void
    {
        Schema::rename('crm_deliveries', 'crm_deliveries_unavailable');
        try {
            $u = $this->signup();
        } finally {
            Schema::rename('crm_deliveries_unavailable', 'crm_deliveries');
        }
        $this->assertDatabaseHas('users', ['id' => $u->id]);
        $this->assertDatabaseCount('crm_deliveries', 0);
    }

    public function test_auth_is_finite_and_network_retry_exhausts(): void
    {
        $this->signup();
        Http::fake(['*' => Http::response([], 401)]);
        app(CrmDeliveryDispatcher::class)->run();
        $this->assertSame('DEAD', CrmDelivery::sole()->status);
        CrmDelivery::sole()->update(['status' => 'PENDING', 'attempts' => 5]);
        Http::fake(['*' => Http::response([], 503)]);
        app(CrmDeliveryDispatcher::class)->run();
        $this->assertSame('DEAD', CrmDelivery::sole()->status);
        $this->assertSame(6, CrmDelivery::sole()->attempts);
    }

    public function test_ui_defaults_and_preference_copy(): void
    {
        $copy = 'Quiero recibir novedades, beneficios y experiencias de JAKAWI por email.';
        $register = file_get_contents(resource_path('js/pages/auth/register.tsx'));
        $this->assertStringContainsString('defaultChecked', $register);
        $this->assertStringContainsString($copy, $register);
        $partner = file_get_contents(resource_path('js/pages/cities/partner-application.tsx'));
        $this->assertStringContainsString('marketing_opt_in: true', $partner);
        $this->assertStringContainsString($copy, $partner);
        $profile = file_get_contents(resource_path('js/pages/settings/profile.tsx'));
        $this->assertStringContainsString('marketing_opt_in === true', $profile);
    }

    public function test_competing_dispatcher_skips_locked_delivery(): void
    {
        $this->signup();
        config(['database.connections.crm_competitor' => config('database.connections.pgsql')]);
        $other = DB::connection('crm_competitor');
        $other->beginTransaction();
        try {
            CrmDelivery::on('crm_competitor')->lockForUpdate()->firstOrFail();
            $this->assertSame(0, app(CrmDeliveryDispatcher::class)->run());
            Http::assertNothingSent();
        } finally {
            $other->rollBack();
            DB::purge('crm_competitor');
        }
        Http::fake(['*' => Http::response(['result_code' => 'OK', 'provider_contact_id' => 42])]);
        $this->assertSame(1, app(CrmDeliveryDispatcher::class)->run());
    }

    public function test_network_failure_keeps_domain_committed_and_new_link_is_used_before_first_attempt(): void
    {
        $u = $this->signup();
        CrmContactLink::create(['provider' => 'fluentcrm', 'user_id' => $u->id, 'provider_contact_id' => '42', 'linked_at' => now()]);
        Http::fake(['*' => Http::failedConnection()]);
        app(CrmDeliveryDispatcher::class)->run();
        $this->assertDatabaseHas('users', ['id' => $u->id]);
        $this->assertSame('RETRY', CrmDelivery::sole()->status);
        $this->assertSame('42', CrmDelivery::sole()->payload['identity']['provider_contact_id']);
        $this->assertSame('NETWORK_ERROR', CrmDelivery::sole()->last_error_code);
    }
}
