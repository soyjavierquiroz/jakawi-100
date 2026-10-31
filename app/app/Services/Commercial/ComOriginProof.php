<?php

namespace App\Services\Commercial;

use App\Services\AttributionService;
use Illuminate\Validation\ValidationException;

class ComOriginProof
{
    public function issue(?array $candidate): ?string
    {
        if (! $candidate) {
            return null;
        }
        $secret = config('commercial.secret');
        if (! is_string($secret) || strlen($secret) < 32) {
            return null;
        }
        $payload = ['contract' => 'jakawi-com-origin-v1', 'source' => 'jakawi.com', 'audience' => 'jakawi.club',
            'issued_at' => now()->timestamp, 'expires_at' => now()->addDays(app(AttributionService::class)->windowDays())->timestamp,
            'acquisition' => $candidate];
        $body = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

        return $body.'.'.hash_hmac('sha256', "jakawi-com-origin-v1\n".$body, $secret);
    }

    public function read(string $token): array
    {
        $bad = fn () => ValidationException::withMessages(['com_origin' => 'El origen COM no es válido o ha expirado.']);
        if (strlen($token) > 8192 || ! preg_match('/\A([A-Za-z0-9_-]+)\.([a-f0-9]{64})\z/', $token, $m)) {
            throw $bad();
        }
        $secret = config('commercial.secret');
        if (! is_string($secret) || strlen($secret) < 32 || ! hash_equals(hash_hmac('sha256', "jakawi-com-origin-v1\n".$m[1], $secret), $m[2])) {
            throw $bad();
        }
        try {
            $payload = json_decode(base64_decode(strtr($m[1], '-_', '+/'), true), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw $bad();
        }
        if (! is_array($payload) || ($payload['contract'] ?? null) !== 'jakawi-com-origin-v1' || ($payload['source'] ?? null) !== 'jakawi.com'
            || ($payload['audience'] ?? null) !== 'jakawi.club' || ! is_int($payload['expires_at'] ?? null)
            || $payload['expires_at'] < now()->timestamp || ($payload['issued_at'] ?? PHP_INT_MAX) > now()->addMinutes(5)->timestamp
            || ! is_array($payload['acquisition'] ?? null)) {
            throw $bad();
        }

        return $payload['acquisition'];
    }
}
