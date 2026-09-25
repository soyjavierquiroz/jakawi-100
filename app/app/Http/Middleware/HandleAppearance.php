<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->user()?->theme_preference ?? $request->cookie('appearance') ?? 'system');
        View::share('appearanceIsAuthenticated', $request->user() !== null);

        return $next($request);
    }
}
