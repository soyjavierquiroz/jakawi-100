<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipBankPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payment_confirmed_at' => 'datetime', 'reported_at' => 'datetime', 'extraction_json' => 'array', 'extraction_claimed_at' => 'datetime', 'extraction_processed_at' => 'datetime', 'extraction_attempts' => 'integer', 'checkout_at' => 'datetime', 'paid_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'activated_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function scopeReceiptPending(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNotNull('reported_at')->whereNull('receipt_path')->whereNull('bank_movement_id')->where('status', '!=', 'approved');
    }

    public function needsReceipt(): bool
    {
        return $this->reported_at !== null && $this->receipt_path === null && $this->bank_movement_id === null && $this->status !== 'approved';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
