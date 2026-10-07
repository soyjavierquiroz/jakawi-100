<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsTracker;
use App\Services\SelectedCity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OpportunityAnalyticsController extends Controller
{
    public function store(Request $request, AnalyticsTracker $analytics, SelectedCity $selectedCity): Response
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(['opportunity_impression', 'opportunity_opened'])],
            'opportunity_type' => ['required', Rule::in(['BENEFIT', 'EXPERIENCE', 'UNLOCK', 'CHALLENGE'])],
            'source_id' => ['required', 'string', 'max:255'],
            'surface' => ['required', Rule::in(['HOME', 'EXPLORE', 'SEARCH'])],
            'section' => ['required', Rule::in(['HERO', 'FOR_YOU', 'HAPPENING_NOW', 'DISCOVER_MORE', 'RESULTS'])],
            // Positions are zero-based within their rendered section or result list.
            'position' => ['required', 'integer', 'min:0'],
            'category' => ['nullable', Rule::in(config('jakawi.categories'))],
        ]);
        if (in_array($data['opportunity_type'], ['UNLOCK','CHALLENGE'], true) && ($data['category'] ?? null) !== null) {
            throw ValidationException::withMessages(['category' => 'Unlock opportunities do not have a category.']);
        }

        $city = $selectedCity->resolve($request);
        $analytics->record($data['event'], [], [
            'opportunity_type' => $data['opportunity_type'],
            'source_id' => $data['source_id'],
            'city_id' => (string) $city->id,
            'city_slug' => $city->slug,
            'surface' => $data['surface'],
            'section' => $data['section'],
            'position' => (string) $data['position'],
            ...(($data['category'] ?? null) === null ? [] : ['category' => $data['category']]),
        ]);

        return response()->noContent();
    }
}
