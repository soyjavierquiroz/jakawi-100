<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRule extends Model
{
    public const BENEFICIARY_USER = 'USER';
    public const BENEFICIARY_PARTNER = 'PARTNER';
    public const STATUS_ACTIVE = 'active';

    public const TYPE_CASH = 'CASH';

    public const TYPE_JP = 'JP';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }

}
