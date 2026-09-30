<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Http\Request;

class SelectedCity
{
    public const COOKIE = 'selected_city';

    public function resolve(Request $request): City
    {
        $slug = $request->cookie(self::COOKIE);

        $city = is_string($slug)
            ? City::query()->where('slug', $slug)->where('status', City::ACTIVE)->first()
            : null;

        if ($city) {
            return $city;
        }

        // Cochabamba is the preferred national fallback while it remains active.
        // If its status was changed manually, never revive a stale cookie: use
        // another active city or make the temporary unavailability explicit.
        $fallback = City::query()
            ->where('status', City::ACTIVE)
            ->orderByRaw("CASE WHEN slug = 'cochabamba' THEN 0 ELSE 1 END")
            ->orderByDesc('priority')
            ->orderBy('name')
            ->first();

        abort_unless($fallback, 503, 'JAKAWI no está disponible temporalmente.');

        return $fallback;
    }

    /** @return array{id:int,name:string,slug:string,status:string} */
    public function data(City $city): array
    {
        return $city->only(['id', 'name', 'slug', 'status']);
    }

    /** @return list<array{id:int,name:string,slug:string,status:string}> */
    public function available(): array
    {
        return City::query()->whereIn('status', [City::ACTIVE, City::UNLOCKING, City::COMING_SOON, City::PREPARING, City::PAUSED])
            ->orderByDesc('priority')->orderBy('name')
            ->get(['id', 'name', 'slug', 'status'])->map(fn (City $city) => $this->data($city))->all();
    }
}
