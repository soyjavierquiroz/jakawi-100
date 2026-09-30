<?php

namespace App\Services;

use App\Models\City;
use App\Models\CityInterest;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerApplication;
use Illuminate\Database\Eloquent\Builder;

class CityExpansionMetrics
{
    /**
     * @return Builder<City>
     */
    public function query(): Builder
    {
        return City::query()
            ->select('cities.*')
            ->selectSub(CityInterest::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id'), 'interest_total')
            ->selectSub(CityInterest::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id')->whereNotNull('user_id'), 'interest_registered')
            ->selectSub(CityInterest::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id')->whereNull('user_id'), 'interest_guests')
            ->selectSub(CityInterest::query()->selectRaw('MAX(created_at)')->whereColumn('city_id', 'cities.id'), 'interest_latest_activity')
            ->selectSub(PartnerApplication::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id'), 'application_total')
            ->selectSub(PartnerApplication::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id')->where('status', PartnerApplication::SUBMITTED), 'application_submitted')
            ->selectSub(PartnerApplication::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id')->where('status', PartnerApplication::CONTACTED), 'application_contacted')
            ->selectSub(PartnerApplication::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id')->where('status', PartnerApplication::QUALIFIED), 'application_qualified')
            ->selectSub(PartnerApplication::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id')->where('status', PartnerApplication::APPROVED), 'application_approved')
            ->selectSub(Location::query()->selectRaw('COUNT(*)')->whereColumn('city_id', 'cities.id'), 'location_total')
            ->selectSub(
                Partner::query()
                    ->selectRaw('COUNT(DISTINCT partners.id)')
                    ->join('locations', 'locations.partner_id', '=', 'partners.id')
                    ->whereColumn('locations.city_id', 'cities.id')
                    ->where('partners.status', 'published')
                    ->where('locations.status', 'published'),
                'published_partner_total'
            );
    }

    /** @return array<string, int|string|null> */
    public function values(City $city): array
    {
        return $city->only([
            'interest_total', 'interest_registered', 'interest_guests', 'interest_latest_activity',
            'application_total', 'application_submitted', 'application_contacted', 'application_qualified', 'application_approved',
            'location_total', 'published_partner_total',
        ]);
    }
}
