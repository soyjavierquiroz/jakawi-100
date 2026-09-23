<?php

namespace App\Services;

use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExperienceReservationService
{
    public function request(User $user, Experience $experience, int $sessionId): ExperienceReservation
    {
        return DB::transaction(function () use ($user, $experience, $sessionId): ExperienceReservation {
            $session = ExperienceSession::query()->lockForUpdate()->with('reservationPartner')->findOrFail($sessionId);
            if (! $user->hasActiveMembership() || ! $experience->isPublished() || $experience->reservation_method !== 'jakawi'
                || $session->experience_id !== $experience->id || ! $session->isUpcoming()
                || ! $session->reservationPartner?->isPublished()
                || ! $experience->partners()->whereKey($session->reservation_partner_id)->exists()) {
                throw ValidationException::withMessages(['session' => 'Esta sesión no está disponible para reserva.']);
            }

            $existing = ExperienceReservation::query()->where('user_id', $user->id)->where('experience_session_id', $session->id)
                ->whereIn('status', [ExperienceReservation::STATUS_PENDING, ExperienceReservation::STATUS_CONFIRMED])->first();
            if ($existing) {
                return $existing;
            }

            return ExperienceReservation::create([
                'user_id' => $user->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id,
                'partner_id' => $session->reservation_partner_id, 'status' => ExperienceReservation::STATUS_PENDING,
            ]);
        });
    }

    public function respond(User $actor, ExperienceReservation $reservation, string $status): void
    {
        abort_unless(in_array($status, [ExperienceReservation::STATUS_CONFIRMED, ExperienceReservation::STATUS_REJECTED], true), 404);
        abort_unless($actor->managesPartner($reservation->partner_id), 403);
        abort_unless($reservation->status === ExperienceReservation::STATUS_PENDING, 422);
        $reservation->update(['status' => $status, 'responded_at' => now(), 'responded_by_user_id' => $actor->id]);
    }

    public function cancel(User $actor, ExperienceReservation $reservation): void
    {
        abort_unless($reservation->user_id === $actor->id, 403);
        $reservation->loadMissing('session');
        abort_unless(in_array($reservation->status, [ExperienceReservation::STATUS_PENDING, ExperienceReservation::STATUS_CONFIRMED], true) && $reservation->session->starts_at->isFuture(), 422);
        $reservation->update(['status' => ExperienceReservation::STATUS_CANCELLED, 'cancelled_at' => now()]);
    }
}
