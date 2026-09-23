<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'partner_id', 'slug', 'name', 'location_type', 'status', 'is_primary', 'country_code',
        'region', 'city', 'zone', 'address', 'address_reference', 'latitude', 'longitude',
        'maps_url', 'google_place_id', 'phone', 'whatsapp', 'email', 'website', 'instagram',
        'facebook', 'tiktok', 'timezone', 'opening_hours', 'manager_name', 'manager_phone',
        'manager_email', 'image_path', 'sort_order', 'published_at',
    ];

    protected $hidden = ['redemption_pin_hash'];

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsToMany<Benefit, $this> */
    public function benefits(): BelongsToMany
    {
        return $this->belongsToMany(Benefit::class);
    }

    /** @param Builder<Location> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function setRedemptionPin(string $pin): void
    {
        if (! preg_match('/^\\d{6}$/', $pin)) {
            throw new InvalidArgumentException('The redemption PIN must be exactly 6 digits.');
        }

        $this->redemption_pin_hash = Hash::make($pin);
    }

    public function hasRedemptionPin(): bool
    {
        return filled($this->redemption_pin_hash);
    }

    public function checkRedemptionPin(string $pin): bool
    {
        return $this->hasRedemptionPin() && Hash::check($pin, $this->redemption_pin_hash);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'opening_hours' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
