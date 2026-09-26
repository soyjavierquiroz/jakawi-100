<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MembershipPurchase;
use App\Models\ProgramEnrollment;
use App\Models\RewardTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipPurchaseService
{
    public function confirmManualCash(User $beneficiary, User $recordedBy, ?User $collector, string $manualReference, string $idempotencyKey): MembershipPurchase
    {
        return DB::transaction(function () use ($beneficiary, $recordedBy, $collector, $manualReference, $idempotencyKey): MembershipPurchase {
            $existing = MembershipPurchase::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $beneficiary = User::query()->lockForUpdate()->findOrFail($beneficiary->id);
            $purchase = MembershipPurchase::create([
                'reference' => $this->reference(), 'idempotency_key' => $idempotencyKey,
                'beneficiary_user_id' => $beneficiary->id, 'recorded_by_user_id' => $recordedBy->id,
                'collected_by_user_id' => $collector?->id, 'payment_channel' => 'manual_cash',
                'status' => MembershipPurchase::STATUS_CONFIRMED, 'amount' => config('jakawi.membership.price_bob'),
                'currency' => config('jakawi.membership.currency', 'BOB'), 'duration_days' => config('jakawi.membership.duration_days'),
                'product_key' => config('jakawi.membership.product_key', 'jakawi_annual'), 'manual_reference' => trim($manualReference),
                'paid_at' => now(), 'processed_at' => now(),
            ]);
            $membership = app(MembershipService::class)->activate($beneficiary, $recordedBy, null, 'manual_cash', $purchase->reference, null, (string) $purchase->amount);
            $conversion = app(ConversionRecorder::class)->record($beneficiary, [
                'idempotency_key' => 'membership-purchase-'.$purchase->id, 'type' => 'membership_purchased',
                'product_type' => 'membership', 'product_key' => $purchase->product_key, 'product_id' => $purchase->id,
                'order_reference' => $purchase->reference, 'gross_amount' => $purchase->amount, 'eligible_amount' => $purchase->amount,
                'currency' => $purchase->currency, 'status' => 'confirmed', 'occurred_at' => $purchase->paid_at,
                'attribution_snapshot' => ['credited_seller_user_id' => $collector?->id],
            ]);
            $purchase->update(['membership_id' => $membership->id, 'conversion_id' => $conversion->id]);
            $reward = $collector?->hasActiveProgram(ProgramEnrollment::TYPE_PROMOTER)
                ? app(RewardResolver::class)->createFor($conversion, $collector, ProgramEnrollment::TYPE_PROMOTER) : null;
            AuditLog::create(['actor_user_id' => $recordedBy->id, 'action' => 'membership_sale_created', 'subject_type' => MembershipPurchase::class, 'subject_id' => $purchase->id, 'metadata' => ['reference' => $purchase->reference, 'amount' => $purchase->amount]]);
            AuditLog::create(['actor_user_id' => $recordedBy->id, 'action' => 'membership_activated_from_purchase', 'subject_type' => get_class($membership), 'subject_id' => $membership->id, 'metadata' => ['purchase_id' => $purchase->id]]);
            if ($reward) {
                AuditLog::create(['actor_user_id' => $recordedBy->id, 'action' => 'reward_created', 'subject_type' => RewardTransaction::class, 'subject_id' => $reward->id, 'metadata' => ['purchase_id' => $purchase->id, 'amount' => $reward->amount]]);
            }

            return $purchase->refresh()->load(['beneficiary', 'membership', 'conversion', 'collectedBy']);
        });
    }

    public function refund(MembershipPurchase $purchase, User $admin, string $reason): MembershipPurchase
    {
        return DB::transaction(function () use ($purchase, $admin, $reason): MembershipPurchase {
            $purchase = MembershipPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            if ($purchase->status === MembershipPurchase::STATUS_REFUNDED) {
                return $purchase;
            }
            if ($purchase->status !== MembershipPurchase::STATUS_CONFIRMED) {
                abort(422, 'Only confirmed purchases can be refunded.');
            }
            if ($purchase->membership_id) {
                app(MembershipService::class)->cancel($purchase->membership()->firstOrFail());
            }
            if ($purchase->conversion_id) {
                $purchase->conversion()->lockForUpdate()->firstOrFail()->update(['status' => 'refunded']);
                $rewards = RewardTransaction::query()->where('conversion_id', $purchase->conversion_id)->lockForUpdate()->get();
                foreach ($rewards as $reward) {
                    $reward->update(['status' => RewardTransaction::STATUS_CANCELLED]);
                    AuditLog::create(['actor_user_id' => $admin->id, 'action' => 'reward_cancelled', 'subject_type' => RewardTransaction::class, 'subject_id' => $reward->id, 'metadata' => ['purchase_id' => $purchase->id]]);
                }
            }
            $purchase->update(['status' => MembershipPurchase::STATUS_REFUNDED, 'refunded_at' => now(), 'refunded_by_user_id' => $admin->id, 'refund_reason' => $reason]);
            AuditLog::create(['actor_user_id' => $admin->id, 'action' => 'membership_sale_refunded', 'subject_type' => MembershipPurchase::class, 'subject_id' => $purchase->id, 'metadata' => ['before_status' => MembershipPurchase::STATUS_CONFIRMED, 'after_status' => MembershipPurchase::STATUS_REFUNDED, 'reason' => $reason]]);

            return $purchase->refresh();
        });
    }

    private function reference(): string
    {
        do {
            $reference = 'MS-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (MembershipPurchase::where('reference', $reference)->exists());

        return $reference;
    }
}
