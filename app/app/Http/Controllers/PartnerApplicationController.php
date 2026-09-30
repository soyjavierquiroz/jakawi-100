<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\AnalyticsTracker;
use App\Services\PartnerApplicationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerApplicationController extends Controller
{
    public function create(City $city, AnalyticsTracker $analytics): Response
    {
        $this->available($city); $analytics->record('partner_application_started', [], $this->metadata($city));
        return Inertia::render('cities/partner-application', ['city' => $city->only(['id', 'name', 'slug'])]);
    }
    public function store(Request $request, City $city, PartnerApplicationService $applications, AnalyticsTracker $analytics)
    {
        $this->available($city);
        $data = $request->validate(['business_name' => ['required', 'string', 'max:255'], 'contact_name' => ['required', 'string', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:255', 'required_without:contact_email'], 'contact_email' => ['nullable', 'email', 'max:255', 'required_without:contact_phone'], 'category' => ['nullable', 'string', 'max:100'], 'message' => ['nullable', 'string', 'max:2000']]);
        [, $created] = $applications->record($city, $request, $data);
        if ($created) $analytics->record('partner_application_submitted', [], $this->metadata($city));
        return to_route('cities.partner.success', $city);
    }
    public function success(City $city): Response { $this->available($city); return Inertia::render('cities/partner-application-success', ['city' => $city->only(['name', 'slug'])]); }
    private function available(City $city): void { abort_if($city->status === City::PAUSED, 404); }
    private function metadata(City $city): array { return ['city_id' => (string) $city->id, 'city_slug' => $city->slug]; }
}
