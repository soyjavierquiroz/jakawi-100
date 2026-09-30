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

        return $city ?? City::query()
            ->where('slug', 'cochabamba')
            ->where('status', City::ACTIVE)
            ->firstOrFail();
    }

    /** @return array{id:int,name:string,slug:string,status:string} */
    public function data(City $city): array
    {
        return $city->only(['id', 'name', 'slug', 'status']);
    }

    /** @return list<array{id:int,name:string,slug:string,status:string}> */
    public function available(): array
    {
        return City::query()->whereIn('status', [City::ACTIVE, City::UNLOCKING, City::COMING_SOON])
            ->orderByDesc('priority')->orderBy('name')
            ->get(['id', 'name', 'slug', 'status'])->map(fn (City $city) => $this->data($city))->all();
    }
}
