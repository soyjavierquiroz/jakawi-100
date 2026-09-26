<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AttributionTouch extends Model { protected $guarded = []; protected function casts(): array { return ['occurred_at' => 'datetime', 'metadata' => 'array']; } public function user(): BelongsTo { return $this->belongsTo(User::class); } public function referrer(): BelongsTo { return $this->belongsTo(User::class, 'referrer_user_id'); } }
