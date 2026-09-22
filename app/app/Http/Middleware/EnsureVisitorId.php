<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureVisitorId
{
    public const ATTRIBUTE = 'jakawi_visitor_id';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $name = config('jakawi.analytics.visitor_cookie');
        $visitorId = $request->cookie($name);
        $valid = is_string($visitorId) && Str::isUuid($visitorId);

        if (! $valid) {
            $visitorId = (string) Str::uuid();
        }

        $request->attributes->set(self::ATTRIBUTE, $visitorId);
        $response = $next($request);

        if (! $valid) {
            $response->headers->setCookie(Cookie::make(
                $name, $visitorId, 60 * 24 * 365, '/', null,
                (bool) config('session.secure'), true, false, 'lax',
            ));
        }

        return $response;
    }
}
