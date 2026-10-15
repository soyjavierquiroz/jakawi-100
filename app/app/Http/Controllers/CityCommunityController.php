<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\CityCommunity;
use App\Services\SelectedCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CityCommunityController extends Controller
{
    public function store(Request $request, City $city, CityCommunity $communities, SelectedCity $selectedCity): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['presented', 'opened', 'snoozed', 'declined', 'self_reported_joined', 'reset'])],
            'source' => ['required', Rule::in(['onboarding', 'home', 'mi-jakawi'])],
        ]);
        abort_unless($selectedCity->resolve($request)->id === $city->id, 409);
        if ($data['source'] === 'onboarding') {
            abort_unless($request->session()->get('community_onboarding') === $request->user()->id, 422);
        }
        abort_if($data['action'] === 'presented' && $data['source'] === 'mi-jakawi', 422);

        return response()->json($communities->interact($request, $city, $data['action'], $data['source']));
    }
}
