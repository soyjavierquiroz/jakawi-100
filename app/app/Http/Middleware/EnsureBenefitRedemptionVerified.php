<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Preserve verification for challenge grants; normal Benefit redemption is non-blocking. */
class EnsureBenefitRedemptionVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $benefit = $request->route('benefit');
        if ($benefit->access_mode === 'social_challenge_grant' && ! $request->user()->hasVerifiedEmail()) {
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
