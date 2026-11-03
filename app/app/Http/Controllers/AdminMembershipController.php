<?php

namespace App\Http\Controllers;

use App\Models\{AuditLog, Membership, User};
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipController extends Controller
{
    private function base()
    {
        return Membership::query()->with(['user:id,name,email', 'user.partners:id,name', 'purchases', 'bankPayments', 'activatedBy:id,name']);
    }

    private function state($query, string $state): void
    {
        match ($state) {
            'active' => $query->active(),
            'expired' => $query->where('status', '!=', 'cancelled')->expired(),
            'cancelled' => $query->where('status', 'cancelled'),
            'scheduled' => $query->where('status', '!=', 'cancelled')->where('starts_at', '>', now())->unexpired(),
            default => null,
        };
    }

    public function index(Request $request): Response
    {
        $data = $request->validate(['status' => 'nullable|in:active,expired,cancelled,scheduled', 'origin' => 'nullable|in:bank,cash,historical', 'q' => 'nullable|string|max:200']);
        $query = $this->base();
        $this->state($query, $data['status'] ?? '');
        if ($search = trim($data['q'] ?? '')) {
            $like = '%'.addcslashes($search, '\\%_').'%';
            $query->where(fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))->orWhere('payment_reference', 'ilike', $like));
        }
        match ($data['origin'] ?? '') {
            'bank' => $query->whereHas('bankPayments'),
            'cash' => $query->whereHas('purchases', fn ($p) => $p->whereIn('payment_channel', ['cash', 'manual_cash'])),
            'historical' => $query->whereDoesntHave('bankPayments')->whereDoesntHave('purchases'),
            default => null,
        };
        $summary = ['total' => Membership::count()];
        foreach (['active', 'expired', 'cancelled', 'scheduled'] as $state) {
            $count = Membership::query();
            $this->state($count, $state);
            $summary[$state] = $count->count();
        }
        return Inertia::render('admin/memberships/index', [
            'memberships' => \App\Support\ListingPage::make($query->latest('starts_at'))->through(fn ($m) => $this->present($m)),
            'summary' => $summary,
        ]);
    }

    public function show(Membership $membership): Response
    {
        $membership = $this->base()->findOrFail($membership->id);
        return Inertia::render('admin/memberships/show', [
            'membership' => $this->present($membership) + ['recorded_amount' => $membership->amount_paid, 'reference' => $membership->payment_reference, 'notes' => $membership->notes, 'created_at' => $membership->created_at, 'activated_by' => $membership->activatedBy?->name],
            'audit' => AuditLog::where('subject_type', Membership::class)->where('subject_id', $membership->id)->orderBy('id')->get()->map(fn ($a) => ['id' => $a->id, 'action' => $a->action, 'at' => $a->created_at, 'actor' => $a->actor_user_id ? 'Administrador #'.$a->actor_user_id : 'Operación COM autorizada', 'reason' => $a->metadata['reason'] ?? null]),
        ]);
    }

    private function present(Membership $m): array
    {
        $technical = $m->user && str_ends_with(strtolower($m->user->email), '@purchases.invalid');
        $payments = $m->bankPayments->map(fn ($p) => ['id' => 'bank-'.$p->id, 'label' => ($p->origin === 'CLUB' ? 'CLUB · ' : '').'Pago bancario', 'reference' => $p->reference, 'confirmed' => $p->status === 'approved', 'amount' => $p->status === 'approved' ? $p->amount : null, 'href' => '/admin/conciliacion-membresias?pago='.$p->id])
            ->concat($m->purchases->map(fn ($p) => ['id' => 'sale-'.$p->id, 'label' => in_array($p->payment_channel, ['cash', 'manual_cash'], true) ? 'Venta en efectivo' : 'Compra registrada', 'reference' => $p->reference, 'confirmed' => $p->status === 'confirmed', 'amount' => $p->status === 'confirmed' ? $p->amount : null, 'href' => '/admin/sales/'.$p->id]))->values();
        $confirmed = $payments->where('confirmed', true);
        return [
            'user_id' => $m->user_id, 'id' => $m->id, 'holder' => $technical ? 'Identidad pendiente de vinculación' : ($m->user?->name ?? 'Titular no disponible'),
            'email' => $technical ? null : $m->user?->email,
            'identity' => $technical ? 'Custodia técnica CLUB; no identifica al comprador' : ($m->user?->partners->isNotEmpty() ? 'Titular con vínculo Partner' : 'Titular de membresía'),
            'status' => $m->status === 'cancelled' ? 'cancelled' : (($m->effectiveEndsAt()?->isPast() ?? false) ? 'expired' : ($m->starts_at->isFuture() ? 'scheduled' : ($m->isActive() ? 'active' : 'unknown'))),
            'type' => $m->type, 'type_label' => $m->type === Membership::TYPE_PIONEER ? 'Pionero' : 'Estándar',
            'starts_at' => $m->starts_at, 'ends_at' => $m->effectiveEndsAt(),
            'expiry_pending_launch' => $m->type === Membership::TYPE_PIONEER && $m->effectiveEndsAt() === null,
            'historical_ends_at' => $m->ends_at,
            'origin' => $payments->isEmpty() ? 'Registro histórico / excepcional' : $payments->pluck('label')->unique()->implode(' · '),
            'confirmed_amount' => $confirmed->count() === 1 ? $confirmed->first()['amount'] : null,
            'payments' => $payments,
        ];
    }

    // Historical endpoint: deliberately absent from routine UI. No sale/payment is fabricated.
    public function activate(Request $request, MembershipService $service)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id', 'starts_at' => 'nullable|date',
            'amount_paid' => 'required|numeric|min:0|max:99999999.99', 'payment_method' => 'required|in:cash,transfer,bank_transfer,qr,bank_qr,other',
            'payment_reference' => 'required|string|max:255', 'notes' => 'nullable|string|max:2000',
            'reason' => 'required|string|min:10|max:2000', 'confirm' => 'required|accepted', 'password' => 'required|current_password',
        ], $this->exceptionMessages());
        $user = User::findOrFail($data['user_id']);
        abort_if(str_ends_with(strtolower($user->email), '@purchases.invalid'), 422, 'La custodia técnica requiere el flujo de identidad existente.');
        try {
            DB::transaction(function () use ($request, $service, $data, $user) {
                $m = $service->activate($user, $request->user(), isset($data['starts_at']) ? now()->parse($data['starts_at']) : null, $data['payment_method'], $data['payment_reference'], $data['notes'] ?? null, (string) $data['amount_paid']);
                $this->audit($request, $m, 'membership_exception_activated', $data['reason'], null);
            });
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['user_id' => 'El usuario ya tiene una membresía activa.']);
        }
        return to_route('admin.memberships.index')->with('success', 'Activación excepcional registrada y auditada.');
    }

    public function cancel(Request $request, Membership $membership, MembershipService $service)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000', 'confirm' => 'required|accepted', 'password' => 'required|current_password'], $this->exceptionMessages());
        DB::transaction(function () use ($request, $membership, $service, $data) {
            $locked = Membership::lockForUpdate()->findOrFail($membership->id);
            abort_if($locked->status === 'cancelled', 422, 'La membresía ya está cancelada.');
            $before = $locked->toArray();
            $service->cancel($locked);
            $this->audit($request, $locked->refresh(), 'membership_exception_cancelled', $data['reason'], $before);
        });
        return back()->with('success', 'Cancelación excepcional auditada. El cobro conserva su registro; no se emitió un reembolso.');
    }

    private function exceptionMessages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'max' => 'El valor supera el máximo permitido.',
            'min' => 'El valor no alcanza el mínimo permitido.',
            'in' => 'Selecciona una opción válida.',
            'string' => 'Introduce un texto válido.',
            'integer' => 'Introduce un número entero.',
            'date' => 'Introduce una fecha válida.',
            'reason.min' => 'Describe el motivo con al menos 10 caracteres.',
            'reason.max' => 'El motivo admite hasta 2000 caracteres.',
            'confirm.accepted' => 'Debes confirmar expresamente la operación excepcional.',
            'password.current_password' => 'La contraseña no es correcta.',
            'amount_paid.numeric' => 'El importe debe ser numérico.',
            'amount_paid.min' => 'El importe no puede ser negativo.',
            'payment_method.in' => 'El medio indicado no es válido.',
            'user_id.exists' => 'El usuario indicado no existe.',
        ];
    }

    private function audit(Request $request, Membership $m, string $action, string $reason, ?array $before): void
    {
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => Membership::class, 'subject_id' => $m->id, 'metadata' => ['reason' => $reason, 'confirmed' => true, 'before' => $before, 'after' => $m->toArray()]]);
    }
}
