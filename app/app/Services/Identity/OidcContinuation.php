<?php

namespace App\Services\Identity;

use Illuminate\Http\Request;

class OidcContinuation
{
    public static function pull(Request $request): ?string
    {
        $url = $request->session()->get('url.intended');
        if (! is_string($url)) {
            return null;
        }
        $parts = parse_url($url);
        $issuer = parse_url(config('oidc.issuer'));
        if (! is_array($parts) || ! is_array($issuer)
            || ($parts['scheme'] ?? null) !== ($issuer['scheme'] ?? null)
            || ($parts['host'] ?? null) !== ($issuer['host'] ?? null)
            || ($parts['port'] ?? null) !== ($issuer['port'] ?? null)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || ($parts['path'] ?? null) !== '/oauth/authorize' || ! isset($parts['query'])) {
            return null;
        }
        $request->session()->forget('url.intended');

        return '/oauth/authorize?'.$parts['query'];
    }
}
