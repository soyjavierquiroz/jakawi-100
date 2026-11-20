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
    public function __construct(private readonly ExperienceCheckInService $checkIns) {}

    public function request(User $user, Experience $experience, int $sessionId, int $partySize = 1): ExperienceReservation
    {
        \App\Support\PrelaunchOperations::assertAllowed();

        return DB::transaction(function () use ($user, $experience, $sessionId, $partySize): ExperienceReservation {
            $session = ExperienceSession::query()->lockForUpdate()->with('reservationPartner')->findOrFail($sessionId);
            if (($experience->catalog_visibility !== 'legacy' && ! app(ExperiencePublication::class)->canOperate($experience)) || ! $user->hasActiveMembership() || ! $experience->isPublished() || $experience->reservation_method !== 'jakawi'
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

            if ($partySize < 1 || $partySize > ExperienceReservation::MAX_PARTY_SIZE) {
                throw ValidationException::withMessages(['party_size' => 'El grupo debe tener entre 1 y 10 personas.']);
            }
            $this->assertCapacity($session, $partySize);

            return ExperienceReservation::create([
                'user_id' => $user->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id,
                'partner_id' => $session->reservation_partner_id, 'status' => ExperienceReservation::STATUS_PENDING, 'party_size' => $partySize, 'check_in_code' => $this->checkIns->code(),
            ]);
        });
    }

    public function respond(User $actor, ExperienceReservation $reservation, string $status): void
    {
        \App\Support\PrelaunchOperations::assertAllowed();

        abort_unless(in_array($status, [ExperienceReservation::STATUS_CONFIRMED, ExperienceReservation::STATUS_REJECTED], true), 404);
        DB::transaction(function () use ($actor, $reservation, $status): void {
            $session = ExperienceSession::query()->lockForUpdate()->findOrFail($reservation->experience_session_id);
            $current = ExperienceReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_unless($actor->managesPartner($current->partner_id), 403);
            if ($current->status === $status) {
                return;
            }
            if ($current->status !== ExperienceReservation::STATUS_PENDING) {
                throw ValidationException::withMessages(['reservation' => 'Esta solicitud ya fue respondida o cancelada. Actualiza el listado antes de continuar.']);
            }
            if ($status === ExperienceReservation::STATUS_CONFIRMED) {
                if (! $session->isUpcoming() || $session->reservation_partner_id !== $current->partner_id) {
                    throw ValidationException::withMessages(['reservation' => 'La sesión ya comenzó, fue cancelada o cambió de responsable. No puede confirmarse.']);
                }
                $this->assertCapacity($session, $current->party_size);
            }
            $current->update(['status' => $status, 'responded_at' => now(), 'responded_by_user_id' => $actor->id]);
        });
    }

    public function cancel(User $actor, ExperienceReservation $reservation): void
    {
        \App\Support\PrelaunchOperations::assertAllowed();

        DB::transaction(function () use ($actor, $reservation): void {
            $session = ExperienceSession::query()->lockForUpdate()->findOrFail($reservation->experience_session_id);
            $current = ExperienceReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_unless($current->user_id === $actor->id, 403);
            if ($current->status === ExperienceReservation::STATUS_CANCELLED) {
                return;
            }
            abort_unless(in_array($current->status, [ExperienceReservation::STATUS_PENDING, ExperienceReservation::STATUS_CONFIRMED], true) && $session->starts_at->isFuture(), 422);
            $current->update(['status' => ExperienceReservation::STATUS_CANCELLED, 'cancelled_at' => now()]);
        });
    }

    /** Caller holds the session lock. Pending requests do not allocate confirmed seats. */
    private function assertCapacity(ExperienceSession $session, int $partySize): void
    {
        if ($session->capacity !== null && $session->confirmedAttendees() + $partySize > $session->capacity) {
            throw ValidationException::withMessages(['reservation' => 'No hay cupos suficientes para todo el grupo. La solicitud no se ha confirmado.']);
        }
    }
}
