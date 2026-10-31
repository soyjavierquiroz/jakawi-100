<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Services\Commercial\CommercialDispatcher;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PartnerMembershipOutboxTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['cities'];

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();
        parent::tearDown();
    }

    public function test_unknown_lead_event_is_retained_for_retry_and_duplicate_ack_is_accepted_without_recreating_lead(): void
    {
        config(['commercial.enabled' => true, 'commercial.secret' => str_repeat('a', 64)]);
        Http::preventStrayRequests();
        Partner::factory()->published()->create(['slug' => 'dossis43']);
        $this->get('/dossis43');
        $this->post('/register', ['name' => 'Lead QA', 'email' => 'lead@example.test', 'whatsapp' => '71234567'])->assertRedirect();
        $row = DB::table('commercial_outbox')->sole();
        Http::fake(['jakawi.club/*' => Http::response(['error' => 'unknown_event_type'], 422)]);
        app(CommercialDispatcher::class)->run();
        $this->assertDatabaseHas('commercial_outbox', ['event_id' => $row->event_id, 'status' => 'RETRY', 'last_http_status' => 422]);
        $this->assertSame($row->raw_body, DB::table('commercial_outbox')->value('raw_body'));
        DB::table('commercial_outbox')->update(['next_attempt_at' => now()->subMinute()]);
        Http::swap(new Factory);
        Http::fake(['jakawi.club/*' => Http::response(['event_id' => $row->event_id, 'status' => 'duplicate'], 200)]);
        app(CommercialDispatcher::class)->run();
        $this->assertDatabaseHas('commercial_outbox', ['status' => 'SENT']);
        $this->assertDatabaseCount('commercial_outbox', 1);
        Http::assertSent(fn ($r) => hash_equals('sha256='.hash_hmac('sha256', $r->header('X-Jakawi-Com-Timestamp')[0]."\nPOST\n/internal/commercial/events\n".hash('sha256', $r->body()), str_repeat('a', 64)), $r->header('X-Jakawi-Com-Signature')[0]));
    }
}
