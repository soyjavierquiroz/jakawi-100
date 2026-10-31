<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClubPartnerAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/internal/club/partners/access';

    protected function setUp(): void
    {
        parent::setUp();
        config(['club_purchases.secret' => str_repeat('p', 64), 'club_purchases.key_id' => 'club-purchases-v1']);
    }

    public static function permissions(): array
    {
        return [
            'owner vigente' => ['owner', false, 'published', true],
            'manager autorizado' => ['manager', true, 'published', true],
            'manager sin permiso' => ['manager', false, 'published', false],
            'staff aun con flag' => ['staff', true, 'published', false],
            'owner pausado' => ['owner', false, 'paused', false],
            'manager pausado' => ['manager', true, 'paused', false],
            'borrador' => ['owner', false, 'draft', false],
            'archivado' => ['owner', false, 'archived', false],
        ];
    }

    #[DataProvider('permissions')]
    public function test_live_access_and_portal_use_same_permission(string $role, bool $explicit, string $status, bool $allowed): void
    {
        $user = User::factory()->create();
        $partner = Partner::factory()->create(['status' => $status]);
        $user->partners()->attach($partner, ['role' => $role, 'economic_access' => $explicit]);
        $other = User::factory()->create();
        $other->partners()->attach(Partner::factory()->published()->create(), ['role' => 'owner']);

        $this->signed(['global_subject_id' => $user->global_subject_id])->assertOk()->assertExactJson([
            'global_subject_id' => $user->global_subject_id,
            'partners' => [['id' => $partner->id, 'name' => $partner->name, 'status' => $status, 'role' => $role, 'economic_access' => $allowed]],
        ])->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('Set-Cookie');
        $this->actingAs($user)->get('/partner/'.$partner->slug)->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('partnerAccess.can_view_economics', $allowed)->etc());
    }

    public function test_next_query_observes_permission_role_status_and_relationship_revocation(): void
    {
        $user = User::factory()->create();
        $partner = Partner::factory()->published()->create();
        $user->partners()->attach($partner, ['role' => 'manager', 'economic_access' => true]);
        $query = ['global_subject_id' => $user->global_subject_id];
        $this->signed($query)->assertJsonPath('partners.0.economic_access', true);
        $user->partners()->updateExistingPivot($partner->id, ['economic_access' => false]);
        $this->signed($query)->assertJsonPath('partners.0.economic_access', false);
        $user->partners()->updateExistingPivot($partner->id, ['role' => 'owner']);
        $this->signed($query)->assertJsonPath('partners.0.economic_access', true);
        $user->partners()->updateExistingPivot($partner->id, ['role' => 'staff']);
        $this->signed($query)->assertJsonPath('partners.0.economic_access', false);
        $user->partners()->updateExistingPivot($partner->id, ['role' => 'owner']);
        $partner->update(['status' => 'paused']);
        $this->signed($query)->assertJsonPath('partners.0.economic_access', false);
        $user->partners()->detach($partner);
        $this->signed($query)->assertExactJson(['global_subject_id' => $user->global_subject_id, 'partners' => []]);
        $this->actingAs($user)->get('/partner/'.$partner->slug)->assertForbidden();
    }

    public function test_no_partners_unknown_subject_and_default_manager_are_denied(): void
    {
        $user = User::factory()->create();
        foreach ([$user->global_subject_id, (string) Str::uuid()] as $subject) {
            $this->signed(['global_subject_id' => $subject])->assertOk()->assertExactJson(['global_subject_id' => $subject, 'partners' => []]);
        }
        $user->partners()->attach(Partner::factory()->published()->create(), ['role' => 'manager']);
        $this->signed(['global_subject_id' => $user->global_subject_id, 'email' => 'owner@example.com'])
            ->assertJsonPath('partners.0.economic_access', false);
    }

    public function test_multiple_partners_are_evaluated_independently(): void
    {
        $user = User::factory()->create();
        foreach (['owner', 'manager', 'staff'] as $role) {
            $user->partners()->attach(Partner::factory()->published()->create(), ['role' => $role]);
        }
        $this->signed(['global_subject_id' => $user->global_subject_id])->assertJsonCount(3, 'partners')
            ->assertJsonPath('partners.0.economic_access', true)
            ->assertJsonPath('partners.1.economic_access', false)
            ->assertJsonPath('partners.2.economic_access', false);
    }

    public function test_invalid_signature_stale_timestamp_replay_tampering_and_browser_session(): void
    {
        $query = ['global_subject_id' => (string) Str::uuid()];
        $this->signed($query, ['HTTP_X_JAKAWI_SIGNATURE' => str_repeat('0', 64)])->assertUnauthorized();
        $this->signed($query, [], time() - 301)->assertUnauthorized();
        $this->signed($query, [], time() + 301)->assertUnauthorized();
        $nonce = (string) Str::uuid();
        $this->signed($query, [], null, $nonce)->assertOk();
        $this->signed($query, [], null, $nonce)->assertStatus(409);
        $this->signed($query, ['HTTP_X_JAKAWI_KEY_ID' => 'unknown'])->assertUnauthorized();
        $this->signed($query, [], null, null, '/internal/club/purchases')->assertUnauthorized();
        $this->signed($query, [], null, null, null, json_encode(['global_subject_id' => (string) Str::uuid()]))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(self::PATH, $query)->assertUnauthorized();
    }

    public function test_validation_requires_uuid_json_and_no_query_string(): void
    {
        $this->signed(['global_subject_id' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('global_subject_id');
        $this->signed([])->assertUnprocessable();
        $this->signed(['global_subject_id' => (string) Str::uuid()], ['CONTENT_TYPE' => 'text/plain'])->assertUnprocessable();
        $this->postJson(self::PATH.'?email=owner@example.com', [])->assertUnauthorized();
    }

    private function signed(array $data, array $overrides = [], ?int $timestamp = null, ?string $nonce = null, ?string $signPath = null, ?string $sentBody = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $ts = (string) ($timestamp ?? time());
        $nonce ??= (string) Str::uuid();
        $canonical = implode("\n", ['jakawi-club-purchases-v1', 'club-purchases-v1', $ts, $nonce, 'POST', $signPath ?? self::PATH, hash('sha256', $body)]);

        return $this->call('POST', self::PATH, [], [], [], array_replace([
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_JAKAWI_KEY_ID' => 'club-purchases-v1', 'HTTP_X_JAKAWI_TIMESTAMP' => $ts,
            'HTTP_X_JAKAWI_NONCE' => $nonce,
            'HTTP_X_JAKAWI_SIGNATURE' => hash_hmac('sha256', $canonical, config('club_purchases.secret')),
        ], $overrides), $sentBody ?? $body);
    }
}
