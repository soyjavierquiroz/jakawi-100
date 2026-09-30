<?php

namespace App\Services;

use App\Models\AttributionTouch;
use App\Models\City;
use App\Models\CityInterest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CityInterestService
{
    /** @return array{0: CityInterest, 1: bool} */
    public function record(City $city, Request $request): array
    {
        $user = $request->user();
        $visitorId = app(AttributionService::class)->anonymousId($request);
        $existing = $this->existing($city, $user, $visitorId);

        if ($existing) {
            return [$existing, false];
        }

        $touch = $this->touch($user, $visitorId);

        try {
            $interest = CityInterest::create([
                'city_id' => $city->id,
                'user_id' => $user?->id,
                'visitor_id' => $user ? null : $visitorId,
                'attribution_touch_id' => $touch?->id,
                'attribution_snapshot' => $touch ? $this->snapshot($touch) : null,
            ]);
        } catch (QueryException $exception) {
            $interest = $this->existing($city, $user, $visitorId);

            if (! $interest) {
                throw $exception;
            }
        }

        return [$interest, $interest->wasRecentlyCreated];
    }

    private function existing(City $city, ?User $user, ?string $visitorId): ?CityInterest
    {
        return CityInterest::query()->where('city_id', $city->id)
            ->when($user, fn ($query) => $query->where('user_id', $user->id), fn ($query) => $query->where('visitor_id', $visitorId))
            ->first();
    }

    private function touch(?User $user, ?string $visitorId): ?AttributionTouch
    {
        return AttributionTouch::query()
            ->when($user, fn ($query) => $query->where('user_id', $user->id), fn ($query) => $query->whereNull('user_id')->where('anonymous_id', $visitorId))
            ->latest('occurred_at')
            ->first();
    }

    /** @return array<string, int|string|null> */
    private function snapshot(AttributionTouch $touch): array
    {
        return $touch->only([
            'id', 'anonymous_id', 'user_id', 'referral_code', 'referrer_user_id', 'acquisition_partner_id',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'landing_page', 'occurred_at',
        ]);
    }
}
