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
        $this->health = app(HostedApps::class)->adminHealth($appKeys);
    }

    public function setting(string $key): array
    {
        return $this->active ? ($this->settings[$key] ?? []) : (AppSetting::where('key', $key)->first()?->value ?? []);
    }
}
