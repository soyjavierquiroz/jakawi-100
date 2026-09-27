<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JpHold extends Model
{
    public const HELD = 'HELD';
    public const RELEASED = 'RELEASED';
    public const FORFEITED = 'FORFEITED';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['held_at' => 'datetime', 'released_at' => 'datetime', 'forfeited_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function participation(): BelongsTo { return $this->belongsTo(UnlockParticipation::class, 'unlock_participation_id'); }
}
