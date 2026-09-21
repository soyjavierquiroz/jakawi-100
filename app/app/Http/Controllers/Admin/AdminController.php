<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Benefit;
use App\Models\Merchant;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/index', [
            'stats' => [
                'merchants' => Merchant::count(),
                'activeMerchants' => Merchant::query()->active()->count(),
                'benefits' => Benefit::count(),
                'activeBenefits' => Benefit::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
