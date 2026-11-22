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

    public static function digitalAllowed(Model $subject): bool
    {
        if (! self::blocked()) return true;
        $product = match (true) {
            $subject instanceof \App\Models\DigitalProduct => $subject,
            $subject instanceof \App\Models\DigitalOrder => $subject->product,
            $subject instanceof \App\Models\DigitalPayment, $subject instanceof \App\Models\DigitalAccessGrant => $subject->order?->product,
            default => null,
        };
        return $product && app(\App\Services\DigitalSaleAvailability::class)->exceptionAllowed($product);
    }

    public static function assertDigitalAllowed(Model $subject): void
    {
        if (! self::digitalAllowed($subject)) self::assertAllowed();
    }

    public static function assertEventAllowed(string $body): void
    {
        if (self::blocked() && ! in_array(json_decode($body, true)['event_type'] ?? null, self::ACCOUNT_EVENTS, true)) {
            self::assertAllowed();
        }
    }

    public static function protectModel(Model $model): void
    {
        if ($model instanceof \App\Models\DigitalOrder || $model instanceof \App\Models\DigitalPayment || $model instanceof \App\Models\DigitalAccessGrant) {
            self::assertDigitalAllowed($model);
            return;
        }
        self::assertAllowed();
    }
}
