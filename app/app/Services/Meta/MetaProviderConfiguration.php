<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Log;

class MetaProviderConfiguration
{
    public function browserReady(): bool
    {
        return $this->ready('browser', ['pixel_id']);
    }

    public function serverReady(): bool
    {
        return $this->ready('capi', ['pixel_id', 'access_token', 'api_version']);
    }

    private function ready(string $channel, array $required): bool
    {
        if (! config('jakawi.analytics.enabled') || ! config('meta.enabled') || ! config("meta.{$channel}_enabled")) return false;
        foreach ($required as $field) {
            $value = config("meta.{$field}");
            $valid = is_string($value) && trim($value) !== '';
            if ($field === 'pixel_id') $valid = $valid && preg_match('/^[0-9]+$/D', $value);
            if ($field === 'api_version') $valid = $valid && preg_match('/^v[0-9]+\.0$/D', $value);
            if (! $valid) {
                Log::warning('Meta channel disabled by incomplete configuration.', ['channel' => $channel, 'field' => $field]);
                return false;
            }
        }
        return true;
    }
}
