<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
use App\Services\RedemptionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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

        return Inertia::render('redemptions/show', ['redemption' => $redemption->only(['public_id', 'code', 'partner_name', 'location_name', 'benefit_title', 'status', 'expires_at', 'confirmed_at', 'savings_amount']) + ['qr_url' => $redemption->isPending() && $redemption->expires_at->isFuture() ? URL::temporarySignedRoute('partner.redemptions.scan', $redemption->expires_at, ['partner' => $redemption->partner->slug, 'redemption_public_id' => $redemption->public_id]) : null]]);
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

    public function scan(Request $request, Partner $partner, string $redemption_public_id): Response|RedirectResponse
    {
        $redemption = Redemption::where('public_id', $redemption_public_id)->firstOrFail();
        if (! $request->user()) {
            return redirect()->guest(route('partner.login'));
        }
        abort_unless($request->user()->managesPartner($partner->id) && $redemption->partner_id === $partner->id, 403);

        return Inertia::render('redemptions/scan', ['partner' => $partner->only(['name', 'slug']), 'redemption' => $redemption->only(['public_id', 'code', 'benefit_title', 'location_name', 'savings_amount', 'status', 'expires_at', 'confirmed_at']) + ['member_name' => $redemption->user->name]]);
    }

    public function scanConfirm(Request $request, Partner $partner, string $redemption_public_id, RedemptionService $service): Response
    {
        $redemption = Redemption::where('public_id', $redemption_public_id)->firstOrFail();
        abort_unless($request->user()?->managesPartner($partner->id) && $redemption->partner_id === $partner->id, 403);
        $data = $request->validate(['pin' => ['required', 'digits:6']]);
        try {
            $result = $service->confirm($redemption->code, $data['pin']);
        } catch (DomainException) {
            $result = $redemption->fresh();
        }

        return Inertia::render('redemptions/scan', ['partner' => $partner->only(['name', 'slug']), 'redemption' => $result->only(['public_id', 'code', 'benefit_title', 'location_name', 'savings_amount', 'status', 'expires_at', 'confirmed_at']) + ['member_name' => $result->user->name, 'error' => $result->isConfirmed() ? null : 'No fue posible confirmar este canje.']]);
    }
}
