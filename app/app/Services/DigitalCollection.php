<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

class DigitalCollection
{
    public const KEY = 'digital_collection_v1';

    public function current(): ?array
    {
        $v = AppSetting::where('key', self::KEY)->first()?->value;
        if (! is_array($v) || ! ($v['enabled'] ?? false) || ! ($v['exclusive_account'] ?? false) || ! ($v['variable_amount_qr'] ?? false)) {
            return null;
        }
        foreach (['bank', 'account', 'holder', 'currency', 'qr_path', 'qr_sha256', 'version', 'account_key'] as $key) {
            if (empty($v[$key])) {
                return null;
            }
        }
        try {
            $disk = Storage::disk('digital-private');
            if (! $disk->exists($v['qr_path']) || ! hash_equals($v['qr_sha256'], hash('sha256', $disk->get($v['qr_path'])))) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $v;
    }

    public function readyFor(string $currency): bool
    {
        return ($this->current()['currency'] ?? null) === $currency;
    }

    public function settings(): ?array
    {
        return AppSetting::where('key', self::KEY)->first()?->value;
    }
}
