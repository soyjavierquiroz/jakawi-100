<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LandingPresentation extends Model
{
    protected $guarded = [];
    public const SCOPES = ['NONE', 'GUESTS', 'ALL'];
    protected $attributes = ['default_scope' => 'NONE'];
    public function subject(): MorphTo { return $this->morphTo(); }
    public function getRouteKeyName(): string { return 'slug'; }
}
