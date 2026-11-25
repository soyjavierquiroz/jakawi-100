<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalProduct extends Model
{
    protected static function booted(): void
    {
        static::updated(function (self $product): void {
            $tiers = [];
            if ($product->wasChanged(['public_price','currency'])) $tiers[] = 'public';
            if ($product->wasChanged(['member_price','currency'])) $tiers[] = 'member';
            if ($tiers) app(\App\Services\DigitalProductQr::class)->invalidate($product, $tiers);
        });
    }

    protected $guarded = ['id'];

    protected $hidden = ['file_path', 'file_sha256', 'gift_pdf_sha256'];

    protected function casts(): array
    {
        return ['welcome_gift_enabled' => 'boolean', 'promotional_gift_enabled' => 'boolean', 'public_price' => 'decimal:2', 'member_price' => 'decimal:2', 'gallery_paths' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function landingPresentations(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(LandingPresentation::class, 'subject');
    }

    /** Editorial availability is separate from catalog publication and prices. */
    public function isComingSoon(): bool
    {
        if (app(\App\Services\DigitalSaleAvailability::class)->canSell($this)) return false;
        return $this->landingPresentations()->where('status', 'PUBLISHED')
            ->whereJsonContains('editorial_blocks', [['type' => 'hero', 'availability' => 'COMING_SOON']])->exists();
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
