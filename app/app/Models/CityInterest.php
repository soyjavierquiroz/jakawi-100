<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityInterest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attribution_snapshot' => 'array'];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AttributionTouch, $this> */
    public function attributionTouch(): BelongsTo
    {
        return $this->belongsTo(AttributionTouch::class);
    }
}
