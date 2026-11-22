<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalProduct extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['file_path', 'file_sha256'];

    protected function casts(): array
    {
        return ['public_price' => 'decimal:2', 'member_price' => 'decimal:2', 'gallery_paths' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function landingPresentations(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(LandingPresentation::class, 'subject');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')->whereHas('partner', fn ($p) => $p->published());
    }
}
