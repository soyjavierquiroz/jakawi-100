<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\Partner;
use App\Services\PartnerKpiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerPortalController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $partners = $request->user()->partners()
            ->orderBy('name')
            ->get(['partners.id', 'partners.name', 'partners.slug']);

        if ($partners->count() === 1) {
            return to_route('partner.portal.show', $partners->first());
        }

        return Inertia::render('partner/index', [
            'partners' => $partners->map(fn ($partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'role' => $partner->pivot->role,
            ]),
        ]);
    }

    public function show(Partner $partner): Response
    {
        return Inertia::render('partner/dashboard', [
            'partner' => $partner->only(['name', 'slug']),
            'pendingReservations' => ExperienceReservation::where('partner_id', $partner->id)->where('status', 'pending')->count(),
            'benefitDrafts' => Benefit::where('partner_id', $partner->id)->where('review_status', 'draft')->count(),
            'benefitSubmitted' => Benefit::where('partner_id', $partner->id)->where('review_status', 'submitted')->count(),
            'experienceDrafts' => Experience::whereHas('partners', fn ($q) => $q->whereKey($partner->id))->where('review_status', 'draft')->count(),
            'experienceSubmitted' => Experience::whereHas('partners', fn ($q) => $q->whereKey($partner->id))->where('review_status', 'submitted')->count(),
        ]);
    }

    public function performance(Request $request, Partner $partner, PartnerKpiService $kpis): Response
    {
        $relation = $request->user()->partners()->whereKey($partner->id)->first();
        abort_unless($relation && in_array($relation->pivot->role, ['owner', 'manager'], true), 403);
        $days = (int) $request->integer('period', 30);
        abort_unless(in_array($days, [7, 30, 90], true), 404);

        return Inertia::render('partner/performance', [
            'partner' => $partner->only(['name', 'slug']),
            'kpis' => $kpis->forPartner($partner, $days),
        ]);
    }

    public function redirectToSinglePartner(Request $request, string $destination): RedirectResponse
    {
        $partners = $request->user()->partners()->orderBy('name')->get(['partners.id', 'partners.slug']);
        abort_unless($partners->count() === 1, 403);

        return to_route($destination, $partners->first());
    }
}
