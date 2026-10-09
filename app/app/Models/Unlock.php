<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Unlock extends Model {
    public const DRAFT='DRAFT', PENDING_REVIEW='PENDING_REVIEW', APPROVED='APPROVED', SCHEDULED='SCHEDULED', ACTIVE='ACTIVE', GOAL_REACHED='GOAL_REACHED', UNLOCKED='UNLOCKED', FULFILLMENT_ACTIVE='FULFILLMENT_ACTIVE', COMPLETED='COMPLETED', GOAL_NOT_REACHED='GOAL_NOT_REACHED', CANCELLED='CANCELLED', REJECTED='REJECTED';
    protected $guarded=[];
    protected function casts(): array { return ['featured'=>'boolean','secret_mode'=>'boolean','hide_partner_until_unlock'=>'boolean','hide_exact_offer_until_unlock'=>'boolean','hide_location_until_unlock'=>'boolean','free_user_eligible'=>'boolean','member_eligible'=>'boolean','starts_at'=>'datetime','commitment_deadline'=>'datetime','confirmation_deadline'=>'datetime','fulfillment_starts_at'=>'datetime','fulfillment_ends_at'=>'datetime','cancellation_deadline'=>'datetime','goal_reached_at'=>'datetime','status_changed_at'=>'datetime']; }
    public function getRouteKeyName(): string { return 'slug'; }
    public function landingPresentations(): \Illuminate\Database\Eloquent\Relations\MorphMany { return $this->morphMany(LandingPresentation::class, 'subject'); }
    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
    public function locations(): BelongsToMany { return $this->belongsToMany(Location::class, 'unlock_location'); }
    public function participations(): HasMany { return $this->hasMany(UnlockParticipation::class); }
    public function statusHistory(): HasMany { return $this->hasMany(UnlockStatusHistory::class); }
    public function benefit(): BelongsTo { return $this->belongsTo(Benefit::class, 'linked_benefit_id'); }
    public function experience(): BelongsTo { return $this->belongsTo(Experience::class, 'linked_experience_id'); }
    public function committedCount(): int { return $this->participations()->whereIn('status', [UnlockParticipation::COMMITTED, UnlockParticipation::UNLOCKED_PENDING_CONFIRMATION, UnlockParticipation::CONFIRMED, UnlockParticipation::FULFILLED, UnlockParticipation::NO_SHOW])->count(); }
    public function revealable(): bool { return in_array($this->status, [self::GOAL_REACHED, self::UNLOCKED, self::FULFILLMENT_ACTIVE, self::COMPLETED], true) || $this->goal_reached_at !== null; }
}
