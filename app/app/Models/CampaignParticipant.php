<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignParticipant extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
}
