<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportSession extends Model
{
    public ?string $displayLabel = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }
}
