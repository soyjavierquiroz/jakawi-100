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
            'canSupport' => fn () => ! $request->attributes->has('support_session') && app(\App\Services\Support\SupportAccess::class)->allows($request->user()),
            'supportMode' => \Inertia\Inertia::always(function () use ($request) {
                $support = $request->attributes->get('support_session');
                return $support ? ['id' => $support->id, 'label' => $request->user()->name.($support->partner_id ? ' / '.\App\Models\Partner::find($support->partner_id)?->name : ''), 'expiresAt' => $support->expires_at->toIso8601String(), 'mode' => $support->mode, 'actor' => \App\Models\User::find($support->actor_user_id)?->name, 'partner' => \App\Models\Partner::find($support->partner_id)?->name] : null;
            }),
            'partnerAccess' => fn () => $request->attributes->get('partner_access'),
            'prelaunchMode' => \Inertia\Inertia::always((bool) config('jakawi.prelaunch_mode')),
            'showDemoBanner' => \Inertia\Inertia::always(fn () => \App\Models\AppSetting::showDemoBanner()),
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
                'user' => $request->user() ? ($request->attributes->has('support_session') ? $request->user()->only(['id', 'name', 'email', 'is_admin', 'theme_preference', 'email_verified_at']) : $request->user()->toArray()) + ['is_partner_only' => $request->user()->isPartnerOnly()] : null,
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
