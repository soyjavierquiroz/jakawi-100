<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redemption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RedemptionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        return Inertia::render('admin/redemptions/index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'redemptions' => Redemption::query()
                ->with('user')
                ->when($status, fn (Builder $query) => $query->where('status', $status))
                ->when($search, function (Builder $query) use ($search) {
                    $query->where(function (Builder $query) use ($search) {
                        $query
                            ->where('merchant_name', 'like', "%{$search}%")
                            ->orWhere('benefit_title', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhereHas('user', function (Builder $query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest()
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Redemption $redemption) => [
                    'public_id' => $redemption->public_id,
                    'created_at' => $redemption->created_at?->toDateTimeString(),
                    'member' => [
                        'name' => $redemption->user?->name,
                        'email' => $redemption->user?->email,
                    ],
                    'merchant_name' => $redemption->merchant_name,
                    'benefit_title' => $redemption->benefit_title,
                    'status' => $redemption->status,
                    'savings_amount' => $redemption->savings_amount,
                    'confirmed_at' => $redemption->confirmed_at?->toDateTimeString(),
                ]),
        ]);
    }
}
