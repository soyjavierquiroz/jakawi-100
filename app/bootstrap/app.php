<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAffiliate;
use App\Http\Middleware\EnsureCreator;
use App\Http\Middleware\EnsurePartner;
use App\Http\Middleware\EnsurePromoter;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Experience;
use App\Models\Unlock;
use App\Services\PublicJourneyContinuation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): string {
            $route = $request->route();
            $resource = match ($route?->getName()) {
                'experiences.reservations.store' => Experience::where('slug', $route->parameter('experience'))->first(),
                'unlocks.commit' => Unlock::where('slug', $route->parameter('unlock'))
                    ->whereIn('status', [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED])->first(),
                default => null,
            };

            if ($resource instanceof Experience) {
                app(PublicJourneyContinuation::class)->set('EXPERIENCE', $resource->id, 'RESERVE');
            } elseif ($resource instanceof Unlock) {
                app(PublicJourneyContinuation::class)->set('UNLOCK', $resource->id, 'COMMIT');
            }

            return $resource ? route('register') : route('login');
        });

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'affiliate' => EnsureAffiliate::class,
            'creator' => EnsureCreator::class,
            'partner' => EnsurePartner::class,
            'promoter' => EnsurePromoter::class,
        ]);

        $middleware->trustProxies(
            at: ['172.18.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', env('JAKAWI_ANALYTICS_VISITOR_COOKIE', 'jakawi_visitor_id')]);

        $middleware->web(append: [
            EnsureVisitorId::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::using(limit: 10),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
