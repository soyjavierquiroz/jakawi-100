<?php

namespace App\Models;

use App\Enums\LandingMediaMode;
use App\Enums\LandingTemplate;
use App\Enums\MarketingTheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LandingPresentation extends Model
{
    protected $guarded = [];

    protected $casts = ['vsl' => 'array', 'template_key' => LandingTemplate::class, 'marketing_theme' => MarketingTheme::class, 'media_mode' => LandingMediaMode::class, 'editorial_version' => 'integer'];

    public const SCOPES = ['NONE', 'GUESTS', 'ALL'];

    protected $attributes = ['default_scope' => 'NONE', 'publish_scope' => 'NONE', 'editorial_version' => 0];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
