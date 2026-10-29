<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClubIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('identity.club_secret');
        $key = $request->header('X-Jakawi-Key-Id', '');
        $timestamp = $request->header('X-Jakawi-Timestamp', '');
        $nonce = $request->header('X-Jakawi-Nonce', '');
        $signature = $request->header('X-Jakawi-Signature', '');
        if (strlen($secret) < 32 || strlen((string) config('identity.club_email_proof_secret')) < 32
            || hash_equals($secret, (string) config('identity.club_email_proof_secret'))
            || hash_equals($secret, (string) config('crm.secret', ''))
            || hash_equals((string) config('identity.club_email_proof_secret'), (string) config('crm.secret', ''))) {
            return response()->json(['error' => 'IDENTITY_UNAVAILABLE'], 503)->header('Cache-Control', 'no-store');
        }
        if ($key !== config('identity.club_key_id') || ! ctype_digit($timestamp)
            || abs(time() - (int) $timestamp) > config('identity.clock_skew_seconds')
            || ! Str::isUuid($nonce) || ! preg_match('/^[a-f0-9]{64}$/D', $signature)
            || $request->getQueryString() !== null || strlen($request->getContent()) > 16384) {
            return $this->unauthorized();
        }
        $canonical = implode("\n", ['jakawi-identity-v1', $key, $timestamp, $nonce,
            $request->method(), $request->getPathInfo(), hash('sha256', $request->getContent())]);
        if (! hash_equals(hash_hmac('sha256', $canonical, $secret), $signature)) {
            return $this->unauthorized();
        }
        $inserted = DB::table('identity_request_nonces')->insertOrIgnore([
            'key_id' => $key, 'nonce' => strtolower($nonce), 'received_at' => now(),
        ]);
        if (! $inserted) {
            return response()->json(['error' => 'REQUEST_REPLAY'], 409)->header('Cache-Control', 'no-store');
        }
        if (($request->isMethod('GET') || str_ends_with($request->getPathInfo(), '/revoke')) && $request->getContent() !== '') {
            return response()->json(['error' => 'BODY_MUST_BE_EMPTY'], 422)->header('Cache-Control', 'no-store');
        }
        if ($request->isMethod('POST') && str_ends_with($request->getPathInfo(), '/handoffs') && ! $request->isJson()) {
            return response()->json(['error' => 'JSON_REQUIRED'], 422)->header('Cache-Control', 'no-store');
        }
        $request->attributes->set('identity_source_app', 'club');

        return $next($request)->header('Cache-Control', 'no-store');
    }

    private function unauthorized(): Response
    {
        return response()->json(['error' => 'UNAUTHENTICATED'], 401)->header('Cache-Control', 'no-store');
    }
}
