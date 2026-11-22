<?php

namespace App\Services;

use App\Models\AppSetting;

class DigitalCollection
{
    public const KEY = 'digital_collection_v1';

    public function current(): ?array
    {
        $v = AppSetting::where('key', self::KEY)->first()?->value;
        if (! is_array($v) || ! ($v['enabled'] ?? false) || ! ($v['exclusive_account'] ?? false)) {
            return null;
        }
        foreach (['bank', 'account', 'holder', 'currency', 'version', 'account_key'] as $key) {
            if (empty($v[$key])) {
                return null;
            }
        }
        unset($v['qr_path'], $v['qr_sha256'], $v['variable_amount_qr']);

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
