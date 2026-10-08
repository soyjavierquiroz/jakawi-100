<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LandingPresentation extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_default' => 'boolean']; }
    public function subject(): MorphTo { return $this->morphTo(); }
    public function getRouteKeyName(): string { return 'slug'; }
}
