<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OperationalAdjustment;
use App\Models\Partner;
use App\Models\ReferralRelationship;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\JpLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAdjustmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = OperationalAdjustment::query()->with('actor:id,name')->latest();
        if ($request->filled('type')) $query->where('type', $request->string('type'));
        if ($request->filled('actor')) $query->where('actor_user_id', $request->integer('actor'));
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->date('to'));
        return Inertia::render('admin/adjustments/index', ['adjustments' => $query->paginate(30)->withQueryString(), 'filters' => $request->only('type', 'actor', 'from', 'to'), 'users' => User::orderBy('name')->get(['id', 'name']), 'partners' => Partner::orderBy('name')->get(['id', 'name']), 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function ledger(Request $request)
    {
        $data = $request->validate(['beneficiary_type' => 'required|in:USER,PARTNER', 'beneficiary_id' => 'required|integer', 'unit' => 'required|in:CASH,JP', 'amount' => 'required|numeric|not_in:0', 'type' => 'required_if:unit,JP|nullable|in:ledger_credit,ledger_debit', 'idempotency_key' => 'required_if:unit,JP|nullable|uuid', 'reason' => 'required|string|max:2000', 'confirm' => 'accepted']);
        $this->beneficiaryExists($data['beneficiary_type'], $data['beneficiary_id']);
        if ($data['unit'] === 'JP') {
            if ($data['beneficiary_type'] !== 'USER' || ! ctype_digit((string) $data['amount']) || (int) $data['amount'] <= 0) {
                throw ValidationException::withMessages(['amount' => 'JP requiere un usuario y un monto entero positivo.']);
            }
            app(JpLedgerService::class)->adminAdjustment($request->user(), User::findOrFail($data['beneficiary_id']), $data['type'], (int) $data['amount'], $data['reason'], $data['idempotency_key']);
            return back()->with('success', 'Ajuste JP contabilizado y auditado.');
        }
        DB::transaction(function () use ($data, $request) {
            $amount = (float) $data['amount'];
            if ($amount < 0 && $this->ledgerBalance($data['beneficiary_type'], $data['beneficiary_id'], $data['unit']) + $amount < 0) abort(422, 'El ajuste dejaría un saldo de ledger negativo.');
            $this->record($request, $amount > 0 ? 'ledger_credit' : 'ledger_debit', null, null, $data['beneficiary_type'], $data['beneficiary_id'], $data['unit'], $amount, $data['reason'], ['ledger_balance' => $this->ledgerBalance($data['beneficiary_type'], $data['beneficiary_id'], $data['unit'])], ['ledger_balance' => $this->ledgerBalance($data['beneficiary_type'], $data['beneficiary_id'], $data['unit']) + $amount]);
        });
        return back()->with('success', 'Ajuste de ledger registrado de forma inmutable.');
    }

    public function rewardStatus(Request $request, RewardTransaction $reward)
    {
        $data = $request->validate(['status' => 'required|in:available,cancelled', 'reason' => 'required|string|max:2000', 'confirm' => 'accepted']);
        abort_if($reward->status === RewardTransaction::STATUS_PAID, 422, 'Una recompensa pagada requiere un ajuste financiero, no reescritura.');
        $allowed = ($reward->status === RewardTransaction::STATUS_PENDING && $data['status'] === RewardTransaction::STATUS_AVAILABLE)
            || ($reward->status === RewardTransaction::STATUS_PENDING && $reward->reward_type === 'JP' && $data['status'] === RewardTransaction::STATUS_CANCELLED)
            || ($reward->status === RewardTransaction::STATUS_AVAILABLE && $data['status'] === RewardTransaction::STATUS_CANCELLED);
        abort_unless($allowed, 422, 'Transición de estado no permitida.');
        DB::transaction(function () use ($request, $reward, $data) {
            $before = $reward->fresh()->toArray();
            if ($reward->reward_type === 'JP' && $reward->status === RewardTransaction::STATUS_AVAILABLE && $data['status'] === RewardTransaction::STATUS_CANCELLED) {
                $reversal = app(JpLedgerService::class)->reverse($reward, $request->user()->id, $data['reason']);
                $after = $reward->fresh()->toArray() + ['reversal_reward_transaction_id' => $reversal->id];
            } else {
                $reward->update(['status' => $data['status'], 'available_at' => $data['status'] === 'available' ? now() : $reward->available_at]);
                $after = $reward->fresh()->toArray();
            }
            $this->record($request, 'reward_status_correction', RewardTransaction::class, $reward->id, $reward->beneficiary_type, $reward->beneficiary_id, $reward->reward_type, null, $data['reason'], $before, $after);
        });
        return back()->with('success', 'Estado corregido y auditado.');
    }

    public function attribution(Request $request, User $user)
    {
        $data = $request->validate(['referrer_type' => 'required|in:USER,PARTNER', 'referrer_id' => 'required|integer', 'reason' => 'required|string|max:2000', 'confirm' => 'accepted']);
        $this->beneficiaryExists($data['referrer_type'], $data['referrer_id']);
        abort_if($data['referrer_type'] === 'USER' && $data['referrer_id'] === $user->id, 422, 'No se permite auto-referido.');
        DB::transaction(function () use ($request, $user, $data) {
            $old = ReferralRelationship::where('referred_user_id', $user->id)->where('status', 'active')->lockForUpdate()->first();
            if ($old) $old->update(['status' => ReferralRelationship::STATUS_INVALID]);
            $relationship = ReferralRelationship::create(['referred_user_id' => $user->id, 'referrer_user_id' => $data['referrer_type'] === 'USER' ? $data['referrer_id'] : null, 'acquisition_partner_id' => $data['referrer_type'] === 'PARTNER' ? $data['referrer_id'] : null, 'referral_code' => $data['referrer_type'].':'.$data['referrer_id'], 'attributed_at' => now(), 'status' => ReferralRelationship::STATUS_ACTIVE]);
            $this->record($request, 'attribution_correction', ReferralRelationship::class, $relationship->id, null, null, null, null, $data['reason'], $old?->toArray(), $relationship->toArray());
        });
        return back()->with('success', 'Atribución corregida; los contactos históricos se preservaron.');
    }

    private function beneficiaryExists(string $type, int $id): void { abort_unless(($type === 'USER' ? User::whereKey($id) : Partner::whereKey($id))->exists(), 422, 'Beneficiario inexistente.'); }
    private function ledgerBalance(string $type, int $id, string $unit): float
    {
        $rewards = RewardTransaction::where('beneficiary_type', $type)->where('beneficiary_id', $id)->where('reward_type', $unit)->whereIn('status', ['available', 'paid'])->sum('amount');
        $adjustments = OperationalAdjustment::where('beneficiary_type', $type)->where('beneficiary_id', $id)->where('unit', $unit)->whereIn('type', ['ledger_credit', 'ledger_debit'])->sum('amount');
        return (float) $rewards + (float) $adjustments;
    }
    private function record(Request $request, string $type, ?string $targetType, ?int $targetId, ?string $beneficiaryType, ?int $beneficiaryId, ?string $unit, ?float $amount, string $reason, ?array $before, ?array $after): void
    {
        $adjustment = OperationalAdjustment::create(['actor_user_id' => $request->user()->id, 'type' => $type, 'target_type' => $targetType, 'target_id' => $targetId, 'beneficiary_type' => $beneficiaryType, 'beneficiary_id' => $beneficiaryId, 'unit' => $unit, 'amount' => $amount, 'reason' => $reason, 'before' => $before, 'after' => $after]);
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => $type, 'subject_type' => OperationalAdjustment::class, 'subject_id' => $adjustment->id, 'metadata' => ['reason' => $reason, 'before' => $before, 'after' => $after]]);
    }
}
