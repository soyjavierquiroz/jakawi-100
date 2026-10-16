<?php

namespace App\Http\Controllers;

use App\Models\ExperienceReservation;
use App\Models\Partner;
use App\Services\ExperienceReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerReservationController extends Controller
{
    public function index(Request $request, Partner $partner): Response
    {
        $query = ExperienceReservation::with(['user:id,name', 'experience', 'session.location', 'partner'])
            ->where('partner_id', $partner->id);
        $confirmedTotals = (clone $query)->where('status', ExperienceReservation::STATUS_CONFIRMED)
            ->selectRaw('experience_session_id, sum(party_size) as confirmed_attendee_count')
            ->groupBy('experience_session_id')->pluck('confirmed_attendee_count', 'experience_session_id');
        $data = fn ($items) => $items->map(fn ($r) => [
            'public_id' => $r->public_id, 'status' => $r->status, 'member_name' => $r->user->name,
            'party_size' => $r->party_size, 'requested_at' => $r->created_at, 'experience' => $r->experience->title,
            'partner' => $r->partner->name, 'partner_slug' => $partner->slug, 'starts_at' => $r->session->starts_at,
            'venue' => $r->session->location?->name ?? $r->session->venue_label, 'capacity' => $r->session->capacity,
            'confirmed_attendee_count' => (int) ($confirmedTotals[$r->experience_session_id] ?? 0), 'checked_in_at' => $r->checked_in_at,
        ]);

        $pending = \App\Support\ListingPage::make((clone $query)->where('status', 'pending')->orderBy('created_at'), 25, 'pending_page');
        $upcoming = \App\Support\ListingPage::make((clone $query)->where('status', 'confirmed')->whereHas('session', fn ($q) => $q->where('starts_at', '>', now()))->orderBy('experience_session_id'), 25, 'upcoming_page');
        $history = \App\Support\ListingPage::make((clone $query)->whereIn('status', ['rejected', 'cancelled'])->latest(), 50, 'history_page');
        return Inertia::render('partner/reservations', [
            'pagination' => ['pending' => array_diff_key($pending->toArray(), ['data' => true]), 'upcomingConfirmed' => array_diff_key($upcoming->toArray(), ['data' => true]), 'history' => array_diff_key($history->toArray(), ['data' => true])],
            'partner' => $partner->only(['name', 'slug']),
            'pending' => $data($pending->getCollection()),
            'upcomingConfirmed' => $data($upcoming->getCollection()),
            'history' => $data($history->getCollection()),
        ]);
    }

    public function respond(Request $request, Partner $partner, ExperienceReservationService $service, string $reservation_public_id, string $status): RedirectResponse
    {
        $reservation = ExperienceReservation::where('public_id', $reservation_public_id)->firstOrFail();
        abort_unless($reservation->partner_id === $partner->id, 403);
        $service->respond($request->user(), $reservation, $status);

        return back();
    }
}
