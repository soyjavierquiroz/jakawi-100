<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Membership;
use App\Models\Conversion;
use App\Services\AnalyticsTracker;
use App\Services\ReferralCodeService;
use App\Services\JpBalanceService;
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

        $user = $request->user();
        $isActiveMember = $user->hasActiveMembership();
        $jpBalance = app(JpBalanceService::class)->for($user);

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
            'memberReferral' => [
                'eligible' => $isActiveMember,
                'code' => $isActiveMember ? app(ReferralCodeService::class)->ensureFor($user) : null,
                'link' => $isActiveMember ? route('referrals.open', app(ReferralCodeService::class)->ensureFor($user)) : null,
                // "Amigos que se unieron" means referred users with a confirmed membership purchase.
                'joined_count' => Conversion::query()->where('type', 'membership_purchased')->where('status', 'confirmed')->whereHas('relationship', fn ($query) => $query->where('referrer_user_id', $user->id))->count(),
                'jp_ledger' => $jpBalance['ledger_balance'],
                'jp_balance' => $jpBalance['available_balance'],
                'jp_held' => $jpBalance['held'],
            ],
            'featuredBenefits' => $membership ? [] : Benefit::query()->available()->with('partner:id,name')->orderByDesc('featured')->orderBy('sort_order')->limit(3)->get()
                ->map(fn (Benefit $benefit) => ['slug' => $benefit->slug, 'title' => $benefit->title, 'partner_name' => $benefit->partner->name]),
        ]);
    }

    public function shared(Request $request, AnalyticsTracker $analytics)
    {
        abort_unless($request->user()->hasActiveMembership(), 403);
        $analytics->record('referral_shared', ['user_id' => $request->user()->id]);

        return response()->noContent();
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
