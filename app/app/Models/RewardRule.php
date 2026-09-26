<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRule extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const TYPE_CASH = 'CASH';
    protected $guarded = [];
    protected function casts(): array { return ['value' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function beneficiary(): BelongsTo { return $this->belongsTo(User::class, 'beneficiary_user_id'); }
}
