<?php

namespace Tests\Feature\Oidc;

use App\Models\OidcClient;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OidcFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'isolated-client-secret-0123456789012345678901234567890123456789';

    private const CALLBACK = 'https://jakawi.club/auth/jakawi/callback';

    private string $clientId;

    private static ?array $keys = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (self::$keys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048]);
            openssl_pkey_export($key, $private);
            self::$keys = [$private, openssl_pkey_get_details($key)['key']];
        }
        $this->clientId = (string) Str::uuid();
        config([
            'oidc.club_client_id' => $this->clientId,
            'oidc.club_client_secret' => self::SECRET,
            'oidc.club_redirect_uri' => self::CALLBACK,
            'oidc.issuer' => 'https://jakawi.com',
            'passport.private_key' => self::$keys[0],
            'passport.public_key' => self::$keys[1],
            'openid.token_headers' => ['kid' => 'isolated-test-key'],
            'openid.issuedBy' => 'https://jakawi.com',
        ]);
        $this->artisan('oidc:register-club')->assertSuccessful();
    }

    private function parameters(array $overrides = []): array
    {
        return array_replace([
            'client_id' => $this->clientId, 'redirect_uri' => self::CALLBACK,
            'response_type' => 'code', 'scope' => 'openid profile email',
            'state' => str_repeat('s', 43), 'nonce' => str_repeat('n', 43),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('v', 64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], $overrides);
    }

    private function authorize(array $overrides = []): string
    {
        $response = $this->get('/oauth/authorize?'.http_build_query($this->parameters($overrides)));
        $response->assertRedirect();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::CALLBACK.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame($overrides['state'] ?? str_repeat('s', 43), $query['state']);

        return $query['code'];
    }

    private function exchange(string $code, array $overrides = [], bool $basic = false)
    {
        $body = array_replace([
            'grant_type' => 'authorization_code', 'client_id' => $this->clientId,
            'client_secret' => self::SECRET, 'redirect_uri' => self::CALLBACK,
            'code' => $code, 'code_verifier' => str_repeat('v', 64),
        ], $overrides);
        $headers = ['Accept' => 'application/json'];
        if ($basic) {
            $headers['Authorization'] = 'Basic '.base64_encode($body['client_id'].':'.$body['client_secret']);
            unset($body['client_id'], $body['client_secret']);
        }

        return $this->post('/oauth/token', $body, $headers);
    }

    private function assertResumesOidc($response, array $parameters): void
    {
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertSame('/oauth/authorize', parse_url($location, PHP_URL_PATH));
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertEquals($parameters, $query);
    }

    private function claims(string $jwt): array
    {
        [$header, $payload, $signature] = explode('.', $jwt);
        $decode = fn ($s) => base64_decode(strtr($s, '-_', '+/'));
        $this->assertSame(1, openssl_verify($header.'.'.$payload, $decode($signature), self::$keys[1], OPENSSL_ALGO_SHA256));
        $this->assertSame(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'isolated-test-key'], json_decode($decode($header), true));

        return json_decode($decode($payload), true);
    }

    public function test_full_local_login_resumes_oidc_and_returns_signed_global_subject(): void
    {
        $user = User::factory()->create();
        $url = '/oauth/authorize?'.http_build_query($this->parameters());
        $this->get($url)->assertRedirect(route('login'));
        $this->assertResumesOidc($this->post('/login', ['email' => $user->email, 'password' => 'password']), $this->parameters());
        $code = $this->authorize();
        $response = $this->exchange($code)->assertOk()->assertJsonMissingPath('refresh_token');
        $claims = $this->claims($response->json('id_token'));
        $this->assertSame('https://jakawi.com', $claims['iss']);
        $this->assertSame($user->global_subject_id, $claims['sub']);
        $this->assertNotSame((string) $user->id, $claims['sub']);
        $this->assertContains($this->clientId, (array) $claims['aud']);
        $this->assertSame(str_repeat('n', 43), $claims['nonce']);
        $this->assertSame($user->email, $claims['email']);
        $this->assertTrue($claims['email_verified']);
        $this->assertSame($user->name, $claims['name']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertSame(300, $response->json('expires_in'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('identity_links', 0);
    }

    public function test_existing_com_session_needs_no_password_and_basic_exchange_works(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $code = $this->authorize(['prompt' => 'none']);
        $claims = $this->claims($this->exchange($code, basic: true)->assertOk()->json('id_token'));
        $this->assertSame($user->global_subject_id, $claims['sub']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_inertia_login_resumes_with_a_full_browser_visit_before_external_callback(): void
    {
        $user = User::factory()->create();
        $url = '/oauth/authorize?'.http_build_query($this->parameters());
        $this->get($url)->assertRedirect(route('login'));
        $headers = ['X-Inertia' => 'true', 'Accept' => 'text/html'];
        $this->assertResumesOidc($this->post('/login', ['email' => $user->email, 'password' => 'password'], $headers), $this->parameters());
        $response = $this->get($url, $headers)->assertStatus(409);
        $location = $response->headers->get('X-Inertia-Location');
        $this->assertSame('/oauth/authorize', parse_url($location, PHP_URL_PATH));
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertEquals($this->parameters(), $query);
        $this->assertDatabaseCount('oauth_auth_codes', 0);
        // Simulate Inertia's window.location visit, which has no X-Inertia header.
        $this->flushHeaders();
        $claims = $this->claims($this->exchange($this->authorize())->assertOk()->json('id_token'));
        $this->assertSame($user->global_subject_id, $claims['sub']);
    }

    public function test_prompt_none_as_guest_returns_login_required_with_state(): void
    {
        $response = $this->get('/oauth/authorize?'.http_build_query($this->parameters(['prompt' => 'none'])));
        $response->assertRedirect();
        $response->assertHeader('Cache-Control', 'no-store, private');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('login_required', $query['error']);
        $this->assertSame(str_repeat('s', 43), $query['state']);
    }

    public function test_invalid_code_and_repeated_code_are_rejected(): void
    {
        $this->exchange('invalid')->assertStatus(400)->assertJsonPath('error', 'invalid_grant')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create());
        $code = $this->authorize();
        $this->exchange($code)->assertOk();
        $this->exchange($code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant')->assertHeader('Cache-Control', 'no-store, private');
        $this->exchange(strtoupper($code))->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $this->assertDatabaseCount('oauth_access_tokens', 1);
    }

    public function test_wrong_pkce_secret_and_redirect_do_not_consume_the_code(): void
    {
        $this->actingAs(User::factory()->create());
        $code = $this->authorize();
        $this->exchange($code, ['code_verifier' => str_repeat('x', 64)])->assertStatus(400)->assertHeader('Cache-Control', 'no-store, private');
        $this->exchange($code, ['client_secret' => 'wrong'])->assertStatus(401)->assertHeader('Cache-Control', 'no-store, private');
        $this->exchange($code, ['redirect_uri' => 'https://evil.example/callback'])->assertStatus(400);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
        $this->exchange($code)->assertOk();
    }

    public function test_nonce_is_bound_to_each_code_even_when_exchanged_in_reverse_order(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->authorize(['nonce' => str_repeat('a', 43)]);
        $second = $this->authorize(['nonce' => str_repeat('b', 43)]);
        $this->assertSame(str_repeat('b', 43), $this->claims($this->exchange($second)->assertOk()->json('id_token'))['nonce']);
        $this->assertSame(str_repeat('a', 43), $this->claims($this->exchange($first)->assertOk()->json('id_token'))['nonce']);
    }

    public function test_code_expiry_and_missing_verifier_are_enforced(): void
    {
        $this->actingAs(User::factory()->create());
        $code = $this->authorize();
        $this->exchange($code, ['code_verifier' => ''])->assertStatus(400);
        $crypt = new class
        {
            use \League\OAuth2\Server\CryptTrait;

            public function expire(string $code): string
            {
                $this->setEncryptionKey(app('encrypter')->getKey());
                $payload = json_decode($this->decrypt($code), true);
                $payload['expire_time'] = time() - 1;

                return $this->encrypt(json_encode($payload));
            }
        };
        $this->exchange($crypt->expire($code))->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public static function invalidRequests(): array
    {
        return [
            'foreign redirect' => [['redirect_uri' => 'https://evil.example/callback']],
            'redirect suffix' => [['redirect_uri' => self::CALLBACK.'/']],
            'redirect query' => [['redirect_uri' => self::CALLBACK.'?next=x']],
            'wrong client' => [['client_id' => 'other-client']],
            'implicit grant' => [['response_type' => 'token']],
            'no pkce' => [['code_challenge' => '']],
            'plain pkce' => [['code_challenge_method' => 'plain']],
            'bad challenge' => [['code_challenge' => str_repeat('+', 43)]],
            'no state' => [['state' => '']],
            'no nonce' => [['nonce' => '']],
            'array nonce' => [['nonce' => ['x']]],
            'missing openid' => [['scope' => 'profile']],
            'privileged scope' => [['scope' => 'openid admin']],
            'duplicate scope' => [['scope' => 'openid openid']],
            'request object' => [['request_uri' => 'https://evil.example/request']],
            'unsupported prompt' => [['prompt' => 'consent']],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function test_unsafe_authorization_is_rejected_before_login_or_redirect(array $overrides): void
    {
        $response = $this->get('/oauth/authorize?'.http_build_query($this->parameters($overrides)));
        $response->assertStatus(400)->assertHeaderMissing('Location');
        $this->assertDatabaseCount('oauth_auth_codes', 0);
    }

    public function test_discovery_is_pinned_and_jwks_contains_only_the_public_key(): void
    {
        $this->get('/.well-known/openid-configuration', ['Host' => 'evil.example'])
            ->assertOk()->assertJsonPath('issuer', 'https://jakawi.com')
            ->assertJsonPath('authorization_endpoint', 'https://jakawi.com/oauth/authorize')
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('grant_types_supported', ['authorization_code']);
        $key = $this->get('/oauth/jwks')->assertOk()->json('keys.0');
        $this->assertSame('isolated-test-key', $key['kid']);
        $this->assertSame('RS256', $key['alg']);
        $this->assertArrayNotHasKey('d', $key);
        $details = openssl_pkey_get_details(openssl_pkey_get_public(self::$keys[1]));
        $this->assertSame(rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='), $key['n']);
    }

    public function test_scopes_do_not_leak_email_profile_roles_or_password(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $before = $user->refresh()->getAttributes();
        $this->actingAs($user);
        $claims = $this->claims($this->exchange($this->authorize(['scope' => 'openid']))->assertOk()->json('id_token'));
        foreach (['email', 'name', 'password', 'is_admin', 'roles', 'memberships'] as $key) {
            $this->assertArrayNotHasKey($key, $claims);
        }
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertSame(0, DB::table('memberships')->count());
        $this->assertSame(0, DB::table('program_enrollments')->count());
    }

    public function test_registered_client_is_hashed_idempotent_and_cannot_be_replaced(): void
    {
        $client = OidcClient::query()->findOrFail($this->clientId);
        $this->assertNotSame(self::SECRET, $client->secret);
        $this->assertTrue(Hash::check(self::SECRET, $client->secret));
        $before = $client->getAttributes();
        $this->artisan('oidc:register-club')->assertSuccessful();
        $this->assertSame($before, $client->fresh()->getAttributes());
        config(['oidc.club_redirect_uri' => 'https://jakawi.club/other']);
        $this->artisan('oidc:register-club')->assertFailed();
        $this->assertSame($before, $client->fresh()->getAttributes());
    }

    public function test_revoked_client_is_rejected_even_with_an_existing_session(): void
    {
        OidcClient::query()->whereKey($this->clientId)->update(['revoked' => true]);
        $this->actingAs(User::factory()->create());
        $this->get('/oauth/authorize?'.http_build_query($this->parameters()))->assertStatus(401);
        $this->assertDatabaseCount('oauth_auth_codes', 0);
    }

    public function test_prompt_login_uses_local_authentication_and_preserves_oidc_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $url = '/oauth/authorize?'.http_build_query($this->parameters(['prompt' => 'login']));
        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertResumesOidc($this->post('/login', ['email' => $user->email, 'password' => 'password']), $this->parameters(['prompt' => 'login']));
        $this->authorize(['prompt' => 'login']);
    }

    public function test_two_factor_is_required_before_any_code_is_issued(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->get('/oauth/authorize?'.http_build_query($this->parameters()))->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertDatabaseCount('oauth_auth_codes', 0);
        $this->get('/oauth/authorize?'.http_build_query($this->parameters()))->assertRedirect(route('login'));
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $claims = $this->claims($this->exchange($this->authorize())->assertOk()->json('id_token'));
        $this->assertSame($user->global_subject_id, $claims['sub']);
    }

    public function test_external_intended_url_cannot_hijack_local_login(): void
    {
        $user = User::factory()->create();
        $this->withSession(['url.intended' => 'https://evil.example/oauth/authorize?x=1']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/mi-jakawi');
    }

    public function test_partner_credentials_and_local_home_remain_intact(): void
    {
        $partner = Partner::factory()->published()->create();
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => 'manager']);
        $before = $user->refresh()->getAttributes();
        $this->get('/oauth/authorize?'.http_build_query($this->parameters()))->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->exchange($this->authorize())->assertOk();
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertTrue($user->partners()->whereKey($partner->id)->exists());
        Auth::logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/partner');
    }

    public function test_disabled_grants_and_client_management_routes_are_unavailable(): void
    {
        foreach (['refresh_token', 'password', 'client_credentials'] as $grant) {
            $this->post('/oauth/token', ['grant_type' => $grant])->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');
        }
        $this->post('/oauth/clients')->assertNotFound();
        $this->post('/oauth/authorize')->assertStatus(405);
    }
}
