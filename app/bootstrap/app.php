<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePartner;
use App\Http\Middleware\EnsurePromoter;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Unlock;
use App\Services\AnalyticsTracker;
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
        then: function (): void {
            require __DIR__.'/../routes/identity.php';
            require __DIR__.'/../routes/catalog.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CLUB codes are an explicit, exact-format external identifier.
        $middleware->trimStrings(except: ['club_ref']);
        $middleware->redirectGuestsTo(function (Request $request): string {
            $route = $request->route();
            $resource = match ($route?->getName()) {
                'experiences.reservations.store' => Experience::where('slug', $route->parameter('experience'))->first(),
                'unlocks.commit' => Unlock::where('slug', $route->parameter('unlock'))
                    ->whereIn('status', [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED])->first(),
                'redemptions.start', 'redemptions.intent' => Benefit::where('slug', $route->parameter('benefit'))->first(),
                default => null,
            };

            if ($resource instanceof Experience) {
                $sessionId = $request->input('experience_session_id');
                $context = is_numeric($sessionId) && ctype_digit((string) $sessionId)
                    && $resource->upcomingSessions()->whereKey((int) $sessionId)->exists()
                    ? ['experience_session_id' => (int) $sessionId] : [];
                app(PublicJourneyContinuation::class)->set('EXPERIENCE', $resource->id, 'RESERVE', $context);
            } elseif ($resource instanceof Unlock) {
                app(PublicJourneyContinuation::class)->set('UNLOCK', $resource->id, 'COMMIT');
            } elseif ($resource instanceof Benefit) {
                $locationId = $request->input('location_id');
                $context = is_scalar($locationId) && ctype_digit((string) $locationId)
                    && $resource->availableLocations()->whereKey((int) $locationId)->get()->contains(fn ($location) => $location->hasRedemptionPin())
                    ? ['location_id' => (int) $locationId] : [];
                app(PublicJourneyContinuation::class)->set('BENEFIT', $resource->id, 'REDEEM', $context);
            }

            $authRoute = $resource instanceof Benefit && $request->query('auth') === 'login' ? 'login' : 'register';
            if ($resource) {
                app(AnalyticsTracker::class)->journeyIntentStarted($resource);
                app(AnalyticsTracker::class)->journeyAuthStarted($resource, $authRoute);
            }

            return $resource ? route($authRoute) : route('login');
        });

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'partner' => EnsurePartner::class,
            'promoter' => EnsurePromoter::class,
        ]);

        $middleware->trustProxies(
            at: ['172.18.0.0/16', '10.94.0.0/24'],
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
            fn (Request $request) => $request->is('api/*', 'internal/identity/*') || $request->expectsJson(),
        );
    })->create();
