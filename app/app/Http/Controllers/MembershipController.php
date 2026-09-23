<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\ExperienceReservation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function show(Request $request): Response
    {
        $membership = $request->user()->activeMembership()->first();
        $confirmed = $membership?->confirmedRedemptions()->latest('confirmed_at')->get() ?? collect();
        $reservations = $request->user()->experienceReservations()->with(['experience', 'session.location', 'partner'])->get()->sortBy(fn ($r) => [$r->session->starts_at->isPast(), $r->session->starts_at]);

        return Inertia::render('mi-jakawi', [
            'membership' => $membership ? $this->serializeMembership($membership) : null,
            'membershipConfig' => [
                'price_bob' => config('jakawi.membership.price_bob'),
                'duration_days' => config('jakawi.membership.duration_days'),
            ],
            'redemptionStats' => ['count' => $confirmed->count(), 'savings_total' => $membership?->confirmedSavings() ?? '0.00'],
            'recentRedemptions' => $confirmed->take(8)->map(fn ($redemption) => ['public_id' => $redemption->public_id, 'partner_name' => $redemption->partner_name, 'benefit_title' => $redemption->benefit_title, 'savings_amount' => $redemption->savings_amount, 'confirmed_at' => $redemption->confirmed_at]),
            'reservations' => $reservations->map(fn ($r) => ['public_id' => $r->public_id, 'status' => $r->status, 'experience' => $r->experience->title, 'starts_at' => $r->session->starts_at, 'venue' => $r->session->location?->name ?? $r->session->venue_label, 'partner' => $r->partner->name, 'can_cancel' => in_array($r->status, ['pending', 'confirmed'], true) && $r->session->starts_at->isFuture()]),
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
            'confirmed_savings' => $membership->confirmedSavings(),
            'remaining_to_payback' => $membership->remainingToPayback(),
            'has_paid_for_itself' => $membership->hasPaidForItself(),
        ];
    }
}
