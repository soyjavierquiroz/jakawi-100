<?php

namespace App\Services\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class EmailActivationProof
{
    /** A short-lived CLUB attestation of an actually consumed email activation token. */
    public function verify(string $proof, string $clubUserId, string $email, string $handoffId): ?array
    {
        $parts = explode('.', $proof);
        if (count($parts) !== 2 || strlen($proof) > 8192 || ! preg_match('/^[A-Za-z0-9_-]+$/D', $parts[0])
            || ! preg_match('/^[a-f0-9]{64}$/D', $parts[1])) {
            return null;
        }
        $secret = (string) config('identity.club_email_proof_secret');
        if (strlen($secret) < 32 || ! hash_equals(hash_hmac('sha256', "jakawi-email-activation-v1\n".$parts[0], $secret), $parts[1])) {
            return null;
        }
        $decoded = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $claims = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($claims) || ($claims['iss'] ?? null) !== 'https://jakawi.club'
            || ($claims['aud'] ?? null) !== config('identity.issuer')
            || ($claims['sub'] ?? null) !== $clubUserId || ($claims['email'] ?? null) !== $email
            || ($claims['handoff_id'] ?? null) !== $handoffId
            || ($claims['method'] ?? null) !== 'email_activation_token'
            || ! is_string($claims['jti'] ?? null) || ! Str::isUuid($claims['jti'])
            || ! is_string($claims['activation_ref'] ?? null) || ! preg_match('/^[A-Za-z0-9:_-]{1,128}$/D', $claims['activation_ref'])
            || ! is_int($claims['iat'] ?? null) || ! is_int($claims['exp'] ?? null)
            || $claims['iat'] > time() + 30 || $claims['iat'] < time() - 300
            || $claims['exp'] <= time() || $claims['exp'] <= $claims['iat'] || $claims['exp'] - $claims['iat'] > 300
            || ! is_string($claims['activated_at'] ?? null)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $claims['activated_at'])) {
            return null;
        }
        try {
            $activated = CarbonImmutable::parse($claims['activated_at']);
            if ($activated->getTimestamp() > $claims['iat'] + 30 || $activated->year < 2020) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $claims;
    }
}
