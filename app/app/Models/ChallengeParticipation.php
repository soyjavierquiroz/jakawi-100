<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChallengeParticipation extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['qualified_at'=>'datetime','selected_at'=>'datetime']; }
    public function challenge(): BelongsTo { return $this->belongsTo(Challenge::class); }
    public function socialEntries(): HasMany { return $this->hasMany(ChallengeSocialEntry::class, 'participation_id'); }
    public function qualifiedEntry(): BelongsTo { return $this->belongsTo(ChallengeSocialEntry::class, 'qualified_entry_id'); }
    public function selectedEntry(): BelongsTo { return $this->belongsTo(ChallengeSocialEntry::class, 'selected_entry_id'); }
    public function grant(): HasOne { return $this->hasOne(ChallengeRewardGrant::class, 'participation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
