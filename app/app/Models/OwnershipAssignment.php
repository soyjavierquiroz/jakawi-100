<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnershipAssignment extends Model
{
    public const TARGET_USER = 'USER';
    public const TARGET_PARTNER_APPLICATION = 'PARTNER_APPLICATION';
    public const TARGET_PROGRAM_APPLICATION = 'PROGRAM_APPLICATION';

    public const SOURCE_REFERRAL_RELATIONSHIP = 'REFERRAL_RELATIONSHIP';
    public const SOURCE_USER_OWNERSHIP = 'USER_OWNERSHIP';
    public const SOURCE_CONVERSION_REFERRAL = 'CONVERSION_REFERRAL';
    public const SOURCE_MANUAL = 'MANUAL';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
