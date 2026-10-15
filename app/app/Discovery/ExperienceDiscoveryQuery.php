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

        return Experience::catalogVisible()
            ->where(function (Builder $query) use ($citySessions, $city) {
                $query->where(function (Builder $proposal) use ($city) {
                    $proposal->where('catalog_visibility', 'upcoming')
                        ->whereHas('partners', fn ($partner) => $partner->published()->whereHas('locations', fn ($location) => $location->published()->where('city_id', $city->id)));
                })->orWhere(function (Builder $operational) use ($citySessions) {
                    $operational->where('catalog_visibility', '!=', 'upcoming')->whereHas('sessions', $citySessions);
                });
            })
            ->with(['landingPresentations:id,subject_type,subject_id,slug,status,default_scope', 'sessions' => fn ($sessions) => $citySessions($sessions)->with('location'), 'partners']);
    }
}
