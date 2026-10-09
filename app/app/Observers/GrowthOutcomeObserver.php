<?php

namespace App\Observers;

use App\Models\ChallengeParticipation;
use App\Models\ExperienceReservation;
use App\Models\Membership;
use App\Models\MembershipPurchaseRequest;
use App\Models\Redemption;
use App\Models\UnlockParticipation;
use App\Services\GrowthMeasurementService;
use Illuminate\Database\Eloquent\Model;

class GrowthOutcomeObserver
{
    public function created(Model $source): void
    {
        $event = match (true) {
            $source instanceof MembershipPurchaseRequest => 'membership_purchase_requested',
            $source instanceof ExperienceReservation => 'experience_reserved',
            $source instanceof ChallengeParticipation => 'challenge_joined',
            default => null,
        };
        if ($event) app(GrowthMeasurementService::class)->outcome($event, $source);
        $this->stateChanged($source);
    }

    public function updated(Model $source): void
    {
        if ($source->wasChanged('status')) $this->stateChanged($source);
    }

    private function stateChanged(Model $source): void
    {
        $event = match (true) {
            $source instanceof Membership && $source->status === Membership::STATUS_ACTIVE => 'membership_activated',
            $source instanceof Redemption && $source->status === Redemption::STATUS_CONFIRMED => 'benefit_redeemed',
            $source instanceof UnlockParticipation && $source->status === UnlockParticipation::COMMITTED => 'unlock_committed',
            default => null,
        };
        if ($event) app(GrowthMeasurementService::class)->outcome($event, $source);
    }
}
