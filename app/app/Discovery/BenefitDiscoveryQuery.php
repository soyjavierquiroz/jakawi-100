<?php

namespace App\Discovery;

use App\Models\Benefit;
use App\Models\City;
use Illuminate\Database\Eloquent\Builder;

class BenefitDiscoveryQuery
{
    /** @return Builder<Benefit> */
    public function forCity(City $city): Builder
    {
        return Benefit::available()
            ->where(function (Builder $query) use ($city): void {
                $query->where(function (Builder $allLocations) use ($city): void {
                    $allLocations->where('applies_to_all_locations', true)
                        ->whereHas('partner.locations', fn (Builder $locations) => $locations->published()->where('city_id', $city->id));
                })->orWhere(function (Builder $selectedLocations) use ($city): void {
                    $selectedLocations->where('applies_to_all_locations', false)
                        ->whereHas('locations', fn (Builder $locations) => $locations->published()->where('city_id', $city->id));
                });
            })
            // DiscoveryService deliberately selects a city location from these
            // bounded relations; this prevents per-card partner/location reads.
            ->with(['partner.locations' => fn ($locations) => $locations->published()->where('city_id', $city->id), 'locations' => fn ($locations) => $locations->published()->where('city_id', $city->id)]);
    }
}
