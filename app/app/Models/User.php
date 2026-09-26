<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'theme_preference'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return HasMany<Membership> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasOne<Membership> */
    public function activeMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->active()->latest('starts_at');
    }

    /** @return HasOne<UserProfile> */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function hasActiveMembership(): bool
    {
        return $this->activeMembership()->exists();
    }

    /** @return HasMany<Redemption> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    /** @return HasMany<AttributionTouch> */
    public function attributionTouches(): HasMany { return $this->hasMany(AttributionTouch::class); }

    /** @return HasMany<ReferralRelationship> */
    public function referralRelationships(): HasMany { return $this->hasMany(ReferralRelationship::class, 'referred_user_id'); }

    /** @return HasMany<Conversion> */
    public function conversions(): HasMany { return $this->hasMany(Conversion::class); }

    /** @return HasMany<ExperienceReservation> */
    public function experienceReservations(): HasMany
    {
        return $this->hasMany(ExperienceReservation::class);
    }

    /** @return HasMany<Redemption> */
    public function confirmedRedemptions(): HasMany
    {
        return $this->redemptions()->where('status', Redemption::STATUS_CONFIRMED);
    }

    public function confirmedSavings(): string
    {
        return (string) $this->confirmedRedemptions()->sum('savings_amount');
    }

    /** @return BelongsToMany<Partner, $this> */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function managesPartner(?int $partnerId): bool
    {
        return $partnerId !== null && $this->partners()->whereKey($partnerId)->exists();
    }

    public function isPartnerOnly(): bool
    {
        return $this->partners()->exists() && ! $this->memberships()->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }
}
