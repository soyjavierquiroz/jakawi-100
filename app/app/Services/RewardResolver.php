<?php

namespace App\Services;

use App\Models\Conversion;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;

class RewardResolver
{
    public function createFor(Conversion $conversion, User $beneficiary, string $participantType): ?RewardTransaction
    {
        $rule = $this->ruleFor($beneficiary, $participantType, $conversion->type, $conversion->product_key);

        if (! $rule || ! $this->withinLimits($rule, $beneficiary)) {
            return null;
        }

        $gross = (float) $conversion->eligible_amount;
        $amount = $rule->calculation_type === 'PERCENTAGE' ? round($gross * ((float) $rule->value / 100), 2) : (float) $rule->value;
        if ($amount <= 0) {
            return null;
        }

        return RewardTransaction::firstOrCreate(['conversion_id' => $conversion->id, 'reward_rule_id' => $rule->id], [
            'beneficiary_user_id' => $beneficiary->id, 'reward_type' => RewardRule::TYPE_CASH,
            'amount' => number_format($amount, 2, '.', ''), 'currency' => $rule->currency ?? $conversion->currency,
            'status' => RewardTransaction::STATUS_PENDING,
        ]);
    }

    public function ruleFor(User $beneficiary, string $participantType, string $event, ?string $productKey): ?RewardRule
    {
        return RewardRule::query()->where('status', RewardRule::STATUS_ACTIVE)->where('event', $event)
            ->where('reward_type', RewardRule::TYPE_CASH)
            ->where(fn ($q) => $q->whereNull('product_key')->orWhere('product_key', $productKey))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($q) => $q->where('beneficiary_user_id', $beneficiary->id)->orWhere(fn ($q) => $q->whereNull('beneficiary_user_id')->where('participant_type', $participantType))->orWhere(fn ($q) => $q->whereNull('beneficiary_user_id')->whereNull('participant_type')))
            ->orderByRaw('CASE WHEN beneficiary_user_id IS NOT NULL THEN 0 WHEN participant_type IS NOT NULL THEN 1 ELSE 2 END')
            ->orderByDesc('priority')->orderBy('id')->first();
    }

    private function withinLimits(RewardRule $rule, User $beneficiary): bool
    {
        if ($rule->maximum_rewards !== null && RewardTransaction::where('reward_rule_id', $rule->id)->count() >= $rule->maximum_rewards) {
            return false;
        }

        return $rule->maximum_per_user === null || RewardTransaction::where('reward_rule_id', $rule->id)->where('beneficiary_user_id', $beneficiary->id)->count() < $rule->maximum_per_user;
    }
}
