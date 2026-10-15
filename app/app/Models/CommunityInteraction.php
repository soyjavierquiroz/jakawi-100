<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface|null $last_invited_at
 * @property CarbonInterface|null $last_opened_at
 * @property CarbonInterface|null $last_snoozed_at
 */
class CommunityInteraction extends Model
{
    protected $guarded = [];

    protected $hidden = ['last_session_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['exposure_count' => 'integer', 'last_invited_at' => 'datetime', 'last_opened_at' => 'datetime', 'last_snoozed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
