<?php

namespace App\Models;

use Database\Factories\ExperienceSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceSession extends Model
{
    /** @use HasFactory<ExperienceSessionFactory> */
    use HasFactory;

    protected $fillable = ['experience_id', 'location_id', 'starts_at', 'ends_at', 'capacity', 'status', 'venue_label'];

    /** @return BelongsTo<Experience, $this> */
    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @param Builder<ExperienceSession> $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function isScheduled(): bool { return $this->status === 'scheduled'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isUpcoming(): bool { return $this->isScheduled() && $this->starts_at->gte(now()); }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
