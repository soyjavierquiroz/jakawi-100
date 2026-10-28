<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AppSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['value' => 'array']; }

    public static function showDemoBanner(): bool
    {
        // Missing setting starts OFF; this controls presentation, never commercial policy.
        return (static::where('key', 'show_demo_banner')->first()?->value['enabled'] ?? false) === true;
    }
}
