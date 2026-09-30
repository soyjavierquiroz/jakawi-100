<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\SelectedCity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SelectedCityController extends Controller
{
    public function store(Request $request, City $city): RedirectResponse
    {
        $fallback = route('home');
        $returnTo = (string) $request->input('return_to', $fallback);
        $target = str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//') ? $returnTo : $fallback;
        $response = redirect()->to($target);

        if ($city->status === City::ACTIVE) {
            $response->withCookie(cookie(SelectedCity::COOKIE, $city->slug, 60 * 24 * 365, '/', null, null, true, false, 'lax'));
        }

        return $response;
    }
}
