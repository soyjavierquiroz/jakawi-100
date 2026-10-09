<?php

namespace Tests\Feature;

use App\Models\CrmDelivery;
use App\Services\Crm\CrmConfiguration;
use App\Services\Crm\CrmBridgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrmRestCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_exact_production_endpoints_are_accepted(): void
    {
        config(['crm.enabled' => true, 'crm.provider' => 'fluentcrm', 'crm.secret' => str_repeat('a', 32)]);
        $fallback = 'https://crm.jakawi.com/?rest_route=/jakawi-fluentcrm/v1/events';
        foreach (['https://crm.jakawi.com/wp-json/jakawi-fluentcrm/v1/events', $fallback] as $url) {
            config(['crm.bridge_url' => $url]);
            $this->assertTrue(app(CrmConfiguration::class)->ready(), $url);
        }
        foreach ([
            str_replace('https:', 'http:', $fallback),
            str_replace('crm.jakawi.com', 'evil.example', $fallback),
            str_replace('crm.jakawi.com', 'sub.crm.jakawi.com', $fallback),
            str_replace('crm.jakawi.com', 'crm.jakawi.com:443', $fallback),
            'https://crm.jakawi.com/?rest_route=/other',
            $fallback.'&foo=bar',
            'https://crm.jakawi.com/?foo=bar&rest_route=/jakawi-fluentcrm/v1/events',
            $fallback.'&rest_route=/jakawi-fluentcrm/v1/events',
            $fallback.'#fragment', $fallback.'#',
            str_replace('https://', 'https://user@', $fallback),
            str_replace('/?rest_route=', '/wp-json/?rest_route=', $fallback),
            str_replace('/jakawi-fluentcrm', '%2Fjakawi-fluentcrm', $fallback),
            str_replace('crm.jakawi.com', 'crm.jakawi.com%40evil.example', $fallback),
            str_replace('/v1/events', '/v1/../v1/events', $fallback),
            'https://crm.jakawi.com/wp-json/jakawi-fluentcrm/v1/events?',
        ] as $url) {
            config(['crm.bridge_url' => $url]);
            $this->assertFalse(app(CrmConfiguration::class)->ready(), $url);
        }
    }

    public function test_client_sends_fallback_unchanged_with_hmac(): void
    {
        $url = 'https://crm.jakawi.com/?rest_route=/jakawi-fluentcrm/v1/events';
        $secret = str_repeat('a', 32);
        config(['crm.enabled' => true, 'crm.provider' => 'fluentcrm', 'crm.secret' => $secret, 'crm.bridge_url' => $url]);
        Http::preventStrayRequests();
        Http::fake([$url => Http::response(['result_code' => 'OK', 'provider_contact_id' => 1], 200)]);
        $payload = ['event_id' => '97c0f531-42df-4eb5-be81-630fb79e51a2'];
        $this->assertTrue(app(CrmBridgeClient::class)->send($payload)['sent']);
        Http::assertSent(function ($request) use ($url, $secret, $payload) {
            $signature = hash_hmac('sha256', $request->header('X-Jakawi-Timestamp')[0]."\n".$payload['event_id']."\n".$request->body(), $secret);

            return $request->url() === $url && $request->header('X-Jakawi-Signature')[0] === $signature;
        });
        Http::assertSentCount(1);
    }

    public function test_requeue_preserves_all_other_columns_and_creates_no_rows(): void
    {
        foreach (['DEAD', 'RETRY', 'PENDING', 'PROCESSING', 'SENT'] as $status) {
            $d = CrmDelivery::create(['provider' => 'fluentcrm', 'event_id' => fake()->uuid(), 'operation' => 'CONTACT_UPSERT', 'source_type' => 'App\\Models\\PartnerApplication', 'source_id' => 1, 'status' => $status, 'payload' => ['contact' => ['email' => 'qa@example.com']], 'attempts' => 2, 'next_attempt_at' => now()->addDay()]);
            $before = (array) DB::table('crm_deliveries')->find($d->id);
            $count = CrmDelivery::count();
            $eligible = in_array($status, ['DEAD', 'RETRY'], true);
            $this->artisan('crm:requeue', ['deliveryId' => $d->id])->assertExitCode($eligible ? 0 : 1);
            $after = (array) DB::table('crm_deliveries')->find($d->id);
            if ($eligible) {
                $this->assertSame('RETRY', $after['status']);
                $this->assertTrue($d->fresh()->next_attempt_at->lte(now()));
                unset($before['status'], $before['next_attempt_at'], $after['status'], $after['next_attempt_at']);
            }
            $this->assertSame($before, $after);
            $this->assertSame($count, CrmDelivery::count());
        }
        $this->artisan('crm:requeue', ['deliveryId' => 999999])->assertExitCode(1);
        $this->artisan('crm:requeue', ['deliveryId' => '1,2'])->assertExitCode(1);
    }
}
