<?php

namespace App\Http\Controllers;
use App\Models\{Partner, Unlock, UnlockParticipation, User};
use App\Services\{UnlockParticipationService, UnlockStatusService};
use App\Support\{ListingPage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};
use Inertia\Inertia;

class PartnerUnlockController extends Controller
{
    private function authorize(Partner $partner, Request $request): void
    {
        $user = app(\App\Services\Support\SupportAccess::class)->partnerUser($request);
        $role = $user?->partners()->whereKey($partner->id)->first()?->pivot->role;
        abort_unless(in_array($role, ['owner', 'manager'], true), 403);
    }

    private function own(Partner $partner, Unlock $unlock): void
    {
        abort_unless($unlock->partner_id === $partner->id, 404);
    }

    private function editable(Unlock $unlock): bool
    {
        return in_array($unlock->status, [Unlock::DRAFT, Unlock::REJECTED], true)
            && ! $unlock->participations()->whereIn('status', [UnlockParticipation::COMMITTED, UnlockParticipation::UNLOCKED_PENDING_CONFIRMATION, UnlockParticipation::CONFIRMED, UnlockParticipation::FULFILLED, UnlockParticipation::NO_SHOW])->exists();
    }

    private function canPrepare(Partner $partner): bool
    {
        return in_array($partner->status, ['draft', 'published'], true);
    }

    private function metrics(Unlock $unlock): array
    {
        $counts = $unlock->participations()->selectRaw("count(*) filter (where status = 'INTERESTED') as interested, count(*) filter (where status in ('COMMITTED','UNLOCKED_PENDING_CONFIRMATION','CONFIRMED','FULFILLED','NO_SHOW')) as committed, count(*) filter (where status = 'CONFIRMED') as confirmed, count(*) filter (where status = 'FULFILLED') as fulfilled, count(*) filter (where status = 'CANCELLED_ON_TIME') as cancelled, count(*) filter (where status = 'EXPIRED') as expired, count(*) filter (where status = 'NO_SHOW') as no_show")->first()->getAttributes();
        $counts['fulfillment_rate'] = $counts['committed'] ? round($counts['fulfilled'] / $counts['committed'] * 100, 1) : 0;
        return $counts;
    }

    public function index(Request $request, Partner $partner)
    {
        $this->authorize($partner, $request);
        $page = ListingPage::make(Unlock::where('partner_id', $partner->id)->latest());
        $items = $page->getCollection()->map(fn ($u) => $u->only('id', 'slug', 'title', 'short_description', 'status') + ['editable' => $this->canPrepare($partner) && $this->editable($u), 'metrics' => $this->metrics($u)]);
        return Inertia::render('partner/unlocks/index', ['partner' => $partner->only('name', 'slug'), 'canCreate' => $this->canPrepare($partner), 'unlocks' => $items, 'pagination' => array_diff_key($page->toArray(), ['data' => true])]);
    }

    public function form(Request $request, Partner $partner, ?Unlock $unlock = null)
    {
        $this->authorize($partner, $request);
        if ($unlock) $this->own($partner, $unlock);
        return Inertia::render('partner/unlocks/form', [
            'partner' => $partner->only('name', 'slug'), 'unlock' => $unlock,
            'editable' => $this->canPrepare($partner) && (! $unlock || $this->editable($unlock)),
            'locationIds' => $unlock ? $unlock->locations()->pluck('locations.id') : [],
            'locations' => $partner->locations()->orderBy('name')->get(['id', 'name', 'status']),
            'reviewNotes' => $unlock ? $unlock->statusHistory()->whereIn('to_status', [Unlock::REJECTED, Unlock::APPROVED, Unlock::PENDING_REVIEW])->whereNotNull('reason')->latest('id')->limit(5)->get(['id', 'to_status', 'reason', 'created_at']) : [],
        ]);
    }

    public function save(Request $request, Partner $partner, ?Unlock $unlock = null)
    {
        $this->authorize($partner, $request);
        return DB::transaction(function () use ($request, $partner, $unlock) {
            if ($unlock) {
                $unlock = Unlock::lockForUpdate()->findOrFail($unlock->id);
                $this->own($partner, $unlock);
                if ($unlock->participations()->whereIn('status', [UnlockParticipation::COMMITTED, UnlockParticipation::UNLOCKED_PENDING_CONFIRMATION, UnlockParticipation::CONFIRMED, UnlockParticipation::FULFILLED, UnlockParticipation::NO_SHOW])->exists()) {
                    $message = 'Esta propuesta tiene compromisos registrados y no admite cambios materiales.';
                    if ($request->header('X-Inertia')) throw ValidationException::withMessages(['status' => $message]);
                    abort(422, $message);
                }
                abort_unless($this->editable($unlock), 403, 'Esta propuesta no admite cambios. Consulta su estado y las observaciones de JAKAWI.');
            }
            $data = $request->validate([
                'title' => 'required|string|max:255', 'short_description' => 'nullable|string', 'description' => 'nullable|string', 'terms' => 'nullable|string',
                'type' => 'required|in:BENEFIT,PRICE,EXPERIENCE,SECRET', 'minimum_commitments' => 'required|integer|min:1',
                'maximum_capacity' => 'nullable|integer|min:1|gte:minimum_commitments', 'commitment_deadline' => 'nullable|date',
                'free_user_offer' => 'nullable|string', 'member_offer' => 'nullable|string', 'location_ids' => 'sometimes|array',
                'location_ids.*' => ['integer', Rule::exists('locations', 'id')->where('partner_id', $partner->id)],
            ], \App\Support\PartnerEditorialValidation::messages() + ['location_ids.*.exists' => 'Selecciona únicamente sedes de este establecimiento.'], [
                'title' => 'título', 'type' => 'tipo de propuesta', 'minimum_commitments' => 'mínimo de compromisos',
                'maximum_capacity' => 'capacidad máxima', 'commitment_deadline' => 'plazo para comprometerse', 'location_ids' => 'sedes',
            ]);
            $locations = $data['location_ids'] ?? null;
            unset($data['location_ids']);
            $unlock ??= new Unlock(['partner_id' => $partner->id, 'origin' => 'PARTNER', 'created_by_user_id' => $request->user()->id, 'status' => Unlock::DRAFT]);
            $unlock->fill($data);
            $unlock->slug ??= Str::slug($data['title']).'-'.Str::lower(Str::random(5));
            $unlock->save();
            // Omission preserves existing assignments. An explicit [] deliberately clears them.
            if ($locations !== null) $unlock->locations()->sync($locations);
            return to_route('partner.unlocks.edit', [$partner, $unlock])->with('success', 'Borrador guardado. Las sedes seleccionadas se conservaron.');
        });
    }

    public function submit(Request $request, Partner $partner, Unlock $unlock, UnlockStatusService $service)
    {
        $this->authorize($partner, $request);
        return DB::transaction(function () use ($request, $partner, $unlock, $service) {
            $unlock = Unlock::lockForUpdate()->findOrFail($unlock->id);
            $this->own($partner, $unlock);
            if (! $this->editable($unlock)) throw ValidationException::withMessages(['status' => 'Esta propuesta no puede enviarse de nuevo desde su estado actual.']);
            $service->transition($unlock, Unlock::PENDING_REVIEW, $request->user(), null, [Unlock::DRAFT, Unlock::REJECTED]);
            return back()->with('success', 'Propuesta enviada a revisión. JAKAWI evaluará su contenido antes de cualquier activación.');
        });
    }

    public function participations(Request $request, Partner $partner, Unlock $unlock, UnlockParticipationService $service)
    {
        $this->authorize($partner, $request);
        $this->own($partner, $unlock);
        $page = ListingPage::make($unlock->participations()->with('user:id,name')->latest(), 25);
        $page->setCollection($page->getCollection()->map(function ($p) use ($partner, $unlock, $service) {
            $name = preg_split('/\s+/', trim($p->user?->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $display = ! $name ? 'Miembro' : $name[0].(count($name) > 1 ? ' '.mb_substr(end($name), 0, 1).'.' : '');
            $reason = $service->fulfillmentBlocker($p, $unlock);
            return $p->only(['id', 'status', 'created_at', 'interested_at', 'committed_at', 'confirmation_requested_at', 'confirmed_at', 'fulfilled_at', 'expired_at', 'no_show_at', 'cancelled_at']) + [
                'member_display_name' => $display, 'fulfillment_blocker' => $reason,
                'can_fulfill' => $partner->isPublished() && $reason === null,
            ];
        }));
        return Inertia::render('partner/unlocks/participations', ['partner' => $partner->only('name', 'slug', 'status'), 'unlock' => $unlock->only(['title', 'slug', 'status', 'fulfillment_starts_at', 'fulfillment_ends_at']), 'participations' => $page]);
    }

    public function fulfill(Request $request, Partner $partner, Unlock $unlock, UnlockParticipation $participation, UnlockParticipationService $service)
    {
        $this->authorize($partner, $request);
        $this->own($partner, $unlock);
        abort_unless($participation->unlock_id === $unlock->id, 404);
        $data = $request->validate(['note' => 'nullable|string|max:2000']);
        $service->fulfill($participation, $request->user(), 'partner_manual', $data['note'] ?? null);
        return back()->with('success', 'Cumplimiento registrado. Si ya estaba cumplido, se conserva el registro original.');
    }
}
