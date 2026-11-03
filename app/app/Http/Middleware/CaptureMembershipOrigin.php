<?php

namespace App\Http\Middleware;

use App\Services\Commercial\MembershipAcquisition;
use Closure;
use Illuminate\Http\Request;

class CaptureMembershipOrigin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->attributes->has('support_session')) app(MembershipAcquisition::class)->capture($request);

        return $next($request);
    }
}
