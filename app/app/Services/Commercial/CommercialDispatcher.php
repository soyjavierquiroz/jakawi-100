<?php

namespace App\Services\Commercial;

use Illuminate\Support\Facades\DB;
use Throwable;

final class CommercialDispatcher
{
    public function run(int $limit = 100): int
    {
        if (! app(CommercialClient::class)->ready()) {
            return 0;
        }
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Commercial dispatch requires committed domain data.');
        }
        app(CommercialOutbox::class)->collect($limit);
        $processed = 0;
        $deadline = microtime(true) + 40;
        while ($processed < min(100, max(0, $limit)) && microtime(true) < $deadline) {
            $found = DB::transaction(function (): bool {
                DB::statement("SET LOCAL lock_timeout = '2s'");
                $row = DB::table('commercial_outbox')->whereIn('status', ['PENDING', 'RETRY'])
                    ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                    ->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
                if (! $row) {
                    return false;
                }
                // A crash releases this lock and rolls local delivery state back. CLUB
                // idempotency covers a receipt lost between HTTP acceptance and commit.
                try {
                    $result = app(CommercialClient::class)->send($row->raw_body, $row->event_id);
                } catch (Throwable) {
                    $result = ['sent' => false, 'retry' => true, 'http' => null, 'code' => 'DELIVERY_UNAVAILABLE'];
                }
                // CLUB may not recognize membership.activated yet; retain and retry the original body.
                if (! $result['sent'] && in_array((json_decode($row->raw_body, true)['event_type'] ?? null), ['membership.activated', 'membership.identity_linked'], true)) {
                    $result['retry'] = true;
                }
                $attempts = $row->attempts + 1;
                $delay = min(21600, 60 * (2 ** min(9, $attempts - 1))) + random_int(0, 30);
                DB::table('commercial_outbox')->where('id', $row->id)->update([
                    'status' => $result['sent'] ? 'SENT' : ($result['retry'] ? 'RETRY' : 'DEAD'),
                    'attempts' => $attempts, 'last_attempt_at' => now(), 'updated_at' => now(),
                    'sent_at' => $result['sent'] ? now() : null,
                    'next_attempt_at' => ! $result['sent'] && $result['retry'] ? now()->addSeconds($delay) : null,
                    'last_http_status' => $result['http'], 'last_error_code' => $result['code'],
                ]);

                return true;
            });
            if (! $found) {
                break;
            }
            $processed++;
        }

        return $processed;
    }
}
