<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redemption extends Model
{
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['public_id', 'code', 'user_id', 'membership_id', 'partner_id', 'location_id', 'benefit_id', 'partner_name', 'location_name', 'benefit_title', 'status', 'savings_amount', 'expires_at', 'confirmed_at'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Benefit, $this> */
    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isExpired(): bool
    {
        return $this->isPending() && $this->expires_at->lt(now());
    }

    protected function casts(): array
    {
        return ['savings_amount' => 'decimal:2', 'expires_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
