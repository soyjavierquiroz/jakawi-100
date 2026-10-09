<?php

namespace App\Observers;

use App\Models\ChallengeParticipation;
use App\Models\ExperienceReservation;
use App\Models\Membership;
use App\Models\MembershipPurchaseRequest;
use App\Models\PartnerApplication;
use App\Models\ProgramApplication;
use App\Models\Redemption;
use App\Models\UnlockParticipation;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Crm\CrmContactProjectionService;
use Illuminate\Database\Eloquent\Model;

final class CrmOutcomeObserver
{
    public function created(Model $m): void
    {
        // Signup is signaled explicitly after attribution association, not at User insert.
        $event = match (true) {
            $m instanceof UserProfile && $m->city => 'profile_updated',
            $m instanceof PartnerApplication => 'partner_application_submitted',$m instanceof ProgramApplication => 'program_application_submitted',$m instanceof MembershipPurchaseRequest => 'membership_purchase_requested',$m instanceof ExperienceReservation => 'experience_reserved',$m instanceof ChallengeParticipation => 'challenge_joined',default => null
        };
        if ($event) {
            $this->signal($m, $event, $m instanceof PartnerApplication ? ($m->marketing_opt_in ? 'GRANT' : 'REVOKE') : 'UNCHANGED');
        }
        $this->state($m);
    }

    public function updated(Model $m): void
    {
        if ($m instanceof User && $m->wasChanged(['email', 'name', 'marketing_opt_in', 'email_verified_at'])) {
            $this->signal($m, $m->wasChanged('marketing_opt_in') ? 'marketing_preference_changed' : 'profile_updated', $m->wasChanged('marketing_opt_in') ? ($m->marketing_opt_in ? 'GRANT' : 'REVOKE') : 'UNCHANGED');
        } elseif ($m instanceof UserProfile && $m->wasChanged('city')) {
            $this->signal($m, 'profile_updated');
        } elseif ($m->wasChanged('status')) {
            $this->state($m);
        }
    }

    private function state(Model $m): void
    {
        $event = match (true) {
            $m instanceof Membership && $m->status === Membership::STATUS_ACTIVE => 'membership_activated',$m instanceof Membership => 'membership_updated',$m instanceof Redemption && $m->status === Redemption::STATUS_CONFIRMED => 'benefit_redeemed',$m instanceof UnlockParticipation && $m->status === UnlockParticipation::COMMITTED => 'unlock_committed',default => null
        };
        if ($event) {
            $this->signal($m, $event);
        }
    }

    private function signal(Model $m, string $event, string $action = 'UNCHANGED'): void
    {
        app(CrmContactProjectionService::class)->signal($m, $event, $action);
    }
}
