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
                410 => 'Este acceso venció o ya fue utilizado. Solicita otro desde CLUB; no necesitas pagar ni registrarte otra vez.',
                409 => 'No pudimos confirmar el acceso a esta cuenta. Si hay otra cuenta COM abierta, confirma el cambio o cancela. Si el problema continúa, vuelve a CLUB para resolver tu identidad.',
                419 => 'La continuación segura venció. Vuelve a CLUB y solicita otro acceso.',
                429 => 'Has realizado varios intentos. Espera un momento y solicita otro acceso desde CLUB.',
                default => 'No pudimos continuar de forma segura. Vuelve a CLUB y solicita otro acceso.',
            };
            $response->setContent(view('identity.club-access', ['error' => $message, 'entry' => false])->render());
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }

        return $response->withHeaders(['Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff']);
    }
}
