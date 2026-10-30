<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\OAuthServerException;
use Symfony\Component\HttpFoundation\Response;

class ValidateOidcAuthorization
{
    public function handle(Request $request, Closure $next): Response
    {
        $q = $request->query();
        $allowed = ['client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'nonce',
            'code_challenge', 'code_challenge_method', 'response_mode', 'prompt'];
        foreach ($q as $key => $value) {
            if (! in_array($key, $allowed, true) || ! is_string($value)) {
                return $this->error();
            }
        }

        if (! is_string(config('oidc.club_client_id')) || ! is_string(config('oidc.club_redirect_uri'))
            || ($q['client_id'] ?? null) !== config('oidc.club_client_id')
            || ($q['redirect_uri'] ?? null) !== config('oidc.club_redirect_uri')
            || ($q['response_type'] ?? null) !== 'code'
            || ($q['code_challenge_method'] ?? null) !== 'S256'
            || ! preg_match('/^[A-Za-z0-9_-]{43}$/D', $q['code_challenge'] ?? '')
            || ! preg_match('/^[A-Za-z0-9._~-]{32,512}$/D', $q['state'] ?? '')
            || ! preg_match('/^[A-Za-z0-9._~-]{32,512}$/D', $q['nonce'] ?? '')
            || ! in_array($q['response_mode'] ?? 'query', ['query'], true)
            || ! in_array($q['prompt'] ?? '', ['', 'none', 'login'], true)) {
            return $this->error();
        }

        $scopes = explode(' ', $q['scope'] ?? '');
        if (! in_array('openid', $scopes, true)
            || array_diff($scopes, ['openid', 'profile', 'email']) !== []
            || count(array_unique($scopes)) !== count($scopes)) {
            return $this->error('invalid_scope');
        }

        try {
            $response = $next($request);
        } catch (OAuthServerException $e) {
            $response = $e->getResponse();
        }
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function error(string $error = 'invalid_request'): Response
    {
        // Never redirect on an unvalidated client, URI, state or nonce.
        return response()->json(['error' => $error], 400, ['Cache-Control' => 'no-store']);
    }
}
