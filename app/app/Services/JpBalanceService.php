<?php

namespace App\Services;

use App\Models\JpHold;
use App\Models\RewardTransaction;
use App\Models\User;

class JpBalanceService
{
    /** @return array{ledger_balance:int,held:int,available_balance:int} */
    public function for(User|int $user): array
    {
        $id = $user instanceof User ? $user->id : $user;
        $ledger = (int) RewardTransaction::query()->where('beneficiary_user_id', $id)->where('reward_type', 'JP')->where('status', RewardTransaction::STATUS_AVAILABLE)->sum('amount');
        $held = (int) JpHold::query()->where('user_id', $id)->where('status', JpHold::HELD)->sum('amount');

        return ['ledger_balance' => $ledger, 'held' => $held, 'available_balance' => max(0, $ledger - $held)];
    }
}
