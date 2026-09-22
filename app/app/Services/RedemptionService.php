<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Redemption;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedemptionService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly AnalyticsTracker $analytics) {}

    public function start(User $user, Benefit $benefit, Location $location): Redemption
    {
        // The user lock serializes both pending idempotency and per-member limits.
        return DB::transaction(function () use ($user, $benefit, $location): Redemption {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $membership = Membership::query()->active()->lockForUpdate()->where('user_id', $lockedUser->id)->first();
            if ($membership === null) {
                throw new DomainException('An active membership is required.');
            }

            $benefit = Benefit::query()->with('partner')->find($benefit->getKey());
            $location = Location::query()->find($location->getKey());
            $this->assertRedeemable($benefit, $location);
            $this->assertLimitAvailable($lockedUser, $benefit);

            $now = now();
            $pending = Redemption::query()->where([
                'user_id' => $lockedUser->id,
                'benefit_id' => $benefit->id,
                'location_id' => $location->id,
                'status' => Redemption::STATUS_PENDING,
            ])->lockForUpdate()->get();

            foreach ($pending as $redemption) {
                if ($redemption->expires_at->gt($now)) {
                    return $redemption;
                }
                $redemption->update(['status' => Redemption::STATUS_EXPIRED]);
            }

            $redemption = $this->createRedemption($lockedUser, $membership, $benefit, $location, $now);
            $this->analytics->redemptionStarted($redemption);

            return $redemption;
        });
    }

    public function confirm(string $code, string $pin): Redemption
    {
        $result = DB::transaction(function () use ($code, $pin): ?Redemption {
            $redemption = Redemption::query()->where('code', $code)->lockForUpdate()->first();
            if ($redemption === null) {
                throw new DomainException('Redemption code not found.');
            }
            if ($redemption->isConfirmed()) {
                return $redemption;
            }
            if (! $redemption->isPending()) {
                throw new DomainException('This redemption cannot be confirmed.');
            }
            if ($redemption->expires_at->lte(now())) {
                $redemption->update(['status' => Redemption::STATUS_EXPIRED]);

                return null;
            }

            $user = User::query()->lockForUpdate()->findOrFail($redemption->user_id);
            $membership = Membership::query()->lockForUpdate()->find($redemption->membership_id);
            if ($membership === null || $membership->user_id !== $user->id || ! $membership->isActive()) {
                throw new DomainException('The membership is no longer active.');
            }

            $benefit = Benefit::query()->with('partner')->find($redemption->benefit_id);
            $location = Location::query()->find($redemption->location_id);
            $this->assertRedeemable($benefit, $location);
            if (! $location->checkRedemptionPin($pin)) {
                throw new DomainException('The location redemption PIN is invalid.');
            }
            $this->assertLimitAvailable($user, $benefit);

            $redemption->update(['status' => Redemption::STATUS_CONFIRMED, 'confirmed_at' => now()]);
            $redemption = $redemption->refresh();
            $this->analytics->redemptionConfirmed($redemption);

            return $redemption;
        });

        if ($result === null) {
            throw new DomainException('This redemption code has expired.');
        }

        return $result;
    }

    private function assertRedeemable(?Benefit $benefit, ?Location $location): void
    {
        if ($benefit === null || $location === null || ! $benefit->isAvailableAt($location)) {
            throw new DomainException('This benefit is not available at this location.');
        }
        if (! $location->hasRedemptionPin()) {
            throw new DomainException('This location does not have a redemption PIN.');
        }
    }

    private function assertLimitAvailable(User $user, Benefit $benefit): void
    {
        if ($benefit->redemption_limit_per_member === null) {
            return;
        }

        // PostgreSQL forbids FOR UPDATE with COUNT(). Lock rows, then count the collection.
        $confirmed = Redemption::query()->where([
            'user_id' => $user->id,
            'benefit_id' => $benefit->id,
            'status' => Redemption::STATUS_CONFIRMED,
        ])->lockForUpdate()->get()->count();

        if ($confirmed >= $benefit->redemption_limit_per_member) {
            throw new DomainException('The redemption limit for this benefit has been reached.');
        }
    }

    private function createRedemption(User $user, Membership $membership, Benefit $benefit, Location $location, CarbonInterface $now): Redemption
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = $this->generateCode();
            if (Redemption::query()->where('code', $code)->exists()) {
                continue;
            }

            return Redemption::create([
                'public_id' => (string) Str::ulid(), 'code' => $code,
                'user_id' => $user->id, 'membership_id' => $membership->id,
                'partner_id' => $benefit->partner_id, 'location_id' => $location->id, 'benefit_id' => $benefit->id,
                'partner_name' => $benefit->partner->name, 'location_name' => $location->name,
                'benefit_title' => $benefit->title, 'savings_amount' => $benefit->estimated_savings,
                'status' => Redemption::STATUS_PENDING,
                'expires_at' => $now->copy()->addMinutes((int) config('jakawi.redemption.code_ttl_minutes')),
            ]);
        }

        throw new DomainException('Could not generate a unique redemption code.');
    }

    private function generateCode(): string
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }
}
