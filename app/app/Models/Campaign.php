<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'end_at' => 'datetime'];
    }

    public function participants(): HasMany { return $this->hasMany(CampaignParticipant::class); }
    public function rules(): HasMany { return $this->hasMany(RewardRule::class); }
    public function conversions(): HasMany { return $this->hasMany(Conversion::class); }

    public function isActiveAt($at = null): bool
    {
        $at ??= now();
        return $this->status === self::STATUS_ACTIVE && ($this->start_at === null || $this->start_at->lte($at)) && ($this->end_at === null || $this->end_at->gte($at));
    }

    public function eligibleFor(string $participantType): bool { return $this->participants()->where('participant_type', $participantType)->exists(); }

    public function scopeOperationalAt(Builder $query, $at): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', $at))->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $at));
    }
}
