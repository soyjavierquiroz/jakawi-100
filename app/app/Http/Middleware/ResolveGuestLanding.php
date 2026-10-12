<?php

namespace App\Http\Middleware;

use App\Services\ProductLandingResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ResolveGuestLanding
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            foreach ($request->route()->parameters() as $subject) {
                if ($subject instanceof Model && ($landing = app(ProductLandingResolver::class)->forGuest($subject))) {
                    // Preserve incoming attribution, but campaign_key remains saved Landing configuration.
                    return redirect()->route('landing-presentations.show', ['presentation' => $landing->slug] + $request->query());
                }
            }
        }

        return $next($request);
    }
}
