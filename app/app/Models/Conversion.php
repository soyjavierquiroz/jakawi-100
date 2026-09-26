<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Conversion extends Model { protected $guarded = []; protected function casts(): array { return ['gross_amount' => 'decimal:2', 'eligible_amount' => 'decimal:2', 'occurred_at' => 'datetime', 'attribution_snapshot' => 'array']; } public function user(): BelongsTo { return $this->belongsTo(User::class); } public function relationship(): BelongsTo { return $this->belongsTo(ReferralRelationship::class, 'referral_relationship_id'); } public function purchase(): BelongsTo { return $this->belongsTo(MembershipPurchase::class, 'id', 'conversion_id'); } }
