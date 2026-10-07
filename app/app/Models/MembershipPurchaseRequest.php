<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPurchaseRequest extends Model
{
    public const REQUESTED = 'REQUESTED';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'journey_context' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attributionTouch(): BelongsTo { return $this->belongsTo(AttributionTouch::class); }
    public function purchase(): BelongsTo { return $this->belongsTo(MembershipPurchase::class, 'membership_purchase_id'); }

    public function intent(): ?array
    {
        if (! $this->journey_type || ! $this->journey_action || ! $this->journey_resource_id) {
            return null;
        }
        return [
            'journey' => $this->journey_type,
            'action' => $this->journey_action,
            'resource_id' => (int) $this->journey_resource_id,
            ...($this->journey_context ? ['context' => $this->journey_context] : []),
        ];
    }
}
