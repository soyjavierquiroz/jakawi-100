<?php

namespace App\Discovery;

use App\Models\City;
use App\Models\Unlock;
use Illuminate\Database\Eloquent\Builder;

class UnlockDiscoveryQuery
{
    /** @return Builder<Unlock> */
    public function forCity(City $city): Builder
    {
        return Unlock::query()
            ->whereIn('status', [Unlock::ACTIVE, Unlock::GOAL_REACHED])
            ->whereHas('locations', fn (Builder $locations) => $locations->where('city_id', $city->id))
            ->with(['partner', 'locations' => fn ($locations) => $locations->where('city_id', $city->id)]);
    }
}
