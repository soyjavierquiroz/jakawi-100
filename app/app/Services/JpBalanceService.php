<?php

namespace App\Services;

use App\Models\JpHold;
use App\Models\RewardTransaction;
use App\Models\User;

class JpBalanceService
{
    /** @return array{earned:int,held:int,spendable:int} */
    public function for(User|int $user): array
    {
        $id = $user instanceof User ? $user->id : $user;
        $earned = (int) RewardTransaction::query()->where('beneficiary_user_id', $id)->where('reward_type', 'JP')->where('status', RewardTransaction::STATUS_AVAILABLE)->sum('amount');
        $held = (int) JpHold::query()->where('user_id', $id)->where('status', JpHold::HELD)->sum('amount');

        return ['earned' => $earned, 'held' => $held, 'spendable' => $earned - $held];
    }
}
