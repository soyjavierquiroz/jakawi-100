<?php

namespace App\Http\Controllers;

use App\Models\LandingPresentation;
use App\Services\GrowthMeasurementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GrowthLandingCtaController extends Controller
{
    public function store(Request $request, LandingPresentation $presentation, GrowthMeasurementService $growth)
    {
        abort_unless($presentation->status === 'PUBLISHED', 404);
        $data = $request->validate([
            'cta_kind' => ['required', Rule::in(['signup', 'membership', 'product_detail', 'redeem', 'reserve', 'participate', 'commit', 'external', 'other'])],
            'cta_location' => ['required', Rule::in(['hero', 'body', 'final', 'other'])],
            'destination' => ['required', 'string', 'max:2048'],
        ]);
        $growth->cta($presentation, $data['cta_kind'], $data['cta_location'], $data['destination']);
        return response()->noContent();
    }
}
