<?php

namespace App\Services\Crm;

final class CrmConfiguration
{
    public function enabled(): bool
    {
        return config('crm.enabled') === true && config('crm.provider') === 'fluentcrm';
    }

    public function ready(): bool
    {
        $url = parse_url((string) config('crm.bridge_url'));

        return $this->enabled() && is_array($url) && ($url['scheme'] ?? '') === 'https' && ! empty($url['host']) && ! isset($url['user']) && ! isset($url['pass']) && ! isset($url['query']) && ! isset($url['fragment'])
            && ($url['path'] ?? '') === '/wp-json/jakawi-fluentcrm/v1/events' && strlen((string) config('crm.secret')) >= 32;
    }
}
