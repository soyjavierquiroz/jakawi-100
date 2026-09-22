<?php

namespace App\Models;

use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

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
