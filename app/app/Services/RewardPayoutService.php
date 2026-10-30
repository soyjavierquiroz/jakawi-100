<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\RewardRule;
use App\Models\RewardPayout;
use App\Models\RewardTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RewardPayoutService
{
    public function partnerMinimum(): string
    {
        return (string) (AppSetting::where('key', 'partner_minimum_payout')->first()?->value['amount'] ?? 0);
    }

    public function requestPartner(Partner $partner, User $actor): RewardPayout
    {
        return DB::transaction(function () use ($partner, $actor): RewardPayout {
            Partner::query()->lockForUpdate()->findOrFail($partner->id);
            $rewards = RewardTransaction::query()
                ->where('beneficiary_type', RewardRule::BENEFICIARY_PARTNER)->where('beneficiary_id', $partner->id)
                ->where('reward_type', 'CASH')->where('currency', 'BOB')
                ->where('status', RewardTransaction::STATUS_AVAILABLE)
                ->whereDoesntHave('payouts', fn ($query) => $query->where('status', RewardPayout::STATUS_REQUESTED))
                ->lockForUpdate()->get();
            $amount = $rewards->sum('amount');
            abort_if($amount <= 0, 422, 'No hay recompensas disponibles para solicitar.');
            abort_if($amount < (float) $this->partnerMinimum(), 422, 'No alcanzas el mínimo de pago Partner configurado.');

            $payout = RewardPayout::create([
                'reference' => $this->reference(), 'beneficiary_type' => RewardRule::BENEFICIARY_PARTNER,
                'beneficiary_id' => $partner->id, 'requested_by_user_id' => $actor->id,
                'status' => RewardPayout::STATUS_REQUESTED, 'requested_at' => now(),
                'requested_amount' => $amount, 'currency' => 'BOB',
            ]);
            $payout->rewards()->attach($rewards->pluck('id'));
            $this->audit($actor, 'partner_payout_requested', $payout, null, ['beneficiary' => 'PARTNER:'.$partner->id, 'amount' => (string) $amount, 'reward_ids' => $rewards->pluck('id')->all()]);
            foreach ($rewards as $reward) $this->audit($actor, 'reward_reserved_for_payout', $reward, ['status' => $reward->status], ['payout_id' => $payout->id, 'payout_reference' => $payout->reference]);

            return $payout;
        });
    }

    public function pay(RewardPayout $payout, User $admin, string $paymentReference, ?string $notes = null): RewardPayout
    {
        return DB::transaction(function () use ($payout, $admin, $paymentReference, $notes): RewardPayout {
            $payout = RewardPayout::query()->lockForUpdate()->findOrFail($payout->id);
            abort_unless($payout->beneficiary_type === RewardRule::BENEFICIARY_PARTNER, 404);
            if ($payout->status === RewardPayout::STATUS_PAID) {
                return $payout;
            }
            abort_unless($payout->status === RewardPayout::STATUS_REQUESTED, 422, 'Solo se puede pagar una solicitud pendiente.');
            $rewards = RewardTransaction::query()->whereIn('id', $payout->rewards()->pluck('reward_transactions.id'))->with('conversion')->lockForUpdate()->get();
            abort_if($rewards->isEmpty() || $rewards->contains(fn (RewardTransaction $reward) => $reward->status !== RewardTransaction::STATUS_AVAILABLE || $reward->conversion?->status !== 'confirmed'), 422, 'Las recompensas incluidas ya no son válidas para pago.');
            $before = $payout->toArray();
            $payout->update(['status' => RewardPayout::STATUS_PAID, 'paid_at' => now(), 'paid_by_user_id' => $admin->id, 'payment_reference' => trim($paymentReference), 'notes' => $notes]);
            foreach ($rewards as $reward) {
                $rewardBefore = $reward->toArray();
                $reward->update(['status' => RewardTransaction::STATUS_PAID]);
                $this->audit($admin, 'reward_paid', $reward, $rewardBefore, $reward->fresh()->toArray() + ['payout_id' => $payout->id]);
            }
            $this->audit($admin, 'partner_payout_paid', $payout, $before, $payout->fresh()->toArray());

            return $payout->fresh();
        });
    }

    public function reject(RewardPayout $payout, User $admin, string $reason): RewardPayout
    {
        return DB::transaction(function () use ($payout, $admin, $reason): RewardPayout {
            $payout = RewardPayout::query()->lockForUpdate()->findOrFail($payout->id);
            abort_unless($payout->beneficiary_type === RewardRule::BENEFICIARY_PARTNER, 404);
            abort_unless($payout->status === RewardPayout::STATUS_REQUESTED, 422, 'Solo se puede rechazar una solicitud pendiente.');
            $before = $payout->toArray();
            $payout->update(['status' => RewardPayout::STATUS_REJECTED, 'rejection_reason' => trim($reason)]);
            $this->audit($admin, 'partner_payout_rejected', $payout, $before, $payout->fresh()->toArray());
            foreach ($payout->rewards()->lockForUpdate()->get() as $reward) {
                $this->audit($admin, 'reward_released_from_payout', $reward, ['payout_id' => $payout->id], ['status' => $reward->status, 'reason' => trim($reason)]);
            }

            return $payout->fresh();
        });
    }

    private function reference(): string
    {
        do {
            $reference = 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (RewardPayout::where('reference', $reference)->exists());

        return $reference;
    }

    private function audit(User $actor, string $action, object $subject, ?array $before, array $after): void
    {
        AuditLog::create(['actor_user_id' => $actor->id, 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id, 'metadata' => ['before' => $before, 'after' => $after]]);
    }
}
