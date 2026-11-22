<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalGift extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['token_hash'];

    protected static function booted(): void
    {
        static::saving(fn () => app(\App\Services\DigitalGifts::class)->assertEnabled());
    }

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'claimed_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
