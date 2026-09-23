<?php

namespace App\Models;

use Database\Factories\BenefitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class Benefit extends Model
{
    /** @use HasFactory<BenefitFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'partner_id', 'slug', 'title', 'short_description', 'description', 'terms', 'category',
        'benefit_type', 'estimated_savings', 'redemption_limit_per_member', 'status', 'featured',
        'starts_at', 'ends_at', 'applies_to_all_locations', 'image_path', 'sort_order', 'published_at',
    ];

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsToMany<Location, $this> */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    /** @param Builder<Benefit> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /** @param Builder<Benefit> $query */
    public function scopeAvailable(Builder $query): void
    {
        $query->published()
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->whereHas('partner', fn (Builder $query) => $query->published());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isAvailable(): bool
    {
        return $this->isPublished()
            && $this->partner?->isPublished() === true
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gte(now()));
    }

    public function isAvailableAt(Location $location): bool
    {
        if (! $this->isAvailable() || ! $location->isPublished() || $location->partner_id !== $this->partner_id) {
            return false;
        }

        return $this->applies_to_all_locations
            || $this->locations()->whereKey($location->getKey())->exists();
    }

    /** @return Builder<Location> */
    public function availableLocations(): Builder
    {
        $locations = Location::query()->published()->where('partner_id', $this->partner_id);

        if (! $this->applies_to_all_locations) {
            $locations->whereIn('locations.id', $this->locations()->select('locations.id'));
        }

        return $locations;
    }

    /** @param iterable<int|Location> $locations */
    public function syncLocations(iterable $locations): void
    {
        if ($this->applies_to_all_locations) {
            $this->locations()->sync([]);

            return;
        }

        $ids = Collection::make($locations)->map(function (int|Location $location): int {
            if ($location instanceof Location) {
                return $location->getKey();
            }

            if (! is_int($location) && ! ctype_digit((string) $location)) {
                throw new InvalidArgumentException('Locations must be IDs or Location models.');
            }

            return (int) $location;
        })->unique()->values();

        $models = Location::query()->whereIn('id', $ids)->get();

        if ($models->count() !== $ids->count() || $models->contains(fn (Location $location) => $location->partner_id !== $this->partner_id)) {
            throw new InvalidArgumentException('Benefit locations must belong to the same partner.');
        }

        $this->locations()->sync($ids->all());
    }

    public function applyToAllLocations(): void
    {
        $this->forceFill(['applies_to_all_locations' => true])->save();
        $this->locations()->sync([]);
    }

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'applies_to_all_locations' => 'boolean',
            'estimated_savings' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
