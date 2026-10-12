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
        $data = $request->validate([
            'experience_session_id' => ['required', 'integer'],
            'landing_presentation_id' => ['nullable', 'integer'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:'.ExperienceReservation::MAX_PARTY_SIZE],
        ]);
        $landing = $request->filled('landing_presentation_id')
            ? $experience->landingPresentations()->whereKey($request->integer('landing_presentation_id'))->where('status', 'PUBLISHED')->firstOrFail() : null;
        $analytics->journeyIntentStarted($experience);
        if (! $request->user()->hasActiveMembership() && $experience->reservation_method === 'jakawi'
            && $experience->upcomingSessions()->whereKey($data['experience_session_id'])->exists()) {
            return redirect('/membresia?'.http_build_query(['journey' => 'EXPERIENCE', 'action' => 'RESERVE',
                'resource_id' => $experience->id, 'experience_session_id' => $data['experience_session_id']] + ($landing ? ['landing_presentation_id' => $landing->id] : [])));
        }
        $before = ExperienceReservation::query()->where('user_id', $request->user()->id)->where('experience_session_id', $data['experience_session_id'])->whereIn('status', ['pending', 'confirmed'])->exists();
        $reservation = $service->request($request->user(), $experience, $data['experience_session_id'], $data['party_size'] ?? 1);
        if (! $before && $reservation->wasRecentlyCreated) {
            $analytics->experienceReserveClicked($experience, 'jakawi');
        }

        if ($landing) {
            app(\App\Services\PublicJourneyContinuation::class)->setLanding($landing);
            $request->session()->flash('landing_acquisition_resumed', $landing->id);
            $request->session()->flash('landing_continuation_context', ['experience_session_id' => $reservation->experience_session_id]);
            return redirect()->route('landing-presentations.result', ['presentation' => $landing, 'session' => $reservation->experience_session_id]);
        }
        return back();
    }

    public function cancel(Request $request, ExperienceReservation $reservation, ExperienceReservationService $service): RedirectResponse
    {
        $service->cancel($request->user(), $reservation);

        return back();
    }
}
