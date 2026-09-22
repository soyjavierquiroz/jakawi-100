<?php

namespace App\Models;

use Database\Factories\BenefitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'merchant_id',
    'title',
    'slug',
    'short_description',
    'description',
    'terms',
    'benefit_type',
    'estimated_savings',
    'image_path',
    'is_active',
    'is_featured',
    'redemption_limit_per_member',
    'starts_at',
    'ends_at',
    'sort_order',
])]
class Benefit extends Model
{
    /** @use HasFactory<BenefitFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Benefit $benefit) {
            if (! $benefit->slug) {
                $benefit->slug = static::uniqueSlug($benefit->title, $benefit->id);
            }
        });
    }

    /** @return BelongsTo<Merchant, Benefit> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @param Builder<Benefit> $query */
    public function scopeAvailable(Builder $query): void
    {
        $query
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->whereHas('merchant', fn (Builder $query) => $query->active());
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'benefit';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'estimated_savings' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'redemption_limit_per_member' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
