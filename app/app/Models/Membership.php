<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'status',
    'type',
    'starts_at',
    'ends_at',
    'amount_paid',
    'payment_method',
    'payment_reference',
    'activated_by',
    'notes',
])]
class Membership extends Model
{
    public const TYPE_STANDARD = 'standard';

    public const TYPE_PIONEER = 'pioneer';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    /** @param Builder<Membership> $query */
    public function scopeActive(Builder $query): void
    {
        $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->unexpired();
    }

    /** Original ends_at remains historical; all entitlement checks use this policy. */
    public static function pioneerEndsAt(): ?\Carbon\CarbonImmutable
    {
        $launch = config('jakawi.membership.official_launch_date');

        if ($launch === null || $launch === '') {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $launch)) {
            throw new \InvalidArgumentException('Official launch date must be YYYY-MM-DD in Bolivia.');
        }
        $date = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $launch, 'America/La_Paz');
        if ($date->format('Y-m-d') !== $launch) {
            throw new \InvalidArgumentException('Official launch date must be a valid date.');
        }

        return $date->addMonthsNoOverflow(15)->endOfDay()->utc();
    }

    public function effectiveEndsAt(): ?\Carbon\CarbonInterface
    {
        return $this->type === self::TYPE_PIONEER ? self::pioneerEndsAt() : $this->ends_at;
    }

    public function scopeUnexpired(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where(fn (Builder $standard) => $standard->where('type', self::TYPE_STANDARD)->where('ends_at', '>=', now()))
                ->orWhere(function (Builder $pioneer): void {
                    $pioneer->where('type', self::TYPE_PIONEER);
                    if (($end = self::pioneerEndsAt()) && $end->lt(now())) {
                        $pioneer->whereRaw('1 = 0');
                    }
                });
        });
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where(fn (Builder $standard) => $standard->where('type', self::TYPE_STANDARD)->where('ends_at', '<', now()));
            if (($end = self::pioneerEndsAt()) && $end->lt(now())) {
                $q->orWhere('type', self::TYPE_PIONEER);
            }
        });
    }

    /** @return BelongsTo<User, Membership> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, Membership> */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /** @return HasMany<MembershipPurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(MembershipPurchase::class);
    }

    public function bankPayments(): HasMany
    {
        return $this->hasMany(MembershipBankPayment::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->starts_at->lte(now())
            && (($end = $this->effectiveEndsAt()) === null || $end->gte(now()));
    }

    /** @return HasMany<Redemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    /** @return HasMany<Redemption, $this> */
    public function confirmedRedemptions(): HasMany
    {
        return $this->redemptions()->where('status', Redemption::STATUS_CONFIRMED);
    }

    public function confirmedSavings(): string
    {
        return $this->fromCents($this->toCents((string) $this->confirmedRedemptions()->sum('savings_amount')));
    }

    public function remainingToPayback(): string
    {
        return $this->fromCents(max($this->toCents($this->amount_paid), $this->toCents($this->confirmedSavings())) - $this->toCents($this->confirmedSavings()));
    }

    public function hasPaidForItself(): bool
    {
        return $this->toCents($this->confirmedSavings()) >= $this->toCents($this->amount_paid);
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'amount_paid' => 'decimal:2',
        ];
    }
}
