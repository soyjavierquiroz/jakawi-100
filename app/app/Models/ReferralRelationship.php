<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReferralRelationship extends Model { public const STATUS_ACTIVE = 'active'; public const STATUS_INVALID = 'invalid'; protected $guarded = []; protected function casts(): array { return ['attributed_at' => 'datetime', 'expires_at' => 'datetime']; } public function referrer(): BelongsTo { return $this->belongsTo(User::class, 'referrer_user_id'); } public function referred(): BelongsTo { return $this->belongsTo(User::class, 'referred_user_id'); } public function attributionTouch(): BelongsTo { return $this->belongsTo(AttributionTouch::class, 'attribution_touch_id'); } public function isValid(): bool { return $this->status === self::STATUS_ACTIVE && ($this->expires_at === null || $this->expires_at->isFuture()); } }
