<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmDelivery extends Model
{
    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'next_attempt_at' => 'datetime', 'last_attempt_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
