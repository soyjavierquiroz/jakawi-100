<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    public const COMING_SOON = 'COMING_SOON';

    public const UNLOCKING = 'UNLOCKING';

    public const PREPARING = 'PREPARING';

    public const ACTIVE = 'ACTIVE';

    public const PAUSED = 'PAUSED';

    protected $fillable = ['name', 'slug', 'region', 'country_code', 'status', 'priority'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::COMING_SOON, self::UNLOCKING, self::PREPARING, self::ACTIVE, self::PAUSED];
    }

    /** @return HasMany<CityInterest, $this> */
    public function interests(): HasMany
    {
        return $this->hasMany(CityInterest::class);
    }

    /** @return HasMany<PartnerApplication, $this> */
    public function partnerApplications(): HasMany
    {
        return $this->hasMany(PartnerApplication::class);
    }
}
