<?php

namespace App\Models;

use App\Enums\AcquisitionProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthProviderDelivery extends Model
{
    public const SERVER = 'SERVER';
    public const PENDING = 'PENDING';
    public const PROCESSING = 'PROCESSING';
    public const SENT = 'SENT';
    public const RETRY = 'RETRY';
    public const DEAD = 'DEAD';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['provider' => AcquisitionProvider::class, 'attempts' => 'integer',
            'next_attempt_at' => 'datetime', 'last_attempt_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function analyticsEvent(): BelongsTo
    {
        return $this->belongsTo(AnalyticsEvent::class);
    }
}
