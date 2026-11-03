<?php

namespace App\Policies;

use App\Models\User;

class SupportSessionPolicy
{
    public function create(User $actor): bool
    {
        return $actor->is_admin && $actor->is_superadmin;
    }
}
