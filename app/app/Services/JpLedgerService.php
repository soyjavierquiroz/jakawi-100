<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\JpHold;
use App\Models\OperationalAdjustment;
use App\Models\RewardTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JpLedgerService
{
    public function adminAdjustment(User $actor, User $beneficiary, string $type, int $amount, string $reason, string $key): OperationalAdjustment
    {
        if (! in_array($type, ['ledger_credit', 'ledger_debit'], true) || $amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'El ajuste JP requiere un monto entero positivo.']);
        }

        return DB::transaction(function () use ($actor, $beneficiary, $type, $amount, $reason, $key): OperationalAdjustment {
            User::query()->lockForUpdate()->findOrFail($beneficiary->id);
            $existing = OperationalAdjustment::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->actor_user_id !== $actor->id || $existing->beneficiary_type !== 'USER' || $existing->beneficiary_id !== $beneficiary->id || $existing->type !== $type || (int) abs((int) $existing->amount) !== $amount || $existing->reason !== $reason) {
                    throw ValidationException::withMessages(['idempotency_key' => 'La clave ya corresponde a otro ajuste.']);
                }
                return $existing;
            }

            $balances = app(JpBalanceService::class);
            $before = $balances->for($beneficiary);
            if ($type === 'ledger_debit' && $amount > $before['available_balance']) {
                throw ValidationException::withMessages(['amount' => 'No hay JP disponibles suficientes para este débito.']);
            }

            $signed = $type === 'ledger_credit' ? $amount : -$amount;
            $adjustment = OperationalAdjustment::create([
                'actor_user_id' => $actor->id, 'type' => $type,
                'beneficiary_type' => 'USER', 'beneficiary_id' => $beneficiary->id,
                'unit' => 'JP', 'amount' => $signed, 'reason' => $reason,
                'idempotency_key' => $key, 'before' => $before,
                'after' => ['ledger_balance' => $before['ledger_balance'] + $signed, 'held' => $before['held'], 'available_balance' => max(0, $before['ledger_balance'] + $signed - $before['held'])],
            ]);
            $entry = $this->entry($beneficiary->id, $signed, 'admin_adjustment', ['operational_adjustment_id' => $adjustment->id]);
            AuditLog::create(['actor_user_id' => $actor->id, 'action' => $type, 'subject_type' => OperationalAdjustment::class, 'subject_id' => $adjustment->id, 'metadata' => ['reason' => $reason, 'before' => $before, 'after' => $adjustment->after, 'reward_transaction_id' => $entry->id]]);

            return $adjustment;
        });
    }

    public function forfeit(JpHold $hold, ?int $actorId = null): ?RewardTransaction
    {
        return DB::transaction(function () use ($hold, $actorId): ?RewardTransaction {
            User::query()->lockForUpdate()->findOrFail($hold->user_id);
            $hold = JpHold::query()->lockForUpdate()->findOrFail($hold->id);
            $existing = RewardTransaction::where('forfeited_jp_hold_id', $hold->id)->first();
            if ($existing) return $existing;
            if ($hold->status !== JpHold::HELD) return null;

            $entry = $this->entry($hold->user_id, -(int) $hold->amount, 'unlock_forfeiture', ['forfeited_jp_hold_id' => $hold->id]);
            $hold->update(['status' => JpHold::FORFEITED, 'forfeited_at' => now()]);
            AuditLog::create(['actor_user_id' => $actorId, 'action' => 'unlock_jp_forfeited', 'subject_type' => JpHold::class, 'subject_id' => $hold->id, 'metadata' => ['reward_transaction_id' => $entry->id, 'amount' => $hold->amount]]);
            return $entry;
        });
    }

    public function reverse(RewardTransaction $original, ?int $actorId = null, ?string $reason = null): RewardTransaction
    {
        return DB::transaction(function () use ($original, $actorId, $reason): RewardTransaction {
            User::query()->lockForUpdate()->findOrFail($original->beneficiary_user_id);
            $original = RewardTransaction::query()->lockForUpdate()->findOrFail($original->id);
            if ($original->reward_type !== 'JP' || $original->status !== RewardTransaction::STATUS_AVAILABLE) {
                throw ValidationException::withMessages(['reward' => 'Solo se puede revertir una recompensa JP contabilizada.']);
            }
            $existing = RewardTransaction::where('reversal_of_reward_transaction_id', $original->id)->first();
            if ($existing) return $existing;

            $amount = (string) $original->amount;
            if (! preg_match('/^-?[0-9]+\.00$/', $amount) || (int) $amount === 0) {
                throw ValidationException::withMessages(['reward' => 'La recompensa JP debe tener un monto entero no nulo.']);
            }
            $entry = $this->entry($original->beneficiary_user_id, -(int) $amount, 'reversal', ['reversal_of_reward_transaction_id' => $original->id]);
            AuditLog::create(['actor_user_id' => $actorId, 'action' => 'jp_reward_reversed', 'subject_type' => RewardTransaction::class, 'subject_id' => $entry->id, 'metadata' => ['original_reward_transaction_id' => $original->id, 'reason' => $reason]]);
            return $entry;
        });
    }

    private function entry(int $userId, int $amount, string $source, array $reference): RewardTransaction
    {
        return RewardTransaction::create([
            'beneficiary_user_id' => $userId, 'beneficiary_type' => 'USER', 'beneficiary_id' => $userId,
            'reward_type' => 'JP', 'currency' => 'JP', 'amount' => $amount,
            'status' => RewardTransaction::STATUS_AVAILABLE, 'available_at' => now(), 'source' => $source,
            ...$reference,
        ]);
    }
}
