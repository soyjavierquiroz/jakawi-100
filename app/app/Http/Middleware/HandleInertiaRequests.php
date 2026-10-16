<?php

namespace App\Http\Middleware;

use App\Services\SelectedCity;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $selectedCity = app(SelectedCity::class);

        return [
            ...parent::share($request),
            'metaBrowser' => \Inertia\Inertia::always(fn () => app(\App\Services\Meta\MetaBrowserContext::class)->forRequest($request)),
            'name' => config('app.name'),
            'prelaunchMode' => \Inertia\Inertia::always((bool) config('jakawi.prelaunch_mode')),
            'prelaunchPublic' => \Inertia\Inertia::always(
                (bool) config('jakawi.prelaunch_mode') && $request->routeIs(
                    'explore', 'benefits.*', 'experiences.*', 'social-challenges.*',
                    'unlocks.*', 'landing-presentations.*', 'public-landings.*',
                    'partners.*', 'locations.*', 'cities.*', 'programs.*',
                    'membership.*', 'mi-jakawi', 'consumer.profile', 'member.profile.*',
                    'redemptions.intent', 'redemptions.show',
                ) && ! $request->routeIs('membership.register'),
            ),
            'newAcquisitionAccount' => $request->user() && $request->session()->get('acquisition_new_user_id') === $request->user()->id,
            'accessEmailQueued' => (bool) $request->session()->get('acquisition_access_email_queued', false),
            'communityBubble' => \Inertia\Inertia::always(fn () => $request->routeIs(
                'explore', 'partners.public', 'partners.show', 'locations.show', 'benefits.show', 'experiences.show', 'mi-jakawi',
            ) ? app(\App\Services\CityCommunity::class)->data($request, 'floating') : null),
            'benefitJourney' => fn () => $this->benefitJourney($request),
            'auth' => [
                'user' => $request->user() ? $request->user()->toArray() + ['is_partner_only' => $request->user()->isPartnerOnly()] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'selectedCity' => fn () => $selectedCity->data($selectedCity->resolve($request)),
            'availableCities' => fn () => $selectedCity->available(),
        ];
    }
    private function benefitJourney(Request $request): ?array
    {
        if (! $request->is('login', 'register', 'membresia')) {
            return null;
        }
        $intent = app(\App\Services\PublicJourneyContinuation::class)->get();
        if (($intent['journey'] ?? null) !== 'BENEFIT') {
            return null;
        }
        $benefit = \App\Models\Benefit::with('partner')->find($intent['resource_id']);
        return $benefit ? ['title' => $benefit->title, 'partner' => $benefit->partner?->name] : null;
    }
}
