<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Unlock;
use App\Models\UnlockStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnlockStatusService
{
    public function transition(Unlock $unlock, string $to, ?User $actor = null, ?string $reason = null, array $allowed = []): Unlock
    {
        return DB::transaction(function () use ($unlock, $to, $actor, $reason, $allowed) {
            $locked = Unlock::lockForUpdate()->findOrFail($unlock->id);
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }

            if ($allowed !== [] && ! in_array($from, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Esta transición no está permitida.']);
            }

            if ($to === Unlock::ACTIVE && $locked->jp_deposit > 0 && ! config('unlocks.jp_commitments_enabled')) {
                throw ValidationException::withMessages(['status' => 'Los desbloqueos con garantía JP no pueden activarse todavía.']);
            }

            $locked->status = $to;
            $locked->status_changed_at = now();

            if ($to === Unlock::GOAL_REACHED) {
                $locked->goal_reached_at ??= now();
            }

            $locked->save();

            UnlockStatusHistory::create([
                'unlock_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_user_id' => $actor?->id,
                'reason' => $reason,
            ]);

            if (in_array($to, [Unlock::GOAL_NOT_REACHED, Unlock::CANCELLED], true)) {
                app(UnlockParticipationService::class)->releaseForUnlock($locked, $to);
            }

            if ($actor) {
                AuditLog::create([
                    'actor_user_id' => $actor->id,
                    'action' => 'unlock_status_changed',
                    'subject_type' => Unlock::class,
                    'subject_id' => $locked->id,
                    'metadata' => ['from' => $from, 'to' => $to, 'reason' => $reason],
                ]);
            }

            return $locked;
        });
    }

    public function activateDueScheduled(): int
    {
        $count = 0;

        Unlock::query()
            ->where('status', Unlock::SCHEDULED)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('commitment_deadline')->orWhere('commitment_deadline', '>=', now()))
            ->chunkById(100, function ($items) use (&$count) {
                foreach ($items as $unlock) {
                    $activated = DB::transaction(function () use ($unlock) {
                        $locked = Unlock::lockForUpdate()->findOrFail($unlock->id);

                        if ($locked->status !== Unlock::SCHEDULED
                            || $locked->starts_at === null
                            || $locked->starts_at->isFuture()
                            || ($locked->commitment_deadline !== null && $locked->commitment_deadline->isPast())) {
                            return false;
                        }

                        $this->transition($locked, Unlock::ACTIVE, null, null, [Unlock::SCHEDULED]);

                        return true;
                    });

                    if ($activated) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function expireMissedGoals(): int
    {
        $count = 0;

        Unlock::whereIn('status', [Unlock::ACTIVE, Unlock::SCHEDULED])
            ->whereNotNull('commitment_deadline')
            ->where('commitment_deadline', '<', now())
            ->chunkById(100, function ($items) use (&$count) {
                foreach ($items as $unlock) {
                    if ($unlock->committedCount() < $unlock->minimum_commitments) {
                        $this->transition($unlock, Unlock::GOAL_NOT_REACHED, null, 'Commitment deadline elapsed', [Unlock::ACTIVE, Unlock::SCHEDULED]);
                        $count++;
                    }
                }
            });

        return $count;
    }
}
