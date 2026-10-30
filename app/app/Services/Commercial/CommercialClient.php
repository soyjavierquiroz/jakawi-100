<?php

namespace App\Services\Commercial;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class CommercialClient
{
    public function ready(): bool
    {
        return config('commercial.enabled') && is_string(config('commercial.secret'))
            && preg_match('/\A[0-9a-fA-F]{64}\z/', config('commercial.secret')) === 1;
    }

    public function send(string $raw, string $eventId): array
    {
        if (! $this->ready()) {
            return ['sent' => false, 'retry' => true, 'http' => null, 'code' => 'CONFIG_UNAVAILABLE'];
        }
        if (strlen($raw) > 65536) {
            return ['sent' => false, 'retry' => false, 'http' => null, 'code' => 'PAYLOAD_TOO_LARGE'];
        }
        $timestamp = (string) time();
        $canonical = $timestamp."\nPOST\n/internal/commercial/events\n".hash('sha256', $raw);
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json', 'Content-Type' => 'application/json',
                'X-Jakawi-Com-Key-Id' => 'com-commercial-v1',
                'X-Jakawi-Com-Timestamp' => $timestamp,
                'X-Jakawi-Com-Signature' => 'sha256='.hash_hmac('sha256', $canonical, config('commercial.secret')),
            ])->withBody($raw, 'application/json')->withOptions(['allow_redirects' => false])
                ->connectTimeout(5)->timeout(15)->post('https://jakawi.club/internal/commercial/events');
        } catch (ConnectionException) {
            return ['sent' => false, 'retry' => true, 'http' => null, 'code' => 'TRANSPORT_UNAVAILABLE'];
        }
        $http = $response->status();
        $sent = in_array($http, [200, 201], true) && $response->json('event_id') === $eventId
            && $response->json('status') === ($http === 201 ? 'accepted' : 'duplicate');

        return ['sent' => $sent, 'retry' => ! $sent && ($http >= 500 || $http === 429 || in_array($http, [200, 201], true)),
            'http' => $http, 'code' => $sent ? null : (in_array($http, [200, 201], true) ? 'INVALID_RECEIPT' : 'HTTP_'.$http)];
    }
}
