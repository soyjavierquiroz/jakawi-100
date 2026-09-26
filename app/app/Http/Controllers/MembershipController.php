<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->isPartnerOnly()) {
            return to_route('partner.index');
        }

        $membership = $request->user()->activeMembership()->first()
            ?? $request->user()->memberships()
                ->where('status', Membership::STATUS_ACTIVE)
                ->where('ends_at', '<', now())
                ->latest('ends_at')
                ->first();
        $confirmed = $membership?->confirmedRedemptions()->latest('confirmed_at')->get() ?? collect();
        $checkIns = $request->user()->experienceReservations()
            ->with(['experience', 'partner'])
            ->whereNotNull('checked_in_at')
            ->latest('checked_in_at')
            ->get();

        $activity = $confirmed->map(fn ($redemption) => [
            'id' => 'redemption-'.$redemption->public_id,
            'type' => 'redemption',
            'title' => $redemption->partner_name,
            'detail' => $redemption->benefit_title,
            'savings_amount' => $redemption->savings_amount,
            'happened_at' => $redemption->confirmed_at,
        ])->concat($checkIns->map(fn ($reservation) => [
            'id' => 'experience-'.$reservation->public_id,
            'type' => 'experience',
            'title' => $reservation->experience->title,
            'detail' => $reservation->partner?->name,
            'happened_at' => $reservation->checked_in_at,
        ]))->sortByDesc('happened_at')->take(8)->values();

        return Inertia::render('mi-jakawi', [
            'membership' => $membership ? $this->serializeMembership($membership) : null,
            'membershipConfig' => [
                'price_bob' => config('jakawi.membership.price_bob'),
                'duration_days' => config('jakawi.membership.duration_days'),
            ],
            'valueStats' => [
                'benefits_used' => $confirmed->count(),
                'experiences_lived' => $checkIns->count(),
            ],
            'activity' => $activity,
            'featuredBenefits' => $membership ? [] : Benefit::query()->available()->with('partner:id,name')->orderByDesc('featured')->orderBy('sort_order')->limit(3)->get()
                ->map(fn (Benefit $benefit) => ['slug' => $benefit->slug, 'title' => $benefit->title, 'partner_name' => $benefit->partner->name]),
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeMembership(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'status' => $membership->status,
            'is_active' => $membership->isActive(),
            'starts_at' => $membership->starts_at?->toDateTimeString(),
            'ends_at' => $membership->ends_at?->toDateTimeString(),
            'days_remaining' => max(0, now()->startOfDay()->diffInDays($membership->ends_at->copy()->startOfDay(), false)),
            'amount_paid' => $membership->amount_paid,
            'confirmed_savings' => $membership->confirmedSavings(),
            'remaining_to_payback' => $membership->remainingToPayback(),
            'has_paid_for_itself' => $membership->hasPaidForItself(),
        ];
    }

}
