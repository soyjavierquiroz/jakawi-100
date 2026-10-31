<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClubPurchase
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('club_purchases.secret');
        if (strlen($secret) < 32 || in_array($secret, [config('identity.club_secret'), config('identity.club_email_proof_secret'), config('commercial.secret'), config('crm.secret')], true)) {
            return response()->json(['error' => 'PURCHASES_UNAVAILABLE'], 503);
        }
        $key = $request->header('X-Jakawi-Key-Id', '');
        $ts = $request->header('X-Jakawi-Timestamp', '');
        $nonce = $request->header('X-Jakawi-Nonce', '');
        $sig = $request->header('X-Jakawi-Signature', '');
        $raw = $request->getContent();
        $canonical = implode("\n", ['jakawi-club-purchases-v1', $key, $ts, $nonce, $request->method(), $request->getPathInfo(), hash('sha256', $raw)]);
        if ($key !== config('club_purchases.key_id') || ! ctype_digit($ts) || abs(time() - (int) $ts) > 300 || ! Str::isUuid($nonce)
            || $request->getQueryString() !== null || strlen($raw) > 14500000 || ! hash_equals(hash_hmac('sha256', $canonical, $secret), $sig)) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }
        if (! DB::table('club_purchase_nonces')->insertOrIgnore(['nonce' => strtolower($nonce), 'received_at' => now()])) {
            return response()->json(['error' => 'REQUEST_REPLAY'], 409);
        }
        if (($request->isMethod('GET') && $raw !== '') || ($request->isMethod('POST') && ! $request->isJson())) {
            return response()->json(['error' => 'INVALID_BODY'], 422);
        }

        return $next($request)->header('Cache-Control', 'private, no-store');
    }
}
