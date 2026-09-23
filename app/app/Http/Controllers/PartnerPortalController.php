<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $partners = $request->user()->partners()
            ->orderBy('name')
            ->get(['partners.id', 'partners.name', 'partners.slug']);

        return Inertia::render('partner/index', [
            'partners' => $partners->map(fn ($partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'role' => $partner->pivot->role,
            ]),
        ]);
    }
}
