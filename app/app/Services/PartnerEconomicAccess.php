<?php

namespace App\Services;

class PartnerEconomicAccess
{
    public function allows(string $status, string $role, bool $explicitPermission): bool
    {
        return $status === 'published'
            && ($role === 'owner' || ($role === 'manager' && $explicitPermission));
    }
}
