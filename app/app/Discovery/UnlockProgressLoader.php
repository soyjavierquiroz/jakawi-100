<?php

namespace App\Discovery;

use App\Models\UnlockParticipation;
use Illuminate\Support\Facades\DB;

/** Reads committed progress for many Unlocks in one grouped aggregate query. */
final class UnlockProgressLoader
{
    /** @param list<int> $unlockIds @param array<int, int> $targets @return array<int, UnlockProgress> */
    public function forUnlocks(array $unlockIds, array $targets): array
    {
        if ($unlockIds === []) {
            return [];
        }

        $counts = DB::table('unlock_participations')
            ->whereIn('unlock_id', $unlockIds)
            ->whereIn('status', [UnlockParticipation::COMMITTED, UnlockParticipation::UNLOCKED_PENDING_CONFIRMATION, UnlockParticipation::CONFIRMED, UnlockParticipation::FULFILLED, UnlockParticipation::NO_SHOW])
            ->selectRaw('unlock_id, count(*) as progress')
            ->groupBy('unlock_id')
            ->pluck('progress', 'unlock_id');

        $progress = [];
        foreach ($unlockIds as $id) {
            $progress[$id] = new UnlockProgress($targets[$id] ?? 0, (int) ($counts[$id] ?? 0));
        }

        return $progress;
    }
}
