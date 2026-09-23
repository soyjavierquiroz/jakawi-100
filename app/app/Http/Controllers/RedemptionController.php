<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Redemption;
use App\Services\RedemptionService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RedemptionController extends Controller
{
    public function start(Request $r, Benefit $benefit, RedemptionService $service)
    {
        $data = $r->validate(['location_id' => ['required', 'integer', 'exists:locations,id']]);
        try {
            $redemption = $service->start($r->user(), $benefit, Location::findOrFail($data['location_id']));
        } catch (DomainException $e) {
            return back()->withErrors(['redemption' => $e->getMessage()]);
        }

        return to_route('redemptions.show', $redemption->public_id);
    }

    public function show(Request $r, Redemption $redemption): Response
    {
        abort_unless($redemption->user_id === $r->user()->id, 403);

        return Inertia::render('redemptions/show', ['redemption' => $redemption->only(['public_id', 'code', 'partner_name', 'location_name', 'benefit_title', 'status', 'expires_at', 'confirmed_at', 'savings_amount'])]);
    }

    public function form(): Response
    {
        return Inertia::render('redemptions/validate');
    }

    public function confirm(Request $r, RedemptionService $service): Response
    {
        $data = $r->validate(['code' => ['required', 'string', 'size:6'], 'pin' => ['required', 'digits:6']]);
        try {
            $x = $service->confirm(strtoupper($data['code']), $data['pin']);

            return Inertia::render('redemptions/validate', ['result' => $x->only(['benefit_title', 'partner_name', 'location_name', 'status'])]);
        } catch (DomainException $e) {
            return Inertia::render('redemptions/validate', ['error' => 'No fue posible validar este canje.']);
        }
    }
}
