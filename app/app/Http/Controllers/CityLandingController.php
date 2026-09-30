<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\CityInterestService;
use App\Services\SelectedCity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CityLandingController extends Controller
{
    public function show(Request $request, City $city, AnalyticsTracker $analytics): Response|RedirectResponse
    {
        if ($city->status === City::ACTIVE) {
            return redirect()->route('home')->withCookie(cookie(SelectedCity::COOKIE, $city->slug, 60 * 24 * 365, '/', null, null, true, false, 'lax'));
        }

        $analytics->record('city_viewed', [], $this->analyticsMetadata($city, $request));

        return Inertia::render('cities/show', [
            'city' => $city->only(['id', 'name', 'slug', 'status']),
            'interestCount' => $city->interests()->count(),
            'interestRecorded' => $this->hasInterest($city, $request),
        ]);
    }

    public function interest(Request $request, City $city, CityInterestService $interests, AnalyticsTracker $analytics): RedirectResponse
    {
        abort_if($city->status === City::ACTIVE, 404);
        abort_if($city->status === City::PAUSED, 422, 'Esta ciudad está temporalmente en pausa.');

        [, $created] = $interests->record($city, $request);

        if ($created) {
            $analytics->record('city_interest_recorded', [], $this->analyticsMetadata($city, $request));
        }

        return to_route('cities.show', $city);
    }

    private function hasInterest(City $city, Request $request): bool
    {
        $user = $request->user();
        $visitorId = app(AttributionService::class)->anonymousId($request);

        return $city->interests()->when($user, fn ($query) => $query->where('user_id', $user->id), fn ($query) => $query->where('visitor_id', $visitorId))->exists();
    }

    /** @return array<string, string> */
    private function analyticsMetadata(City $city, Request $request): array
    {
        return ['city_id' => (string) $city->id, 'city_slug' => $city->slug, 'authenticated' => $request->user() ? 'true' : 'false'];
    }
}
