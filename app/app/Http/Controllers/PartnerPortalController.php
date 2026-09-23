<?php

namespace App\Http\Controllers;

use App\Models\ExperienceReservation;
use App\Models\Partner;
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
        ]);
    }

    public function redirectToSinglePartner(Request $request, string $destination): RedirectResponse
    {
        $partners = $request->user()->partners()->orderBy('name')->get(['partners.id', 'partners.slug']);
        abort_unless($partners->count() === 1, 403);

        return to_route($destination, $partners->first());
    }
}
