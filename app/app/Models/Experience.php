<?php

namespace App\Models;

use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class Experience extends Model
{
    /** @use HasFactory<ExperienceFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'slug', 'title', 'short_description', 'description', 'terms', 'category', 'experience_type',
        'duration_minutes', 'regular_price', 'member_price', 'currency', 'reservation_method',
        'reservation_url', 'reservation_whatsapp', 'reservation_phone', 'status', 'featured',
        'image_path', 'cover_path', 'sort_order', 'published_at', 'review_status', 'submitted_at', 'submitted_by_user_id', 'reviewed_at', 'reviewed_by_user_id', 'review_notes', 'created_by_user_id',
    ];

    /** @return BelongsToMany<Partner, $this> */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class)
            ->withPivot(['role', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** @return HasMany<ExperienceSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ExperienceSession::class);
    }

    /** @return HasMany<ExperienceReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(ExperienceReservation::class);
    }

    /** @return HasMany<ExperienceSession, $this> */
    public function upcomingSessions(): HasMany
    {
        return $this->sessions()->upcoming();
    }

    /** @param Builder<Experience> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /** @param Builder<Experience> $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->published()->whereHas('sessions', fn (Builder $query) => $query->upcoming());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Replace partner-role assignments. A partner may legitimately appear once per role.
     *
     * @param  iterable<array{partner_id:int|numeric-string,role:string,sort_order?:int}>  $assignments
     */
    public function syncPartnersWithRoles(iterable $assignments): void
    {
        $assignments = Collection::make($assignments)->map(function (mixed $assignment): array {
            if (! is_array($assignment) || ! isset($assignment['partner_id'], $assignment['role'])) {
                throw new InvalidArgumentException('Each partner assignment requires partner_id and role.');
            }

            return [
                'partner_id' => (int) $assignment['partner_id'],
                'role' => $assignment['role'],
                'sort_order' => (int) ($assignment['sort_order'] ?? 0),
            ];
        })->values();

        $ids = $assignments->pluck('partner_id')->unique()->values();
        if (Partner::query()->whereIn('id', $ids)->count() !== $ids->count()) {
            throw new InvalidArgumentException('Each experience partner must exist.');
        }

        $seen = [];
        foreach ($assignments as $assignment) {
            if (! in_array($assignment['role'], config('jakawi.experience_partner_roles'), true)) {
                throw new InvalidArgumentException('Experience partner role is not allowed.');
            }
            $key = $assignment['partner_id'].'|'.$assignment['role'];
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate experience partner role assignment.');
            }
            $seen[$key] = true;
        }

        $this->partners()->detach();
        foreach ($assignments as $assignment) {
            $this->partners()->attach($assignment['partner_id'], [
                'role' => $assignment['role'],
                'sort_order' => $assignment['sort_order'],
            ]);
        }
    }

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'regular_price' => 'decimal:2',
            'member_price' => 'decimal:2',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime',
        ];
    }
}
