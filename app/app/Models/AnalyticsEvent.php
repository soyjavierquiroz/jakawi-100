<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->event_id ??= (string) \Illuminate\Support\Str::uuid();
        });
    }


    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return BelongsTo<Experience, $this> */
    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function unlock(): BelongsTo
    {
        return $this->belongsTo(Unlock::class);
    }

    /** @return BelongsTo<Redemption, $this> */
    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemption::class);
    }

    public function attributionTouch(): BelongsTo
    {
        return $this->belongsTo(AttributionTouch::class);
    }

    public function landingPresentation(): BelongsTo
    {
        return $this->belongsTo(LandingPresentation::class);
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }
}
