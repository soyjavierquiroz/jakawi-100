<?php

namespace App\Http\Controllers;

use App\Models\AttributionTouch;
use App\Models\MembershipPurchaseRequest;
use App\Models\User;
use App\Services\AnalyticsTracker;
use App\Services\PublicJourneyContinuation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MembershipOfferController extends Controller
{
    public function show(Request $request, PublicJourneyContinuation $continuation, AnalyticsTracker $analytics): Response
    {
        if ($request->query()) {
            $query = $request->query();
            if (array_diff(array_keys($query), ['journey', 'action', 'resource_id', 'experience_session_id'])
                || ! isset($query['journey'], $query['action'], $query['resource_id'])
                || ! ctype_digit((string) $query['resource_id'])) {
                throw ValidationException::withMessages(['journey' => 'Intención inválida.']);
            }
            $journey = $query['journey'];
            $action = $query['action'];
            $resourceId = (int) $query['resource_id'];
            if (! is_string($journey) || ! is_string($action)
                || ! in_array($journey.'/'.$action, ['EXPERIENCE/RESERVE', 'BENEFIT/REDEEM', 'UNLOCK/COMMIT'], true)) {
                throw ValidationException::withMessages(['journey' => 'Intención inválida.']);
            }
            $context = [];
            if (isset($query['experience_session_id'])) {
                if ($journey !== 'EXPERIENCE' || ! ctype_digit((string) $query['experience_session_id'])) {
                    throw ValidationException::withMessages(['experience_session_id' => 'Fecha inválida.']);
                }
                $context = ['experience_session_id' => (int) $query['experience_session_id']];
            }
            try {
                $continuation->set($journey, $resourceId, $action, $context);
                if ($continuation->destination($continuation->get()) === null) {
                    throw new \InvalidArgumentException();
                }
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['journey' => 'Intención inválida.']);
            }
        }

        $user = $request->user();
        $membership = $user?->activeMembership()->first();
        $pending = $user ? MembershipPurchaseRequest::where('user_id', $user->id)->where('status', MembershipPurchaseRequest::REQUESTED)->first() : null;
        $completed = $membership && $user ? MembershipPurchaseRequest::where('user_id', $user->id)
            ->where('status', MembershipPurchaseRequest::COMPLETED)->whereNull('returned_at')->latest('completed_at')->get()
            ->first(fn (MembershipPurchaseRequest $item) => $item->intent() && $continuation->destination($item->intent())) : null;
        $analytics->record('membership_view');

        return Inertia::render('membresia', [
            'price' => config('jakawi.membership.price_bob'),
            'durationDays' => config('jakawi.membership.duration_days'),
            'membership' => $membership ? ['ends_at' => $membership->ends_at, 'confirmed_savings' => $membership->confirmedSavings()] : null,
            'requestStatus' => $pending?->status,
            'returnIntent' => $completed ? ['id' => $completed->id, 'journey' => $completed->journey_type] : null,
        ]);
    }

    public function register(Request $request, PublicJourneyContinuation $continuation): RedirectResponse
    {
        $request->session()->put('url.intended', '/membresia');
        $continuation->consume();
        return redirect('/register');
    }

    public function request(Request $request, PublicJourneyContinuation $continuation, AnalyticsTracker $analytics): RedirectResponse
    {
        if ($request->except('_token') !== []) {
            throw ValidationException::withMessages(['journey' => 'La solicitud no acepta datos de intención externos.']);
        }
        $created = DB::transaction(function () use ($request, $continuation): ?MembershipPurchaseRequest {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->hasActiveMembership() || MembershipPurchaseRequest::where('user_id', $user->id)->where('status', MembershipPurchaseRequest::REQUESTED)->exists()) {
                return null;
            }
            $intent = $continuation->consume();
            if ($intent && ! in_array($intent['journey'].'/'.$intent['action'], ['EXPERIENCE/RESERVE', 'BENEFIT/REDEEM', 'UNLOCK/COMMIT'], true)) {
                $intent = null;
            }
            if ($intent && $continuation->destination($intent) === null) {
                $intent = null;
            }
            $touch = AttributionTouch::where('user_id', $user->id)->latest('occurred_at')->latest('id')->first();
            return MembershipPurchaseRequest::create([
                'user_id' => $user->id, 'status' => MembershipPurchaseRequest::REQUESTED,
                'attribution_touch_id' => $touch?->id,
                'journey_type' => $intent['journey'] ?? null, 'journey_action' => $intent['action'] ?? null,
                'journey_resource_id' => $intent['resource_id'] ?? null,
                'journey_context' => $intent['context'] ?? null, 'requested_at' => now(),
            ]);
        });
        if ($created) {
            $analytics->record('membership_assistance_requested', ['user_id' => $created->user_id], $this->metadata($created));
        }
        return to_route('membership.show');
    }

    public function returnToIntent(Request $request, MembershipPurchaseRequest $purchaseRequest, PublicJourneyContinuation $continuation, AnalyticsTracker $analytics): RedirectResponse
    {
        $destination = DB::transaction(function () use ($request, $purchaseRequest, $continuation): ?string {
            $item = MembershipPurchaseRequest::whereKey($purchaseRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->user_id === $request->user()->id && $request->user()->hasActiveMembership()
                && $item->status === MembershipPurchaseRequest::COMPLETED && $item->returned_at === null, 404);
            $intent = $item->intent();
            abort_unless($intent && in_array($intent['journey'].'/'.$intent['action'], ['EXPERIENCE/RESERVE', 'BENEFIT/REDEEM', 'UNLOCK/COMMIT'], true), 404);
            $destination = $continuation->destination($intent);
            abort_unless($destination, 404);
            $item->update(['returned_at' => now()]);
            return $destination;
        });
        $analytics->record('membership_returned_to_intent', ['user_id' => $request->user()->id], $this->metadata($purchaseRequest));
        return redirect()->to($destination);
    }

    private function metadata(MembershipPurchaseRequest $item): array
    {
        return array_filter(['journey' => $item->journey_type, 'action' => $item->journey_action,
            'resource_id' => $item->journey_resource_id, 'request_status' => $item->status], fn ($value) => $value !== null);
    }
}
