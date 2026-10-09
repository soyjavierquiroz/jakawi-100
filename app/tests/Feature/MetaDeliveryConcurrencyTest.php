<?php

namespace Tests\Feature;

use App\Enums\AcquisitionProvider;
use App\Models\{GrowthProviderDelivery, AnalyticsEvent};
use App\Services\{GrowthMeasurementService};
use App\Services\Meta\MetaDeliveryDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class MetaDeliveryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_separate_database_session_skips_a_delivery_locked_during_http(): void
    {
        Http::preventStrayRequests();
        config(['meta.enabled' => true, 'meta.capi_enabled' => true, 'meta.pixel_id' => '123456789',
            'meta.access_token' => 'FAKE', 'meta.api_version' => 'v99.0']);
        $default = DB::getDefaultConnection();
        config(['database.connections.meta_competitor' => config('database.connections.'.$default)]);
        $primaryPid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
        $secondaryPid = DB::connection('meta_competitor')->selectOne('SELECT pg_backend_pid() AS pid')->pid;
        $this->assertNotSame($primaryPid, $secondaryPid);
        $event = app(GrowthMeasurementService::class)->record('landing_view', [
            'acquisition_provider' => AcquisitionProvider::META, 'visitor_id' => (string) Str::uuid(),
        ]);
        // A second PostgreSQL session must see a committed fixture. This test creates
        // no business data and explicitly removes the committed event afterwards.
        DB::commit();
        Http::fake(function () use ($default) {
            DB::setDefaultConnection('meta_competitor');
            try {
                $this->assertSame(0, app(MetaDeliveryDispatcher::class)->run(1));
            } finally {
                DB::setDefaultConnection($default);
            }
            return Http::response(['events_received' => 1]);
        });
        try {
            $this->assertSame(1, app(MetaDeliveryDispatcher::class)->run(1));
            Http::assertSentCount(1);
            $delivery = GrowthProviderDelivery::where('analytics_event_id', $event->id)->sole();
            $this->assertSame('SENT', $delivery->status);
            $this->assertSame(1, $delivery->attempts);
            $this->assertDatabaseCount('analytics_events', 1);
        } finally {
            DB::disconnect('meta_competitor');
            AnalyticsEvent::whereKey($event->id)->delete();
            DB::beginTransaction();
        }
    }
}
