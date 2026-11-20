<?php

namespace App\Http\Controllers;

use App\Models\ExperienceReservation;
use App\Models\Partner;
use App\Services\ExperienceCheckInService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExperienceCheckInController extends Controller
{
    public function scan(Request $request, Partner $partner, string $reservation_public_id): Response|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('partner.login'));
        }

        return $this->screen($request, $partner, $this->reservation($reservation_public_id));
    }

    public function form(Partner $partner): Response
    {
        return Inertia::render('partner/check-in', ['partner' => $partner->only(['name', 'slug'])]);
    }

    public function find(Request $request, Partner $partner): Response
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $reservation = ExperienceReservation::where('partner_id', $partner->id)->where('check_in_code', strtoupper($data['code']))->first();
        if (! $reservation) {
            return Inertia::render('partner/check-in', ['partner' => $partner->only(['name', 'slug']), 'error' => 'Código inválido. Comprueba los seis caracteres y el establecimiento.']);
        }

        return $this->screen($request, $partner, $reservation);
    }

    public function confirm(Request $request, Partner $partner, string $reservation_public_id, ExperienceCheckInService $service): Response
    {
        $reservation = $this->authorized($request, $partner, $this->reservation($reservation_public_id));
        $error = null;
        try {
            $reservation = $service->checkIn($request->user(), $reservation);
        } catch (DomainException $e) {
            $error = $e->getMessage();
            $reservation = $reservation->fresh();
        }

        return $this->screen($request, $partner, $reservation, $error);
    }

    private function reservation(string $publicId): ExperienceReservation
    {
        return ExperienceReservation::where('public_id', $publicId)->firstOrFail();
    }

    private function authorized(Request $request, Partner $partner, ExperienceReservation $reservation): ExperienceReservation
    {
        abort_unless($request->user()?->managesPartner($partner->id) && $reservation->partner_id === $partner->id, 403);

        return $reservation;
    }

    private function screen(Request $request, Partner $partner, ExperienceReservation $reservation, ?string $error = null): Response
    {
        $reservation = $this->authorized($request, $partner, $reservation);
        $reservation->load(['user:id,name', 'experience:id,title', 'session.location', 'session.experience']);

        return Inertia::render('partner/check-in-review', ['partner' => $partner->only(['name', 'slug']), 'error' => $error, 'checkInBlocker' => app(ExperienceCheckInService::class)->blocker($reservation), 'reservation' => ['public_id' => $reservation->public_id, 'check_in_code' => $reservation->check_in_code, 'member_display_name' => $this->memberDisplayName($reservation->user->name), 'experience' => $reservation->experience->title, 'party_size' => $reservation->party_size, 'starts_at' => $reservation->session->starts_at, 'ends_at' => $reservation->session->operationalEnd(), 'timezone' => $reservation->session->displayTimezone(), 'venue' => $reservation->session->location?->name ?? $reservation->session->venue_label, 'status' => $reservation->status, 'checked_in_at' => $reservation->checked_in_at]]);
    }

    private function memberDisplayName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (! $parts) {
            return 'Miembro';
        }

        return count($parts) === 1 ? $parts[0] : $parts[0].' '.mb_substr($parts[array_key_last($parts)], 0, 1).'.';
    }
}
