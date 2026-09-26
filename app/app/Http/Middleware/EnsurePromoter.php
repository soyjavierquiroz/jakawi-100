<?php

namespace App\Http\Middleware;

use App\Models\ProgramEnrollment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePromoter
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasActiveProgram(ProgramEnrollment::TYPE_PROMOTER), 403);

        return $next($request);
    }
}
