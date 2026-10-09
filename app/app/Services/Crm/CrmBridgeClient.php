<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\Http;
use Throwable;

final class CrmBridgeClient
{
    public function send(array $payload): array
    {
        if (! app(CrmConfiguration::class)->ready()) {
            return ['sent' => false, 'retry' => false, 'http' => null, 'code' => 'CONFIGURATION_ERROR', 'contact_id' => null, 'retry_after' => null];
        }
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp."\n".$payload['event_id']."\n".$raw, config('crm.secret'));
        try {
            $response = Http::connectTimeout(max(1, min(5, (int) config('crm.connect_timeout'))))->timeout(max(1, min(10, (int) config('crm.timeout'))))
                ->withOptions(['allow_redirects' => false])->withHeaders(['X-Jakawi-Timestamp' => $timestamp, 'X-Jakawi-Event-Id' => $payload['event_id'], 'X-Jakawi-Signature' => $signature])->withBody($raw, 'application/json')->post(config('crm.bridge_url'));
            $http = $response->status();
            $id = $response->json('provider_contact_id');
            $sent = $http === 200 && $response->json('result_code') === 'OK' && is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0;
            $retry = $http === 429 || $http >= 500;
            $after = $response->header('Retry-After');
            $delay = null;
            if (is_string($after) && ctype_digit($after)) {
                $delay = min(21600, max(60, (int) $after));
            } elseif (is_string($after) && ($date = strtotime($after)) !== false) {
                $delay = min(21600, max(60, $date - time()));
            }

            return ['sent' => $sent, 'retry' => $retry, 'http' => $http, 'code' => $sent ? null : ($http === 409 ? 'IDENTITY_OR_EVENT_CONFLICT' : ($http === 401 || $http === 403 ? 'AUTH_CONFIGURATION_ERROR' : ($retry ? 'BRIDGE_RETRY' : 'BRIDGE_REJECTED'))), 'contact_id' => $sent ? (string) $id : null, 'retry_after' => $delay];
        } catch (Throwable) {
            return ['sent' => false, 'retry' => true, 'http' => null, 'code' => 'NETWORK_ERROR', 'contact_id' => null, 'retry_after' => null];
        }
    }
}
