<?php

namespace App\Support;

use App\Exceptions\PrelaunchOperationBlocked;
use Illuminate\Database\Eloquent\Model;

/** One runtime policy shared by HTTP, services, jobs and model writes. */
final class PrelaunchOperations
{
    public const ACCOUNT_EVENTS = ['member.registered', 'membership.identity_linked', 'membership.attribution_fixed', 'membership.attribution_identity_linked'];

    public static function blocked(): bool
    {
        return (bool) config('jakawi.prelaunch_mode');
    }

    public static function assertAllowed(): void
    {
        if (self::blocked()) {
            throw new PrelaunchOperationBlocked;
        }
    }

    public static function assertEventAllowed(string $body): void
    {
        if (self::blocked() && ! in_array(json_decode($body, true)['event_type'] ?? null, self::ACCOUNT_EVENTS, true)) {
            self::assertAllowed();
        }
    }

    public static function protectModel(Model $model): void
    {
        self::assertAllowed();
    }
}
