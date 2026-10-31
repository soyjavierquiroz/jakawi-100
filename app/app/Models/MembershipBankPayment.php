<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipBankPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['extraction_json' => 'array', 'extraction_claimed_at' => 'datetime', 'extraction_processed_at' => 'datetime', 'extraction_attempts' => 'integer', 'checkout_at' => 'datetime', 'paid_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'activated_at' => 'datetime', 'amount' => 'decimal:2'];
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
