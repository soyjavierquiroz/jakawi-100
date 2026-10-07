<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\ChallengeParticipation;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    public function activate(User $user, User $activatedBy, ?CarbonInterface $startsAt = null, ?string $paymentMethod = null, ?string $paymentReference = null, ?string $notes = null, ?string $amountPaid = null): Membership
    {
        $membership = DB::transaction(function () use ($user, $activatedBy, $startsAt, $paymentMethod, $paymentReference, $notes, $amountPaid): Membership {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->activeMembership()->exists()) {
                throw new DomainException('The user already has an active membership.');
            }

            $startsAt ??= now();

            return Membership::create([
                'user_id' => $lockedUser->id,
                'status' => Membership::STATUS_ACTIVE,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays((int) config('jakawi.membership.duration_days')),
                'amount_paid' => $amountPaid ?? (string) config('jakawi.membership.price_bob'),
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'activated_by' => $activatedBy->id,
                'notes' => $notes,
            ]);
        });
        ChallengeParticipation::query()->where('user_id', $user->id)->where('qualification_status', 'qualified')
            ->whereDoesntHave('grant')->whereHas('challenge', fn ($q) => $q->where('reward_eligibility', 'ACTIVE_MEMBERS'))
            ->with('challenge')->get()->each(function (ChallengeParticipation $participation): void {
                if ($participation->challenge->selection_type === 'TOP_N' && $participation->challenge->status === 'closed') app(ChallengeService::class)->finalize($participation->challenge);
                else app(ChallengeService::class)->selectAndMaybeGrant($participation);
            });
        return $membership;
    }

    public function cancel(Membership $membership): Membership
    {
        return DB::transaction(function () use ($membership): Membership {
            $locked = Membership::query()->lockForUpdate()->findOrFail($membership->getKey());
            User::query()->lockForUpdate()->findOrFail($locked->user_id);
            $locked->update(['status' => Membership::STATUS_CANCELLED]);

            return $locked->refresh();
        });
    }
}
