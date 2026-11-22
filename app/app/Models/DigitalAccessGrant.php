<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalAccessGrant extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'delivered_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DigitalOrder::class, 'digital_order_id');
    }
}
