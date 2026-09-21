<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'featuredBenefits' => Benefit::query()
                ->with('merchant')
                ->available()
                ->where('is_featured', true)
                ->orderBy('sort_order')
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn (Benefit $benefit) => PublicBenefitController::serializeBenefit($benefit)),
        ]);
    }
}
