<?php

namespace App\Services;

use App\Models\ExperienceReservation;
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
            $reservation = ExperienceReservation::query()->with('session')->lockForUpdate()->findOrFail($reservation->id);
            if (! $actor->managesPartner($reservation->partner_id)) {
                abort(403);
            } if ($reservation->checked_in_at) {
                return $reservation;
            } if ($reservation->status !== ExperienceReservation::STATUS_CONFIRMED || ! $reservation->session || $reservation->session->starts_at->isPast()) {
                throw new DomainException('This reservation cannot be checked in.');
            } $reservation->update(['checked_in_at' => now(), 'checked_in_by_user_id' => $actor->id]);

            return $reservation->refresh();
        });
    }
}
