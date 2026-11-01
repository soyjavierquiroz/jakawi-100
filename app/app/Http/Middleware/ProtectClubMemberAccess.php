<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProtectClubMemberAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('club/access', 'club/access/*')) {
            return $next($request);
        }
        // Covers successful and rejected responses, including CSRF errors.
        $response = $next($request);
        $status = $response->getStatusCode();
        if ($status >= 400 && $status < 500) {
            $message = match ($status) {
                410 => 'Tu acceso expiró. Vuelve a intentarlo.',
                409 => 'No pudimos confirmar tu acceso. Vuelve a CLUB para intentarlo de nuevo.',
                419 => 'Tu acceso expiró. Vuelve a intentarlo.',
                429 => 'Has realizado varios intentos. Espera un momento y solicita otro acceso desde CLUB.',
                default => 'No pudimos continuar de forma segura. Vuelve a CLUB y solicita otro acceso.',
            };
            if ($request->expectsJson()) {
                $response->setContent(json_encode(['message' => $message]));
                $response->headers->set('Content-Type', 'application/json');
            } else {
                $response->setContent(view('identity.club-access', ['error' => $message, 'entry' => false])->render());
                $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
            }
        }

        return $response->withHeaders(['Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self' https://jakawi.club; base-uri 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff']);
    }
}
