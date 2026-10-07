<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SocialChallenge extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['allowed_platforms'=>'array','required_hashtags'=>'array','required_mentions'=>'array','starts_at'=>'datetime','ends_at'=>'datetime','published_from'=>'datetime','published_until'=>'datetime']; }
    public function getRouteKeyName(): string { return 'slug'; }
    public function participations(): HasMany { return $this->hasMany(SocialChallengeParticipation::class); }
    public function benefit(): BelongsTo { return $this->belongsTo(Benefit::class); }
    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
    public function acceptsParticipation(): bool { return $this->status === 'open' && (!$this->starts_at || $this->starts_at->lte(now())) && (!$this->ends_at || $this->ends_at->gt(now())); }
}
