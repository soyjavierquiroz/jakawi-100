<?php

namespace App\Http\Controllers;

use App\Services\PublicOfferCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    public function __invoke(Request $request, PublicOfferCatalog $catalog): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($catalog->page($input['per_page'] ?? 50, $input['page'] ?? 1))
            ->header('Cache-Control', 'no-store');
    }
}
