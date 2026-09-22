<?php

namespace App\Models;

use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'short_description',
    'description',
    'category',
    'address',
    'city',
    'instagram',
    'whatsapp',
    'logo_path',
    'cover_path',
    'is_active',
    'is_featured',
    'sort_order',
])]
#[Hidden(['redemption_pin_hash'])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Merchant $merchant) {
            if (! $merchant->slug) {
                $merchant->slug = static::uniqueSlug($merchant->name, $merchant->id);
            }
        });
    }

    /** @return HasMany<Benefit> */
    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class);
    }

    /** @param Builder<Merchant> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'merchant';
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
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }
}
