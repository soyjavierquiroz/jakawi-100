<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerApplication extends Model
{
    public const SUBMITTED = 'SUBMITTED';

    public const CONTACTED = 'CONTACTED';

    public const QUALIFIED = 'QUALIFIED';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    protected $guarded = [];

    public static function statuses(): array
    {
        return [self::SUBMITTED, self::CONTACTED, self::QUALIFIED, self::APPROVED, self::REJECTED];
    }

    protected function casts(): array
    {
        return ['attribution_snapshot' => 'array', 'marketing_opt_in' => 'boolean', 'marketing_opt_in_at' => 'datetime'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attributionTouch(): BelongsTo
    {
        return $this->belongsTo(AttributionTouch::class);
    }
}
