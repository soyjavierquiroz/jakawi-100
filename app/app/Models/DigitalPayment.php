<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalPayment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['receipt_path', 'receipt_sha256', 'statement_path', 'statement_sha256', 'bank_account_key'];

    protected function casts(): array
    {
        return ['received_amount' => 'decimal:2', 'refund_amount' => 'decimal:2', 'bank_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DigitalOrder::class, 'digital_order_id');
    }
}
