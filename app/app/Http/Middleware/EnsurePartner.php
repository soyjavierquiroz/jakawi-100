<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartner
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->partners()->exists(), 403);

        $partner = $request->route('partner');
        if ($partner instanceof Partner) {
            abort_unless($user->partners()->whereKey($partner->id)->exists(), 403);
        }

        return $next($request);
    }
}
