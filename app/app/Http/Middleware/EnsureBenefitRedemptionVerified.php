<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Scope verification enforcement to Benefit redemption, without changing global auth. */
class EnsureBenefitRedemptionVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $benefit = $request->route('benefit');
            $locationId = $request->input('location_id');
            $context = is_scalar($locationId) && ctype_digit((string) $locationId)
                ? ['location_id' => (int) $locationId] : [];
            // A generated same-origin GET is safe to resume; it never creates a redemption.
            $request->session()->put('url.intended', route('redemptions.intent', ['benefit' => $benefit->slug] + $context));
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
