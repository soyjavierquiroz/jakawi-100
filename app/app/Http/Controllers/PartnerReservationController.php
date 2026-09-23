<?php

namespace App\Http\Controllers;

use App\Models\ExperienceReservation;
use App\Services\ExperienceReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $ids = $request->user()->is_admin ? null : $request->user()->partners()->pluck('partners.id');
        $query = ExperienceReservation::with(['experience', 'session.location', 'partner'])->when($ids !== null, fn ($q) => $q->whereIn('partner_id', $ids));
        $data = fn ($items) => $items->map(fn ($r) => ['public_id' => $r->public_id, 'status' => $r->status, 'experience' => $r->experience->title, 'partner' => $r->partner->name, 'starts_at' => $r->session->starts_at, 'venue' => $r->session->location?->name ?? $r->session->venue_label, 'capacity' => $r->session->capacity]);
        return Inertia::render('partner/reservations', [
            'pending' => $data((clone $query)->where('status', 'pending')->orderBy('created_at')->get()),
            'upcomingConfirmed' => $data((clone $query)->where('status', 'confirmed')->whereHas('session', fn ($q) => $q->where('starts_at', '>', now()))->orderBy('experience_session_id')->get()),
            'history' => $data((clone $query)->whereIn('status', ['rejected', 'cancelled'])->latest()->take(30)->get()),
        ]);
    }
    public function respond(Request $request, ExperienceReservation $reservation, ExperienceReservationService $service, string $status): RedirectResponse
    {
        $service->respond($request->user(), $reservation, $status);
        return back();
    }
}
