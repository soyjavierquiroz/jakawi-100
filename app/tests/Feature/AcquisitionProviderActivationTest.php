<?php

namespace Tests\Feature;

use App\Enums\AcquisitionProvider;
use App\Models\{AnalyticsEvent, AttributionTouch, Benefit, Campaign, LandingPresentation, User};
use App\Services\{AcquisitionProviderResolver, AttributionService, ConversionRecorder};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcquisitionProviderActivationTest extends TestCase
{
    use RefreshDatabase;

    private function landing(): LandingPresentation
    {
        return Benefit::factory()->published()->create()->landingPresentations()->create([
            'name' => 'Provider', 'slug' => 'provider', 'status' => 'PUBLISHED', 'default_scope' => 'NONE',
        ]);
    }

    public function test_parser_only_recognizes_explicit_canonical_values(): void
    {
        $resolver = app(AcquisitionProviderResolver::class);
        foreach (['meta' => AcquisitionProvider::META, 'META' => AcquisitionProvider::META,
            '  MeTa  ' => AcquisitionProvider::META, 'tiktok' => AcquisitionProvider::TIKTOK,
            'google' => AcquisitionProvider::GOOGLE, 'facebook' => AcquisitionProvider::NONE,
            'tt' => AcquisitionProvider::NONE, 'foo' => AcquisitionProvider::NONE, 'none' => AcquisitionProvider::NONE,
            '' => AcquisitionProvider::NONE] as $raw => $expected) {
            $this->assertSame($expected, $resolver->parse($raw));
        }
        foreach ([null, ['meta'], 42] as $raw) $this->assertSame(AcquisitionProvider::NONE, $resolver->parse($raw));
    }

    public function test_acq_alone_creates_canonical_touches_and_view_snapshots_without_economics(): void
    {
        $this->landing();
        foreach (['meta' => AcquisitionProvider::META, 'TIKTOK' => AcquisitionProvider::TIKTOK, ' google ' => AcquisitionProvider::GOOGLE] as $raw => $expected) {
            $this->get('/l/provider?'.http_build_query(['acq' => $raw]))->assertOk();
            $this->assertSame($expected, AttributionTouch::latest('id')->firstOrFail()->acquisition_provider);
            $this->assertSame($expected, AnalyticsEvent::where('event_name', 'landing_view')->latest('id')->firstOrFail()->acquisition_provider);
        }
        $this->assertDatabaseCount('attribution_touches', 3);
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('conversions', 0);
    }

    public function test_invalid_acq_does_not_activate_or_persist_raw_values(): void
    {
        $this->landing();
        foreach (['foo', 'facebook', 'tt', 'none', ''] as $raw) {
            $this->get('/l/provider?'.http_build_query(['acq' => $raw]))->assertOk();
            $this->assertDatabaseCount('attribution_touches', 0);
            $this->assertSame(AcquisitionProvider::NONE, AnalyticsEvent::latest('id')->firstOrFail()->acquisition_provider);
        }
        $this->get('/l/provider?acq=foo&utm_source=newsletter')->assertOk();
        $this->assertSame(AcquisitionProvider::NONE, AttributionTouch::sole()->acquisition_provider);
        $this->assertSame('newsletter', AttributionTouch::sole()->utm_source);
        $this->assertArrayNotHasKey('acq', AttributionTouch::sole()->getAttributes());
        $this->get('/l/provider?acq[]=meta')->assertOk();
        $this->assertDatabaseCount('attribution_touches', 1);
    }

    public function test_switching_and_direct_continuity_use_one_touch_lifecycle(): void
    {
        $this->freezeTime(); // Also exercises id DESC for identical occurred_at.
        $landing = $this->landing();
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['?acq=meta' => AcquisitionProvider::META, '' => AcquisitionProvider::META,
            '?acq=tiktok' => AcquisitionProvider::TIKTOK, '?acq=google' => AcquisitionProvider::GOOGLE,
            '?acq=meta&utm_source=meta' => AcquisitionProvider::META, '?utm_source=newsletter' => AcquisitionProvider::NONE] as $query => $provider) {
            $this->get('/l/provider'.$query)->assertOk();
            $this->assertSame($provider, AnalyticsEvent::where('event_name', 'landing_view')->latest('id')->firstOrFail()->acquisition_provider);
            $this->assertSame($provider, app(AttributionService::class)->latestApplicableTouch($user->id, null)->acquisition_provider);
        }
        $this->assertDatabaseCount('attribution_touches', 5);
        $this->get('/l/provider')->assertOk();
        $this->assertDatabaseCount('attribution_touches', 5);
        $this->assertSame(AcquisitionProvider::NONE, AnalyticsEvent::latest('id')->firstOrFail()->acquisition_provider);
        $this->get('/l/provider?acq=meta')->assertOk();
        $landing->update(['campaign_key' => 'creator-laura']);
        $this->get('/l/provider')->assertOk();
        $this->assertSame(AcquisitionProvider::NONE, AttributionTouch::latest('id')->firstOrFail()->acquisition_provider);
        $this->get('/l/provider?acq=meta')->assertOk();
        $this->assertSame(AcquisitionProvider::META, AttributionTouch::latest('id')->firstOrFail()->acquisition_provider);
        $landing->update(['campaign_key' => null]);
        AttributionTouch::query()->update(['occurred_at' => now()->subDays(31)]);
        $this->get('/l/provider')->assertOk();
        $this->assertSame(AcquisitionProvider::NONE, AnalyticsEvent::latest('id')->firstOrFail()->acquisition_provider);
    }

    public function test_organic_home_and_product_detail_do_not_activate(): void
    {
        $landing = $this->landing();
        $this->get('/')->assertOk();
        $this->get('/beneficios/'.$landing->subject->slug)->assertOk();
        $this->get('/l/provider')->assertOk();
        $this->assertDatabaseCount('attribution_touches', 0);
        $this->assertSame(AcquisitionProvider::NONE, AnalyticsEvent::where('event_name', 'landing_view')->sole()->acquisition_provider);
    }

    public function test_prefetch_and_admin_preview_cannot_change_effective_provider(): void
    {
        $landing = $this->landing();
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        foreach (['Purpose', 'Sec-Purpose'] as $header) {
            $this->withHeader($header, 'prefetch')->get('/l/provider?acq=meta')->assertOk();
            $this->assertDatabaseCount('attribution_touches', 0);
            $this->flushHeaders();
        }
        $this->get('/l/provider?acq=meta')->assertOk();
        foreach (['Purpose', 'Sec-Purpose'] as $header) {
            $this->withHeader($header, 'prefetch')->get('/l/provider?acq=tiktok&utm_source=new')->assertOk();
            $this->flushHeaders();
        }
        $this->get('/admin/beneficios/'.$landing->subject->slug.'/landings/provider/preview?acq=google')->assertOk();
        $this->assertDatabaseCount('attribution_touches', 1);
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'landing_view')->count());
        $this->assertSame(AcquisitionProvider::META, app(AttributionService::class)->latestApplicableTouch($user->id, null)->acquisition_provider);
    }

    public function test_provider_authorization_is_separate_from_economic_campaign(): void
    {
        $landing = $this->landing();
        $user = User::factory()->create();
        $campaign = Campaign::create(['name' => 'Economic', 'code' => 'creator-laura', 'status' => 'active', 'event' => 'membership_purchased']);
        $this->actingAs($user)->get('/l/provider?acq=meta')->assertOk();
        $this->assertDatabaseCount('campaigns', 1);
        $this->assertDatabaseCount('conversions', 0);
        $landing->update(['campaign_key' => $campaign->code]);
        $this->get('/l/provider')->assertOk();
        $conversion = app(ConversionRecorder::class)->record($user, ['idempotency_key' => 'provider-key', 'type' => 'membership_purchased', 'gross_amount' => 100]);
        $this->assertNull($conversion->campaign_id);
        $this->assertSame(AcquisitionProvider::NONE, AttributionTouch::latest('id')->firstOrFail()->acquisition_provider);
        $this->get('/l/provider?acq=google&utm_campaign=creator-laura')->assertOk();
        $conversion = app(ConversionRecorder::class)->record($user, ['idempotency_key' => 'provider-economic', 'type' => 'membership_purchased', 'gross_amount' => 100]);
        $this->assertSame($campaign->id, $conversion->campaign_id);
    }

    public function test_migration_backfills_none_and_enforces_storage_contract(): void
    {
        $migration = require database_path('migrations/2026_10_09_000002_add_acquisition_provider.php');
        $migration->down();
        $touchId = DB::table('attribution_touches')->insertGetId(['utm_source' => 'meta', 'fbclid' => 'historical', 'occurred_at' => now()]);
        $eventId = DB::table('analytics_events')->insertGetId(['event_id' => (string) \Illuminate\Support\Str::uuid(), 'event_name' => 'landing_view', 'utm_source' => 'google', 'occurred_at' => now()]);
        $migration->up();
        $this->assertDatabaseHas('attribution_touches', ['id' => $touchId, 'acquisition_provider' => AcquisitionProvider::NONE->value]);
        $this->assertDatabaseHas('analytics_events', ['id' => $eventId, 'acquisition_provider' => AcquisitionProvider::NONE->value]);
        foreach (['attribution_touches' => $touchId, 'analytics_events' => $eventId] as $table => $id) {
            foreach (AcquisitionProvider::cases() as $provider) DB::table($table)->where('id', $id)->update(['acquisition_provider' => $provider->value]);
            foreach ([null, 'facebook', 'meta', 'INVALID'] as $invalid) {
                try {
                    DB::transaction(fn () => DB::table($table)->where('id', $id)->update(['acquisition_provider' => $invalid]));
                    $this->fail('Invalid provider accepted');
                } catch (QueryException $exception) {
                    $this->assertContains($exception->getCode(), ['23502', '23514']);
                }
            }
        }
        $index = DB::selectOne("SELECT indexdef FROM pg_indexes WHERE tablename = 'analytics_events' AND indexname = 'analytics_provider_occurred_at_index'");
        $this->assertNotNull($index);
        $this->assertStringContainsString('(acquisition_provider, occurred_at)', $index->indexdef);
    }
}
