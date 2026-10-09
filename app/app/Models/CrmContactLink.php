<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmContactLink extends Model
{
    protected $guarded = [];

    protected $hidden = ['last_known_email'];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }
}
