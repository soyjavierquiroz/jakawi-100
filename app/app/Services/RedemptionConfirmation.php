<?php

namespace App\Services;

use App\Models\Redemption;

final readonly class RedemptionConfirmation
{
    public function __construct(public Redemption $redemption, public bool $replayed) {}
}
