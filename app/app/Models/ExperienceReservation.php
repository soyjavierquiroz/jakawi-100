<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExperienceReservation extends Model
{
    use HasFactory;

    public const MAX_PARTY_SIZE = 10;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['public_id', 'check_in_code', 'user_id', 'experience_id', 'experience_session_id', 'partner_id', 'status', 'party_size', 'responded_at', 'responded_by_user_id', 'cancelled_at', 'checked_in_at', 'checked_in_by_user_id'];

    protected static function booted(): void
    {
        static::creating(function (self $reservation): void {
            $reservation->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Experience, $this> */
    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    /** @return BelongsTo<ExperienceSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExperienceSession::class, 'experience_session_id');
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }

    protected function casts(): array
    {
        return ['party_size' => 'integer', 'responded_at' => 'datetime', 'cancelled_at' => 'datetime', 'checked_in_at' => 'datetime'];
    }
}
