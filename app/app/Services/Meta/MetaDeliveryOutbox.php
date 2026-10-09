<?php

namespace App\Services\Meta;

use App\Enums\AcquisitionProvider;
use App\Models\{AnalyticsEvent, GrowthProviderDelivery};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaDeliveryOutbox
{
    public function __construct(private readonly MetaProviderConfiguration $configuration, private readonly MetaEventMapper $mapper) {}

    public function enqueueNew(AnalyticsEvent $event): void
    {
        // Never backfill deliveries for an existing/idempotently reused event.
        if (! $event->wasRecentlyCreated || $event->acquisition_provider !== AcquisitionProvider::META
            || ! $this->mapper->map($event->event_name) || ! $this->configuration->serverReady()) return;
        try {
            // Isolated savepoint preserves the canonical event/domain on outbox storage failure.
            DB::transaction(fn () => GrowthProviderDelivery::firstOrCreate([
                'analytics_event_id' => $event->id, 'provider' => AcquisitionProvider::META,
                'channel' => GrowthProviderDelivery::SERVER,
            ]));
        } catch (Throwable) {
            Log::warning('Meta delivery could not be stored.', ['event_id' => $event->event_id, 'status' => 'OUTBOX_UNAVAILABLE']);
        }
    }
}
