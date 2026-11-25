<?php

namespace App\Services;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ExperienceCheckInService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function code(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            } if (! ExperienceReservation::where('check_in_code', $code)->exists()) {
                return $code;
            }
        } throw new DomainException('Could not generate a unique check-in code.');
    }

    public function checkIn(User $actor, ExperienceReservation $reservation): ExperienceReservation
    {
        return DB::transaction(function () use ($actor, $reservation): ExperienceReservation {
            ExperienceSession::query()->lockForUpdate()->findOrFail($reservation->experience_session_id);
            $reservation = ExperienceReservation::query()->with('session.experience')->lockForUpdate()->findOrFail($reservation->id);
            if (! $actor->managesPartner($reservation->partner_id)) {
                abort(403);
            } if ($reservation->checked_in_at) {
                return $reservation;
            }
            if ($reason = $this->blocker($reservation)) {
                throw new DomainException($reason);
            }
            $reservation->update(['checked_in_at' => now(), 'checked_in_by_user_id' => $actor->id]);

            return $reservation->refresh();
        });
    }

    /** Read-only eligibility, shared by service and review screen. All dates are instants. */
    public function blocker(ExperienceReservation $reservation): ?string
    {
        if ($reservation->checked_in_at) {
            return null;
        }
        $session = $reservation->session;
        if ($reservation->status !== ExperienceReservation::STATUS_CONFIRMED) {
            return 'La reserva debe estar confirmada para registrar asistencia.';
        }
        if (! $session || ! $session->isScheduled() || $session->reservation_partner_id !== $reservation->partner_id) {
            return 'La sesión no está disponible para registrar asistencia.';
        }
        if (now()->lt($session->starts_at)) {
            return 'Aún no comienza la sesión. Registra la asistencia durante su horario.';
        }
        $end = $session->operationalEnd();
        if (! $end || $end->lte($session->starts_at)) {
            return 'La sesión no tiene una hora de fin o duración válida. Contacta a JAKAWI.';
        }
        if (now()->gt($end)) {
            return 'El horario de asistencia venció al finalizar la sesión.';
        }

        return null;
    }
}
