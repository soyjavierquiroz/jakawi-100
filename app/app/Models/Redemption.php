<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'public_id',
    'code',
    'user_id',
    'membership_id',
    'merchant_id',
    'benefit_id',
    'merchant_name',
    'benefit_title',
    'status',
    'savings_amount',
    'expires_at',
    'confirmed_at',
])]
class Redemption extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected static function booted(): void
    {
        static::creating(function (Redemption $redemption) {
            $redemption->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @param Builder<Redemption> $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', self::STATUS_CONFIRMED);
    }

    /** @param Builder<Redemption> $query */
    public function scopePendingValid(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING)->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lte(now());
    }

    /** @return BelongsTo<User, Redemption> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Membership, Redemption> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<Merchant, Redemption> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<Benefit, Redemption> */
    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    protected function casts(): array
    {
        return [
            'savings_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
