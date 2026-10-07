<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model {
    protected $guarded = [];
    protected static function booted(): void { static::creating(function (self $challenge): void {
        if (!$challenge->isDirty('ranking_visibility')) $challenge->ranking_visibility = $challenge->selection_type === 'TOP_N' ? 'PUBLIC' : 'NONE';
    }); }
    protected function casts(): array { return ['allowed_platforms'=>'array','required_hashtags'=>'array','required_mentions'=>'array','starts_at'=>'datetime','ends_at'=>'datetime','published_from'=>'datetime','published_until'=>'datetime','reward_jp_amount'=>'integer']; }
    public function getRouteKeyName(): string { return 'slug'; }
    public function participations(): HasMany { return $this->hasMany(ChallengeParticipation::class); }
    public function socialEntries(): HasMany { return $this->hasMany(ChallengeSocialEntry::class); }
    public function grants(): HasMany { return $this->hasMany(ChallengeRewardGrant::class); }
    public function benefit(): BelongsTo { return $this->belongsTo(Benefit::class); }
    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
    public function city(): BelongsTo { return $this->belongsTo(City::class); }
    public function isPublic(): bool { return $this->review_status === 'APPROVED' && in_array($this->status, ['open','closed'], true); }
    public function acceptsParticipation(): bool { return $this->isPublic() && $this->status === 'open' && (!$this->starts_at || $this->starts_at->lte(now())) && (!$this->ends_at || $this->ends_at->gt(now())); }
    public function rulesContract(): array { return $this->only(['description','instructions','evidence_type','participation_eligibility','qualification_type','qualification_metric','qualification_target','selection_type','selection_metric','winner_limit','reward_type','benefit_id','reward_jp_amount','manual_prize_description','reward_eligibility','review_mode','starts_at','ends_at','max_entries_per_user','allowed_platforms','required_hashtags','required_mentions','published_from','published_until']); }
}
