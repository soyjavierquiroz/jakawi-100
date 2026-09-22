<?php

namespace App\Http\Controllers;

use App\Models\Redemption;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $confirmedRedemptions = Redemption::query()
            ->where('user_id', $request->user()->id)
            ->confirmed();

        return Inertia::render('dashboard', [
            'membership' => $request->user()->activeMembership()->first(['id', 'ends_at']),
            'redemptionStats' => [
                'count' => (clone $confirmedRedemptions)->count(),
                'savings_total' => (string) (clone $confirmedRedemptions)
                    ->whereNotNull('savings_amount')
                    ->sum('savings_amount'),
            ],
        ]);
    }
}
