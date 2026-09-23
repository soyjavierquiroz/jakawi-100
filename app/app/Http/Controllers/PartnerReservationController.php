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
            'confirmed_attendee_count' => (int) ($confirmedTotals[$r->experience_session_id] ?? 0),
        ]);

        return Inertia::render('partner/reservations', [
            'partner' => $partner->only(['name', 'slug']),
            'pending' => $data((clone $query)->where('status', 'pending')->orderBy('created_at')->get()),
            'upcomingConfirmed' => $data((clone $query)->where('status', 'confirmed')->whereHas('session', fn ($q) => $q->where('starts_at', '>', now()))->orderBy('experience_session_id')->get()),
            'history' => $data((clone $query)->whereIn('status', ['rejected', 'cancelled'])->latest()->take(30)->get()),
        ]);
    }

    public function respond(Request $request, Partner $partner, ExperienceReservation $reservation, ExperienceReservationService $service, string $status): RedirectResponse
    {
        abort_unless($reservation->partner_id === $partner->id, 403);
        $service->respond($request->user(), $reservation, $status);

        return back();
    }
}
