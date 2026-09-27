<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalAdjustment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'before' => 'array', 'after' => 'array'];
    }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
