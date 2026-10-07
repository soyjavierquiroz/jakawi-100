<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SocialChallengeParticipation extends Model {
    protected $guarded = [];
    protected static function booted(): void { static::updating(function (self $participation): void { if ($participation->isDirty('social_url') || $participation->isDirty('normalized_url_hash') || $participation->isDirty('external_reference')) throw new \DomainException('Submitted social publication identity is immutable.'); }); }
    protected function casts(): array { return ['sharecontest_payload'=>'array','published_at'=>'datetime','checked_at'=>'datetime','final_checked_at'=>'datetime','last_refresh_requested_at'=>'datetime','refresh_count_date'=>'date','refresh_pending'=>'boolean']; }
    public function challenge(): BelongsTo { return $this->belongsTo(SocialChallenge::class,'social_challenge_id'); }
    public function grant(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(SocialChallengeRewardGrant::class,'participation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
