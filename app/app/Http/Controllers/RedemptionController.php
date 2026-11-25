<?php

namespace App\Http\Controllers;
use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
use App\Services\RedemptionService;
use App\Services\AnalyticsTracker;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class RedemptionController extends Controller
{
    public function intent(Request $request, Benefit $benefit, PublicController $public): Response
    {
        $props = $public->benefitPublicProps($benefit, $request);
        $locations = $props['locations'];
        $selected = $locations->firstWhere('id', $request->integer('location_id'));
        $locationId = $selected['id'] ?? ($locations->count() === 1 ? $locations->first()['id'] : null);

        return Inertia::render('benefits/show', $props + [
            'redemptionIntent' => $props['availability']['available'],
            'selectedLocationId' => $locationId,
        ]);
    }

    public function start(Request $r, Benefit $benefit, RedemptionService $service, AnalyticsTracker $analytics)
    {
        if (! $r->filled('location_id')) {
            $locations = $benefit->availableLocations()->get()->filter->hasRedemptionPin();
            if ($locations->count() === 1) {
                $r->merge(['location_id' => $locations->first()->id]);
            }
        }
        $data = $r->validate(['location_id' => ['required', 'integer', 'exists:locations,id'], 'social_challenge_reward_grant_id'=>['nullable','integer']]);
        $location = Location::findOrFail($data['location_id']);
        if (! $benefit->isAvailableAt($location) || ! $location->hasRedemptionPin()) {
            return back()->withErrors(['redemption' => 'Este beneficio no está disponible en esa sucursal.']);
        }
        $analytics->journeyIntentStarted($benefit);
        if ($benefit->requiresMembership() && ! $r->user()->hasActiveMembership()) {
            return redirect('/membresia?'.http_build_query(['journey' => 'BENEFIT', 'action' => 'REDEEM', 'resource_id' => $benefit->id, 'location_id' => $location->id]));
        }
        try {
            $redemption = $service->start($r->user(), $benefit, Location::findOrFail($data['location_id']), isset($data['social_challenge_reward_grant_id']) ? \App\Models\ChallengeRewardGrant::find($data['social_challenge_reward_grant_id']) : null);
        } catch (DomainException $e) {
            return back()->withErrors(['redemption' => $e->getMessage()]);
        }

        return to_route('redemptions.show', $redemption->public_id);
    }

    public function show(Request $r, Redemption $redemption): Response
    {
        abort_unless($redemption->user_id === $r->user()->id, 403);

        if ($redemption->isExpired()) {
            $redemption->update(['status' => Redemption::STATUS_EXPIRED]);
            $redemption->refresh();
        }

        $membership = $redemption->membership;

        return Inertia::render('redemptions/show', ['redemption' => $redemption->only(['public_id', 'code', 'partner_name', 'location_name', 'benefit_title', 'status', 'expires_at', 'confirmed_at', 'savings_amount']) + ['address' => $redemption->location?->address, 'terms' => $redemption->benefit?->terms, 'benefit_slug' => $redemption->benefit?->slug, 'qr_url' => $redemption->isPending() && $redemption->expires_at->isFuture() ? URL::temporarySignedRoute('partner.redemptions.scan', $redemption->expires_at, ['partner' => $redemption->partner->slug, 'redemption_public_id' => $redemption->public_id]) : null, 'membership' => $membership ? ['amount_paid' => $membership->amount_paid, 'confirmed_savings' => $membership->confirmedSavings(), 'has_paid_for_itself' => $membership->hasPaidForItself()] : null]]);
    }

    public function form(Partner $partner): Response
    {
        return Inertia::render('redemptions/validate', ['partner' => $partner->only(['name', 'slug'])]);
    }

    public function confirm(Request $r, Partner $partner, RedemptionService $service): Response|\Symfony\Component\HttpFoundation\Response
    {
        $data = $r->validate(['code' => ['required', 'string', 'size:6'], 'pin' => ['required', 'digits:6']]);
        $redemption = Redemption::query()->where('code', strtoupper($data['code']))->where('partner_id', $partner->id)->first();
        if ($redemption === null) {
            return Inertia::render('redemptions/validate', ['partner' => $partner->only(['name', 'slug']), 'error' => 'Código inválido. Comprueba el código y el establecimiento.'])->toResponse($r)->setStatusCode(403);
        }

        try {
            $outcome = $service->confirmWithOutcome(strtoupper($data['code']), $data['pin']);
            return Inertia::render('redemptions/validate', [
                'partner' => $partner->only(['name', 'slug']),
                'result' => $outcome->redemption->only(['benefit_title', 'partner_name', 'location_name', 'status']),
                'message' => $outcome->replayed ? 'Canje previamente utilizado. Ya estaba confirmado; no se registró otro canje.' : 'Canje confirmado correctamente.',
            ]);
        } catch (DomainException $e) {
            return Inertia::render('redemptions/validate', ['partner' => $partner->only(['name', 'slug']), 'error' => $this->validationMessage($e)]);
        }
    }

    public function scan(Request $request, Partner $partner, string $redemption_public_id): Response|RedirectResponse
    {
        $redemption = Redemption::where('public_id', $redemption_public_id)->firstOrFail();
        if (! $request->user()) {
            return redirect()->guest(route('partner.login'));
        }
        abort_unless($request->user()->managesPartner($partner->id) && $redemption->partner_id === $partner->id, 403);

        return Inertia::render('redemptions/scan', ['partner' => $partner->only(['name', 'slug']), 'redemption' => $redemption->only(['public_id', 'code', 'benefit_title', 'location_name', 'savings_amount', 'status', 'expires_at', 'confirmed_at'])]);
    }

    public function scanConfirm(Request $request, Partner $partner, string $redemption_public_id, RedemptionService $service): Response
    {
        $redemption = Redemption::where('public_id', $redemption_public_id)->firstOrFail();
        abort_unless($request->user()?->managesPartner($partner->id) && $redemption->partner_id === $partner->id, 403);
        $data = $request->validate(['pin' => ['required', 'digits:6']]);
        $error = null;
        $message = null;
        try {
            $outcome = $service->confirmWithOutcome($redemption->code, $data['pin']);
            $result = $outcome->redemption;
            $message = $outcome->replayed ? 'Canje previamente utilizado. No se registró otro canje.' : 'Canje confirmado correctamente.';
        } catch (DomainException $e) {
            $result = $redemption->fresh();
            $error = $this->validationMessage($e);
        }

        return Inertia::render('redemptions/scan', ['partner' => $partner->only(['name', 'slug']), 'message' => $message, 'error' => $error, 'redemption' => $result->only(['public_id', 'code', 'benefit_title', 'location_name', 'savings_amount', 'status', 'expires_at', 'confirmed_at'])]);
    }
    private function validationMessage(DomainException $error): string
    {
        return match ($error->getMessage()) {
            'Redemption code not found.' => 'Código inválido. Comprueba el código y el establecimiento.',
            'This redemption code has expired.' => 'Código expirado. El miembro debe generar un nuevo código.',
            'The location redemption PIN is invalid.' => 'PIN incorrecto. Utiliza el PIN de la sede indicada.',
            'The membership is no longer active.', 'An active membership is required.' => 'La membresía requerida no está activa. No se confirmó el canje.',
            'This benefit is not available at this location.' => 'Este beneficio no está disponible en esta sede o según sus condiciones vigentes.',
            'This location does not have a redemption PIN.' => 'Esta sede no tiene un PIN de canje configurado. Contacta a JAKAWI.',
            'The redemption limit for this benefit has been reached.' => 'Se alcanzó el límite de canjes permitido para este beneficio.',
            'A challenge grant is required.', 'A valid challenge grant is required.' => 'La autorización de recompensa no está vigente para este canje.',
            'This redemption cannot be confirmed.' => 'Este canje ya no está pendiente y no puede confirmarse.',
            default => 'No fue posible validar este canje. Revisa sus condiciones o contacta a JAKAWI.',
        };
    }

}
