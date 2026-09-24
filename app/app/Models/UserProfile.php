<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property array<int, string>|null $interests
 * @property array<int, string>|null $social_contexts
 * @property array<int, string>|null $preferred_days
 * @property array<int, string>|null $preferred_times
 * @property Carbon|null $profile_completed_at
 */
#[Fillable(['avatar_path', 'city', 'interests', 'social_contexts', 'preferred_days', 'preferred_times', 'profile_completed_at'])]
class UserProfile extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completionPercentage(): int
    {
        $hasValues = fn (?array $values): bool => $values !== null && count($values) > 0;

        return ($hasValues($this->interests) ? 40 : 0)
            + ($hasValues($this->social_contexts) ? 20 : 0)
            + ($hasValues($this->preferred_days) ? 20 : 0)
            + ($hasValues($this->preferred_times) ? 20 : 0);
    }

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'social_contexts' => 'array',
            'preferred_days' => 'array',
            'preferred_times' => 'array',
            'profile_completed_at' => 'datetime',
        ];
    }
}
