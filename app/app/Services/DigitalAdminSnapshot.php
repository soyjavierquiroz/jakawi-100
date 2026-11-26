<?php
namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/** Read-only, request-local inputs for catalog classification. Never used by mutations. */
class DigitalAdminSnapshot
{
    public bool $active = false;
    public array $settings = [];
    public array $health = [];
    public array $integrity = [];

    public function start(array $appKeys): void
    {
        $this->settings = AppSetting::where(function ($q) {
            $q->where('key', 'like', 'digital_%')->orWhereIn('key', [DigitalSaleAvailability::KEY, 'hosted_app_catalog']);
        })->pluck('value', 'key')->all();
        $this->active = true;
        $cache = Cache::store('database');
        $keys = array_values(array_unique(array_filter($appKeys)));
        $this->health = $cache->many(array_map(fn ($key) => 'hosted-app-health:'.$key, $keys));
        $last = $cache->many(array_map(fn ($key) => 'hosted-app-admin-health:'.$key, $keys));
        foreach ($keys as $key) {
            if (!isset($this->health['hosted-app-health:'.$key])) $this->health['hosted-app-health:'.$key] = $last['hosted-app-admin-health:'.$key]['ready'] ?? false;
        }
    }

    public function setting(string $key): array
    {
        return $this->active ? ($this->settings[$key] ?? []) : (AppSetting::where('key', $key)->first()?->value ?? []);
    }
}
