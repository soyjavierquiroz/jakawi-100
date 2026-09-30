<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\CityExpansionMetrics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminExpansionController extends Controller
{
    public function index(Request $request, CityExpansionMetrics $metrics): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(City::statuses())]]);
        $cities = $metrics->query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/expansion/index', [
            'cities' => $cities,
            'filters' => ['status' => $filters['status'] ?? null],
            'statuses' => City::statuses(),
        ]);
    }
}
