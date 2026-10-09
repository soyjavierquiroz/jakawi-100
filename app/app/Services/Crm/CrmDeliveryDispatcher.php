<?php

namespace App\Services\Crm;

use App\Models\CrmContactLink;
use App\Models\CrmDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CrmDeliveryDispatcher
{
    const BACKOFF = [60, 300, 900, 3600, 21600];

    public function run(int $limit = 100): int
    {
        if (! app(CrmConfiguration::class)->ready()) {
            return 0;
        }
        $count = 0;
        $deadline = microtime(true) + 40;
        while ($count < min(100, max(0, $limit)) && microtime(true) < $deadline) {
            $found = DB::transaction(function () {
                DB::statement("SET LOCAL lock_timeout = '2s'");
                // Same bounded-lock strategy as Meta; a crash rolls PROCESSING back.
                $d = CrmDelivery::where('provider', 'fluentcrm')->whereIn('status', ['PENDING', 'RETRY'])->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
                if (! $d) {
                    return false;
                }
                $d->update(['status' => 'PROCESSING', 'attempts' => $d->attempts + 1, 'last_attempt_at' => now()]);
                try {
                    if ($d->attempts === 1) {
                        $pending = $d->payload;
                        $uid = $pending['identity']['jakawi_user_id'] ?? null;
                        $link = $uid ? CrmContactLink::where('provider', 'fluentcrm')->where('user_id', $uid)->first() : null;
                        if ($link) {
                            $pending['identity']['provider_contact_id'] = $link->provider_contact_id;
                            $d->payload = $pending;
                            $d->save();
                        }
                    }
                    $payload = $d->payload;
                    $result = app(CrmBridgeClient::class)->send($payload);
                } catch (Throwable) {
                    $result = ['sent' => false, 'retry' => false, 'http' => null, 'code' => 'PAYLOAD_UNAVAILABLE', 'contact_id' => null, 'retry_after' => null];
                }
                if ($result['sent'] && ($uid = $payload['identity']['jakawi_user_id'] ?? null) && User::whereKey($uid)->exists()) {
                    try {
                        DB::transaction(function () use ($uid, $result, $payload) {
                            User::whereKey($uid)->lockForUpdate()->firstOrFail();
                            $link = CrmContactLink::where('provider', 'fluentcrm')->where('user_id', $uid)->lockForUpdate()->first();
                            $other = CrmContactLink::where('provider', 'fluentcrm')->where('provider_contact_id', $result['contact_id'])->where('user_id', '!=', $uid)->exists();
                            if ($other || ($link && $link->provider_contact_id !== $result['contact_id'])) {
                                throw new \DomainException('LINK_CONFLICT');
                            }
                            CrmContactLink::updateOrCreate(['provider' => 'fluentcrm', 'user_id' => $uid], ['provider_contact_id' => $result['contact_id'], 'last_known_email' => $payload['contact']['email'], 'linked_at' => $link?->linked_at ?? now(), 'last_synced_at' => now()]);
                        });
                    } catch (\DomainException) {
                        $result['sent'] = false;
                        $result['retry'] = false;
                        $result['code'] = 'CONTACT_LINK_CONFLICT';
                    } catch (Throwable) {
                        $result['sent'] = false;
                        $result['retry'] = true;
                        $result['code'] = 'CONTACT_LINK_UNAVAILABLE';
                    }
                }
                $retry = ! $result['sent'] && $result['retry'] && $d->attempts < 6;
                $delay = max(self::BACKOFF[min(4, $d->attempts - 1)], $result['retry_after'] ?? 0);
                $d->update(['status' => $result['sent'] ? 'SENT' : ($retry ? 'RETRY' : 'DEAD'), 'sent_at' => $result['sent'] ? now() : null, 'next_attempt_at' => $retry ? now()->addSeconds($delay) : null, 'last_http_status' => $result['http'], 'last_error_code' => $result['code']]);

                return true;
            });
            if (! $found) {
                break;
            } $count++;
        }

        return $count;
    }
}
