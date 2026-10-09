<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\SelectedCity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnersLandingController extends Controller
{
    public function show(Request $request, SelectedCity $selectedCity, AttributionService $attribution, AnalyticsTracker $analytics): Response
    {
        $city = $selectedCity->resolve($request);

        // The existing attribution service owns visitor identity, UTMs and landing context.
        // Keep the public aliases within its established fields.
        if ($request->filled('campaign') && ! $request->filled('utm_campaign')) {
            $request->merge(['utm_campaign' => $request->query('campaign')]);
        }
        $ref = $request->query('ref');
        $referrer = is_string($ref) ? $attribution->findReferrer($ref) : null;
        $touch = $attribution->recordLandingTouch($request, $referrer);
        if ($touch) $request->session()->push('attribution_touch_ids', $touch->id);

        $analytics->record('landing_view', [], ['landing' => 'partners']);

        return Inertia::render('partners/index', [
            'city' => $city->only(['name', 'slug']),
            'canonical' => route('partners.index'),
        ]);
    }

    public function apply(Request $request, SelectedCity $selectedCity, AnalyticsTracker $analytics): RedirectResponse
    {
        $city = $selectedCity->resolve($request);
        $analytics->record('landing_cta_click', [], ['landing' => 'partners']);

        return to_route('cities.partner.create', $city);
    }
}
