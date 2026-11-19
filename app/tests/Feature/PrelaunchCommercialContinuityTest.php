<?php

namespace Tests\Feature;

use App\Exceptions\PrelaunchOperationBlocked;
use App\Services\Commercial\{CommercialClient, CommercialDispatcher, CommercialOutbox};
use Illuminate\Support\Facades\{DB, Http};
use Tests\Support\EvidenceDatabaseTruncation;
use Tests\TestCase;

class PrelaunchCommercialContinuityTest extends TestCase
{
    use EvidenceDatabaseTruncation;

    protected array $exceptTables = ['cities'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode' => true]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            while (DB::transactionLevel() > 0) DB::rollBack();
            $this->truncateTablesForAllConnections();
        }
        parent::tearDown();
    }

    public function test_only_account_notifications_are_delivered_and_transaction_rows_are_untouched(): void
    {
        foreach (['qa-account' => 'member.registered', 'qa-commercial' => 'redemption.confirmed', 'qa-unknown' => 'unknown'] as $id => $type) {
            DB::table('commercial_outbox')->insert(['event_id' => $id, 'raw_body' => json_encode(['event_type' => $type]), 'status' => 'PENDING', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $before = DB::table('commercial_outbox')->where('event_id', '!=', 'qa-account')->orderBy('id')->get()->toJson();
        config(['commercial.enabled' => true, 'commercial.secret' => str_repeat('a', 64)]);
        Http::fake(['*' => Http::response(['event_id' => 'qa-account', 'status' => 'accepted'], 201)]);
        $this->assertSame(1, app(CommercialDispatcher::class)->run());
        $this->assertSame($before, DB::table('commercial_outbox')->where('event_id', '!=', 'qa-account')->orderBy('id')->get()->toJson());
        $this->assertSame('SENT', DB::table('commercial_outbox')->where('event_id','qa-account')->value('status'));
        Http::assertSentCount(1);
    }

    public function test_direct_client_blocks_financial_and_unknown_events_before_http(): void
    {
        foreach (['membership.activated', 'redemption.confirmed', 'unknown'] as $type) {
            try { app(CommercialClient::class)->send(json_encode(['event_type' => $type]), 'qa'); $this->fail('Commercial event bypass'); }
            catch (PrelaunchOperationBlocked $e) { $this->assertSame(423, $e->getStatusCode()); }
        }
        config(['commercial.enabled' => false]);
        $result = app(CommercialClient::class)->send(json_encode(['event_type' => 'member.registered']), 'qa-account');
        $this->assertSame('CONFIG_UNAVAILABLE', $result['code']);
        Http::assertNothingSent();
    }
}
