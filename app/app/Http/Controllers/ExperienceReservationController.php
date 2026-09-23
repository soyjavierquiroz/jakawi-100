<?php

namespace App\Http\Controllers;

use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Services\AnalyticsTracker;
use App\Services\ExperienceReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExperienceReservationController extends Controller
{
    public function store(Request $request, Experience $experience, ExperienceReservationService $service, AnalyticsTracker $analytics): RedirectResponse
    {
        $data = $request->validate(['experience_session_id' => ['required', 'integer']]);
        $before = ExperienceReservation::query()->where('user_id', $request->user()->id)->where('experience_session_id', $data['experience_session_id'])->whereIn('status', ['pending', 'confirmed'])->exists();
        $reservation = $service->request($request->user(), $experience, $data['experience_session_id']);
        if (! $before && $reservation->wasRecentlyCreated) $analytics->experienceReserveClicked($experience, 'jakawi');
        return back();
    }

    public function cancel(Request $request, ExperienceReservation $reservation, ExperienceReservationService $service): RedirectResponse
    {
        $service->cancel($request->user(), $reservation);
        return back();
    }
}
