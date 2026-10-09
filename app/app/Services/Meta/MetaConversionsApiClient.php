<?php

namespace App\Services\Meta;

use App\Enums\AcquisitionProvider;
use App\Models\AnalyticsEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class MetaConversionsApiClient
{
    public function __construct(private readonly MetaProviderConfiguration $configuration, private readonly MetaEventPayloadFactory $payloads) {}

    /** Only fixed error categories leave this client; never response bodies or exceptions. */
    public function send(AnalyticsEvent $event): array
    {
        if ($event->acquisition_provider !== AcquisitionProvider::META || ! $this->configuration->serverReady()) {
            return ['sent' => false, 'retry' => false, 'http_status' => null, 'error' => 'ineligible'];
        }
        try {
            $body = ['data' => [$this->payloads->server($event)]];
            if (filled(config('meta.test_event_code'))) $body['test_event_code'] = config('meta.test_event_code');
            $response = Http::withToken(config('meta.access_token'))->acceptJson()->asJson()
                ->connectTimeout(2)->timeout(5)->withOptions(['allow_redirects' => false])
                ->post('https://graph.facebook.com/'.config('meta.api_version').'/'.config('meta.pixel_id').'/events', $body);
            $status = $response->status();
            if ($response->successful() && $response->json('events_received') === 1 && ! $response->json('error')) {
                return ['sent' => true, 'retry' => false, 'http_status' => $status, 'error' => null];
            }
            $retry = $status === 429 || $status >= 500;
            return ['sent' => false, 'retry' => $retry, 'http_status' => $status,
                'error' => $retry ? 'transient_http' : ($response->successful() ? 'invalid_response' : 'permanent_http')];
        } catch (ConnectionException) {
            return ['sent' => false, 'retry' => true, 'http_status' => null, 'error' => 'connection'];
        } catch (Throwable) {
            return ['sent' => false, 'retry' => false, 'http_status' => null, 'error' => 'invalid_payload_or_client'];
        }
    }
}
