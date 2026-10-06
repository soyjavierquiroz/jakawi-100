<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\OwnershipAssignment;
use App\Models\PartnerApplication;
use App\Models\ProgramApplication;
use App\Models\ProgramEnrollment;
use App\Models\ReferralRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnershipAssignmentService
{
    public function current(User|PartnerApplication|ProgramApplication $target): ?OwnershipAssignment
    {
        return OwnershipAssignment::query()->where('target_type', $this->targetType($target))
            ->where('target_id', $target->getKey())->whereNull('ended_at')->first();
    }

    public function eligible(User $owner): bool
    {
        return $owner->is_admin || $owner->programEnrollments()->active()
            ->whereIn('program_type', [ProgramEnrollment::TYPE_AFFILIATE, ProgramEnrollment::TYPE_CREATOR, ProgramEnrollment::TYPE_PROMOTER])->exists();
    }

    public function assignIfUnowned(User|PartnerApplication|ProgramApplication $target, User $owner, string $source, ?User $actor = null, ?string $reason = null): OwnershipAssignment
    {
        return DB::transaction(function () use ($target, $owner, $source, $actor, $reason): OwnershipAssignment {
            $this->lockTarget($target);
            if ($current = $this->current($target)) return $current;
            $this->validateOwner($target, $owner);
            return $this->create($target, $owner, $source, $actor, $reason);
        });
    }

    public function reassign(User|PartnerApplication|ProgramApplication $target, User $owner, User $actor, string $reason): OwnershipAssignment
    {
        abort_unless($actor->is_admin, 403);
        return DB::transaction(function () use ($target, $owner, $actor, $reason): OwnershipAssignment {
            $this->lockTarget($target);
            $this->validateOwner($target, $owner);
            $current = $this->current($target);
            if ($current?->owner_user_id === $owner->id) return $current;
            if ($current) $current->update(['ended_at' => now()]);
            $assignment = $this->create($target, $owner, OwnershipAssignment::SOURCE_MANUAL, $actor, $reason);
            $this->audit($target, $actor, $current ? 'ownership_reassigned' : 'ownership_assigned', $current?->owner_user_id, $owner->id, $reason);
            return $assignment;
        });
    }

    public function unassign(User|PartnerApplication|ProgramApplication $target, User $actor, string $reason): void
    {
        abort_unless($actor->is_admin, 403);
        DB::transaction(function () use ($target, $actor, $reason): void {
            $this->lockTarget($target);
            $current = $this->current($target);
            if (! $current) return;
            $current->update(['ended_at' => now()]);
            $this->audit($target, $actor, 'ownership_unassigned', $current->owner_user_id, null, $reason);
        });
    }

    public function inheritFromUser(PartnerApplication|ProgramApplication $application, User $user): ?OwnershipAssignment
    {
        if ($application->user_id !== $user->id) return null;
        return DB::transaction(function () use ($application, $user): ?OwnershipAssignment {
            $this->lockTarget($user);
            $current = $this->current($user);
            return $current && $current->owner && $this->eligible($current->owner)
                ? $this->assignIfUnowned($application, $current->owner, OwnershipAssignment::SOURCE_USER_OWNERSHIP) : null;
        });
    }

    public function autoAssignUserFromReferral(User $user): ?OwnershipAssignment
    {
        $relationship = ReferralRelationship::query()->where('referred_user_id', $user->id)
            ->where('status', ReferralRelationship::STATUS_ACTIVE)->first();
        if (! $relationship?->isValid() || ! $relationship->referrer_user_id) return null;
        $referrer = $relationship->referrer;
        return $referrer && $referrer->id !== $user->id && $this->eligible($referrer)
            ? $this->assignIfUnowned($user, $referrer, OwnershipAssignment::SOURCE_REFERRAL_RELATIONSHIP) : null;
    }

    public function autoAssignGuestPartnerApplication(PartnerApplication $application): ?OwnershipAssignment
    {
        if ($application->user_id !== null || ! $application->visitor_id) return null;
        $touch = $application->attributionTouch;
        if (! $touch?->referral_code || ! $touch->referrer_user_id
            || $touch->user_id !== null || $touch->anonymous_id !== $application->visitor_id) return null;
        $referrer = $touch->referrer;
        return $referrer && $this->eligible($referrer)
            ? $this->assignIfUnowned($application, $referrer, OwnershipAssignment::SOURCE_CONVERSION_REFERRAL) : null;
    }

    private function validateOwner(User|PartnerApplication|ProgramApplication $target, User $owner): void
    {
        $owner = User::findOrFail($owner->id);
        if (! $this->eligible($owner) || ($target instanceof User && $target->id === $owner->id)
            || (($target instanceof PartnerApplication || $target instanceof ProgramApplication) && $target->user_id === $owner->id)) {
            throw ValidationException::withMessages(['owner_user_id' => 'Responsable no elegible para este objetivo.']);
        }
    }

    private function lockTarget(User|PartnerApplication|ProgramApplication $target): void
    {
        $target::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
    }

    private function create(User|PartnerApplication|ProgramApplication $target, User $owner, string $source, ?User $actor, ?string $reason): OwnershipAssignment
    {
        return OwnershipAssignment::create([
            'target_type' => $this->targetType($target), 'target_id' => $target->getKey(),
            'owner_user_id' => $owner->id, 'source' => $source,
            'assigned_by_user_id' => $actor?->id, 'reason' => $reason, 'assigned_at' => now(),
        ]);
    }

    private function targetType(User|PartnerApplication|ProgramApplication $target): string
    {
        return match (true) {
            $target instanceof User => OwnershipAssignment::TARGET_USER,
            $target instanceof PartnerApplication => OwnershipAssignment::TARGET_PARTNER_APPLICATION,
            $target instanceof ProgramApplication => OwnershipAssignment::TARGET_PROGRAM_APPLICATION,
        };
    }

    private function audit(User|PartnerApplication|ProgramApplication $target, User $actor, string $action, ?int $before, ?int $after, string $reason): void
    {
        AuditLog::create(['actor_user_id' => $actor->id, 'action' => $action,
            'subject_type' => $target::class, 'subject_id' => $target->getKey(),
            'metadata' => ['before_owner_user_id' => $before, 'after_owner_user_id' => $after, 'reason' => $reason]]);
    }
}
