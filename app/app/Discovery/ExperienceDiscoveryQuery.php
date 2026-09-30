<?php

namespace App\Discovery;

use App\Models\City;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Builder;

class ExperienceDiscoveryQuery
{
    /**
     * The loaded sessions relation contains only upcoming sessions at locations in $city.
     *
     * @return Builder<Experience>
     */
    public function forCity(City $city): Builder
    {
        $citySessions = fn ($sessions) => $sessions->upcoming()
            ->whereHas('location', fn ($location) => $location->where('city_id', $city->id));

        return Experience::upcoming()
            ->whereHas('sessions', $citySessions)
            ->with(['sessions' => fn ($sessions) => $citySessions($sessions)->with('location')]);
    }
}
