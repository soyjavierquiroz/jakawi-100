<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeSocialEntry extends Model {
    protected $guarded = [];
    protected static function booted(): void { static::updating(function (self $entry): void { if ($entry->isDirty('social_url') || $entry->isDirty('normalized_url_hash') || $entry->isDirty('external_reference') || $entry->isDirty('participation_id') || $entry->isDirty('challenge_id')) throw new \DomainException('Submitted social publication identity is immutable.'); }); }
    protected function casts(): array { return ['sharecontest_payload'=>'array','published_at'=>'datetime','checked_at'=>'datetime','final_checked_at'=>'datetime','last_refresh_requested_at'=>'datetime','refresh_count_date'=>'date','refresh_pending'=>'boolean']; }
    public function challenge(): BelongsTo { return $this->belongsTo(Challenge::class); }
    public function participation(): BelongsTo { return $this->belongsTo(ChallengeParticipation::class); }
}
