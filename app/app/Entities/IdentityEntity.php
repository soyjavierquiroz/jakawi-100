<?php

namespace App\Entities;

use App\Models\User;
use OpenIDConnect\Entities\IdentityEntity as BaseIdentityEntity;

class IdentityEntity extends BaseIdentityEntity
{
    private User $user;

    public function setIdentifier($identifier): void
    {
        $this->user = User::query()->findOrFail($identifier);
        // Only the existing authority issues subjects. Local numeric PKs stay local.
        $this->identifier = $this->user->global_subject_id;
    }

    public function getClaims(array $scopes = []): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'email_verified' => $this->user->email_verified_at !== null,
        ];
    }
}
