<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\ChallengeParticipation;
use App\Models\Conversion;
use App\Models\Membership;
use App\Models\MembershipBankPayment;
use App\Services\AnalyticsTracker;
use App\Services\CityCommunity;
use App\Services\JpBalanceService;
use App\Services\MembershipStatus;
use App\Services\ReferralCodeService;
use App\Support\ListingPage;
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

        $membership = app(MembershipStatus::class)->membershipFor($request->user());
        $confirmedQuery = $membership?->confirmedRedemptions();
        $confirmedCount = $confirmedQuery ? (clone $confirmedQuery)->count() : 0;
        $confirmed = $confirmedQuery?->latest('confirmed_at')->orderByDesc('id')->limit(8)->get() ?? collect();
        $checkInQuery = $request->user()->experienceReservations()->whereNotNull('checked_in_at');
        $checkInCount = (clone $checkInQuery)->count();
        $checkIns = $checkInQuery
            ->with(['experience', 'partner'])
            ->whereNotNull('checked_in_at')
            ->latest('checked_in_at')->orderByDesc('id')->limit(8)
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

        $challengePage = ListingPage::make(ChallengeParticipation::where('user_id', $user->id)->with(['challenge:id,slug,title,qualification_type,qualification_metric,qualification_target,reward_type,reward_jp_amount,manual_prize_description,selection_type', 'socialEntries:id,participation_id,likes,views,comments,validation_status', 'grant:id,participation_id,status,reward_type,jp_amount,benefit_id', 'grant.benefit:id,title'])->latest(), 50);

        return Inertia::render('mi-jakawi', [
            'community' => app(CityCommunity::class)->data($request, 'mi-jakawi'),
            'challengePagination' => array_diff_key($challengePage->toArray(), ['data' => true]),
            'challenges' => $challengePage->getCollection()->map(function ($p) {
                $c = $p->challenge;
                $metric = $c->qualification_metric;

                return ['title' => $c->title, 'slug' => $c->slug, 'qualification_status' => $p->qualification_status, 'selection_status' => $p->selection_status,
                    'progress' => $metric ? $p->socialEntries->filter(fn ($e) => $e->validation_status === 'valid')->max($metric) ?? 0 : null,
                    'target' => $c->qualification_target, 'metric' => $metric,
                    'reward' => $p->grant ? ($p->grant->reward_type === 'JP' ? '+'.$p->grant->jp_amount.' JP' : $p->grant->benefit?->title ?? $c->manual_prize_description) : null,
                    'grant_status' => $p->grant?->status];
            }),
            'pendingReceipts' => MembershipBankPayment::where('user_id', $user->id)->receiptPending()->orderBy('id')->get()->map(fn ($payment) => [
                'reference' => $payment->reference,
                'upload_url' => $payment->origin === 'CLUB' ? 'https://jakawi.club/activar' : '/membresia/checkout#adjuntar-comprobante',
            ]),
            'membershipStatus' => app(MembershipStatus::class)->for($user),
            'membership' => $membership ? $this->serializeMembership($membership) : null,
            'membershipConfig' => [
                'price_bob' => config('jakawi.membership.price_bob'),
                'duration_days' => config('jakawi.membership.duration_days'),
            ],
            'valueStats' => [
                'benefits_used' => $confirmedCount,
                'experiences_lived' => $checkInCount,
            ],
            'activity' => $activity,
            'memberReferral' => [
                'eligible' => $isActiveMember,
                'code' => $isActiveMember ? ($request->attributes->has('support_session') ? $user->referral_code : app(ReferralCodeService::class)->ensureFor($user)) : null,
                'link' => $isActiveMember && (! $request->attributes->has('support_session') || $user->referral_code) ? route('referrals.open', $request->attributes->has('support_session') ? $user->referral_code : app(ReferralCodeService::class)->ensureFor($user)) : null,
                // "Amigos que se unieron" means referred users with a confirmed membership purchase.
                'joined_count' => Conversion::query()->where('type', 'membership_purchased')->where('status', 'confirmed')->whereHas('relationship', fn ($query) => $query->where('referrer_user_id', $user->id))->count(),
                'jp_ledger' => $jpBalance['ledger_balance'],
                'jp_balance' => $jpBalance['available_balance'],
                'jp_held' => $jpBalance['held'],
            ],
            'featuredBenefits' => Benefit::query()->available()->publicAccess()->with('partner:id,name')->orderByDesc('featured')->orderBy('sort_order')->limit(3)->get()
                ->map(function (Benefit $benefit) use ($request) {
                    // Reuse detail availability (locations, PIN and per-account limits).
                    $props = app(PublicController::class)->benefitPublicProps($benefit, $request);
                    return [
                        'slug' => $benefit->slug,
                        'title' => $benefit->title,
                        'partner_name' => $benefit->partner->name,
                        'image_url' => $props['benefit']['image_url'],
                        'availability' => $props['availability'],
                        'access_label' => $benefit->requiresMembership()
                            ? ($props['hasActiveMembership'] ? 'Con tu membresía · Revisa las condiciones' : 'Requiere membresía activa')
                            : 'Con tu cuenta gratuita · Revisa las condiciones',
                    ];
                }),
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
            'ends_at' => $membership->effectiveEndsAt()?->toISOString(),
            'days_remaining' => ($end = $membership->effectiveEndsAt()) ? max(0, now()->diffInDays($end, false)) : null,
            'amount_paid' => $membership->amount_paid,
            'confirmed_savings' => $membership->confirmedSavings(),
            'remaining_to_payback' => $membership->remainingToPayback(),
            'has_paid_for_itself' => $membership->hasPaidForItself(),
        ];
    }
}
