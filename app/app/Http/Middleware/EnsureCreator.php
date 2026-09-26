<?php

namespace App\Http\Middleware;

use App\Models\ProgramEnrollment;
use Closure;
use Illuminate\Http\Request;

class EnsureCreator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->hasActiveProgram(ProgramEnrollment::TYPE_CREATOR), 403);

        return $next($request);
    }
}
