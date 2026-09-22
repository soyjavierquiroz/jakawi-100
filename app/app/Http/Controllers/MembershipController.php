<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Redemption;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function show(Request $request): Response
    {
        $membership = $request->user()->activeMembership()->first();
        $confirmedRedemptions = Redemption::query()
            ->where('user_id', $request->user()->id)
            ->confirmed()
            ->latest('confirmed_at');

        return Inertia::render('mi-jakawi', [
            'membership' => $membership ? $this->serializeMembership($membership) : null,
            'redemptionStats' => [
                'count' => (clone $confirmedRedemptions)->count(),
                'savings_total' => (string) (clone $confirmedRedemptions)->whereNotNull('savings_amount')->sum('savings_amount'),
            ],
            'recentRedemptions' => (clone $confirmedRedemptions)
                ->limit(5)
                ->get()
                ->map(fn (Redemption $redemption) => [
                    'public_id' => $redemption->public_id,
                    'merchant_name' => $redemption->merchant_name,
                    'benefit_title' => $redemption->benefit_title,
                    'savings_amount' => $redemption->savings_amount,
                    'confirmed_at' => $redemption->confirmed_at?->toDateTimeString(),
                ]),
            'membershipConfig' => [
                'price_bob' => config('jakawi.membership.price_bob'),
                'duration_days' => config('jakawi.membership.duration_days'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeMembership(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'status' => $membership->status,
            'starts_at' => $membership->starts_at?->toDateTimeString(),
            'ends_at' => $membership->ends_at?->toDateTimeString(),
            'days_remaining' => max(0, now()->startOfDay()->diffInDays($membership->ends_at->copy()->startOfDay(), false)),
            'amount_paid' => $membership->amount_paid,
        ];
    }
}
