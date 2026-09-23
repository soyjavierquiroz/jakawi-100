<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
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

    public function form(Partner $partner): Response
    {
        return Inertia::render('redemptions/validate', ['partner' => $partner->only(['name', 'slug'])]);
    }

    public function confirm(Request $r, Partner $partner, RedemptionService $service): Response
    {
        $data = $r->validate(['code' => ['required', 'string', 'size:6'], 'pin' => ['required', 'digits:6']]);
        $redemption = Redemption::query()->where('code', strtoupper($data['code']))->where('partner_id', $partner->id)->first();
        abort_unless($redemption !== null, 403);

        try {
            $x = $service->confirm(strtoupper($data['code']), $data['pin']);

            return Inertia::render('redemptions/validate', ['partner' => $partner->only(['name', 'slug']), 'result' => $x->only(['benefit_title', 'partner_name', 'location_name', 'status'])]);
        } catch (DomainException $e) {
            return Inertia::render('redemptions/validate', ['partner' => $partner->only(['name', 'slug']), 'error' => 'No fue posible validar este canje.']);
        }
    }
}
