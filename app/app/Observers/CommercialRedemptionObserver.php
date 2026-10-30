<?php

namespace App\Observers;

use App\Models\Redemption;
use App\Services\Commercial\CommercialOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CommercialRedemptionObserver
{
    public function created(Redemption $redemption): void
    {
        if (! config('commercial.enabled')) {
            return;
        }

        try {
            // Savepoint prevents an integration failure from aborting a valid canje.
            DB::transaction(fn () => app(CommercialOutbox::class)->capture($redemption));
        } catch (Throwable) {
            Log::warning('Commercial origin capture unavailable.', ['redemption_id' => $redemption->id]);
        }
    }
}
