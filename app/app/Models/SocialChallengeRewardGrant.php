<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class SocialChallengeRewardGrant extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['snapshot'=>'array','granted_at'=>'datetime','fulfilled_at'=>'datetime','cancelled_at'=>'datetime','jp_amount'=>'integer']; }
    public function participation(): BelongsTo { return $this->belongsTo(SocialChallengeParticipation::class,'participation_id'); }
    public function benefit(): BelongsTo { return $this->belongsTo(Benefit::class); }
    public function jpCredit(): HasOne { return $this->hasOne(RewardTransaction::class, 'social_challenge_reward_grant_id'); }
}
