<?php

namespace App\Models;

use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'slug', 'name', 'entity_type', 'partner_type', 'legal_name', 'tax_id',
        'description', 'category', 'website', 'instagram', 'facebook', 'tiktok',
        'phone', 'whatsapp', 'email', 'contact_name', 'contact_phone', 'contact_email',
        'logo_path', 'cover_path', 'status', 'featured', 'internal_notes', 'sort_order', 'published_at',
    ];

    /** @return HasMany<Location, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /** @return HasMany<Benefit, $this> */
    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class);
    }

    /** @return BelongsToMany<Experience, $this> */
    public function experiences(): BelongsToMany
    {
        return $this->belongsToMany(Experience::class)
            ->withPivot(['role', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    /** @param Builder<Partner> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'published_at' => 'datetime'];
    }
}
