<?php

namespace App\Http\Middleware;

use App\Models\Benefit;
use App\Models\Experience;
use App\Services\ExperiencePublication;
use App\Services\ProductLandingResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ResolveProductLanding
{
    public function handle(Request $request, Closure $next)
    {
        foreach ($request->route()->parameters() as $subject) {
            abort_if($subject instanceof Benefit && $subject->status === 'draft', 404);
            if ($subject instanceof Experience) {
                abort_unless(app(ExperiencePublication::class)->isPublic($subject), 404);
            }
            if ($subject instanceof Model && ($landing = app(ProductLandingResolver::class)->forViewer($subject, (bool) $request->user()))) {
                // Preserve incoming attribution, but campaign_key remains saved Landing configuration.
                return redirect()->route('landing-presentations.show', ['presentation' => $landing->slug] + $request->query());
            }
        }

        return $next($request);
    }
}
