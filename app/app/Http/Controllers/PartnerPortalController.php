<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\Partner;
use App\Services\CityCommunity;
use App\Services\PartnerKpiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerPortalController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $partners = app(\App\Services\Support\SupportAccess::class)->partnerUser($request)->partners()
            ->orderBy('name')
            ->get(['partners.id', 'partners.name', 'partners.slug']);

        if ($partners->count() === 1) {
            return to_route('partner.portal.show', $partners->first());
        }

        return Inertia::render('partner/index', [
            'community' => app(CityCommunity::class)->data($request, 'mi-jakawi'),
            'partners' => $partners->map(fn ($partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'role' => $partner->pivot->role,
            ]),
        ]);
    }

    public function show(Request $request, Partner $partner, PartnerKpiService $kpis): Response
    {
        $relation = app(\App\Services\Support\SupportAccess::class)->partnerUser($request)->partners()->whereKey($partner->id)->firstOrFail();

        return Inertia::render('partner/dashboard', [
            'community' => app(CityCommunity::class)->data($request, 'mi-jakawi'),
            'partner' => $partner->only(['name', 'slug', 'status']) + ['public_url' => $partner->isPublished() ? route('partners.public', $partner) : null, 'can_manage' => in_array($relation->pivot->role, ['owner', 'manager'], true)],
            'activity' => in_array($relation->pivot->role, ['owner', 'manager'], true) ? $this->activity($kpis, $partner) : null,
            'benefitPublished' => Benefit::where('partner_id', $partner->id)->available()->count(),
            'pendingReservations' => ExperienceReservation::where('partner_id', $partner->id)->where('status', 'pending')->count(),
            'benefitDrafts' => Benefit::where('partner_id', $partner->id)->where('review_status', 'draft')->count(),
            'benefitSubmitted' => Benefit::where('partner_id', $partner->id)->where('review_status', 'submitted')->count(),
            'experienceDrafts' => Experience::whereHas('partners', fn ($q) => $q->whereKey($partner->id))->where('review_status', 'draft')->count(),
            'experienceSubmitted' => Experience::whereHas('partners', fn ($q) => $q->whereKey($partner->id))->where('review_status', 'submitted')->count(),
        ]);
    }

    public function performance(Request $request, Partner $partner, PartnerKpiService $kpis): Response
    {
        $relation = app(\App\Services\Support\SupportAccess::class)->partnerUser($request)->partners()->whereKey($partner->id)->first();
        abort_unless($relation && in_array($relation->pivot->role, ['owner', 'manager'], true), 403);
        $days = (int) $request->integer('period', 30);
        abort_unless(in_array($days, [7, 30, 90], true), 404);

        return Inertia::render('partner/performance', [
            'partner' => $partner->only(['name', 'slug']),
            'kpis' => $this->performanceKpis($request, $kpis, $partner, $days),
            'operationalOnly' => $request->attributes->get('support_session')?->mode === 'manage',
        ]);
    }

    private function activity(PartnerKpiService $service, Partner $partner): array
    {
        $kpis = $service->forPartner($partner, 30);

        return ['partner_views' => $kpis['discovery']['partner_views'], 'benefit_views' => $kpis['discovery']['benefit_views'], 'confirmed' => $kpis['benefits']['confirmed']];
    }

    private function performanceKpis(Request $request, PartnerKpiService $service, Partner $partner, int $days): array
    {
        $kpis = $service->forPartner($partner, $days);
        if ($request->attributes->get('support_session')?->mode === 'manage') {
            unset($kpis['benefits']['savings'], $kpis['benefits']['average_savings']);
            $kpis['topBenefits'] = $kpis['topBenefits']->map(fn ($row) => collect($row)->except('savings')->all());
        }

        return $kpis;
    }

    public function redirectToSinglePartner(Request $request, string $destination): RedirectResponse
    {
        $partners = app(\App\Services\Support\SupportAccess::class)->partnerUser($request)->partners()->orderBy('name')->get(['partners.id', 'partners.slug']);
        abort_unless($partners->count() === 1, 403);

        return to_route($destination, $partners->first());
    }
}
