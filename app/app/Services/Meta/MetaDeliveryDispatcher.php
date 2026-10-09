<?php

namespace App\Services\Meta;

use App\Enums\AcquisitionProvider;
use App\Models\GrowthProviderDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaDeliveryDispatcher
{
    public const BACKOFF_MINUTES = [1, 5, 15, 60, 360];
    public const MAX_ATTEMPTS = 6;

    public function __construct(private readonly MetaProviderConfiguration $configuration, private readonly MetaConversionsApiClient $client) {}

    public function run(int $limit = 100): int
    {
        if (! $this->configuration->serverReady()) return 0;
        $handled = 0;
        $deadline = microtime(true) + 40; // Preserve the existing one-minute scheduler cadence.
        for ($i = 0; $i < min(100, max(0, $limit)); $i++) {
            if (microtime(true) >= $deadline) break;
            $found = DB::transaction(function () {
                $delivery = GrowthProviderDelivery::where('provider', AcquisitionProvider::META)
                    ->where('channel', GrowthProviderDelivery::SERVER)
                    ->whereIn('status', [GrowthProviderDelivery::PENDING, GrowthProviderDelivery::RETRY])
                    ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                    ->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
                if (! $delivery) return false;
                // Keep the row lock through the bounded HTTP call. Other dispatchers skip it.
                // On process death PostgreSQL rolls back to the previous retryable state.
                $delivery->update(['status' => GrowthProviderDelivery::PROCESSING,
                    'attempts' => $delivery->attempts + 1, 'last_attempt_at' => now()]);
                $event = $delivery->analyticsEvent;
                $result = $event ? $this->client->send($event)
                    : ['sent' => false, 'retry' => false, 'http_status' => null, 'error' => 'missing_event'];
                $retry = ! $result['sent'] && $result['retry'] && $delivery->attempts < self::MAX_ATTEMPTS;
                $status = $result['sent'] ? GrowthProviderDelivery::SENT : ($retry ? GrowthProviderDelivery::RETRY : GrowthProviderDelivery::DEAD);
                $delivery->update(['status' => $status, 'last_http_status' => $result['http_status'],
                    'last_error_code' => $result['error'], 'sent_at' => $result['sent'] ? now() : null,
                    'next_attempt_at' => $retry ? now()->addMinutes(self::BACKOFF_MINUTES[$delivery->attempts - 1]) : null]);
                Log::info('Meta delivery processed.', ['event_id' => $event?->event_id,
                    'delivery_id' => $delivery->id, 'status' => $status, 'http_status' => $result['http_status']]);
                return true;
            });
            if (! $found) break;
            $handled++;
        }
        return $handled;
    }
}
