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
        return $this->enabled() && $this->validEndpoint()
            && strlen((string) config('crm.secret')) >= 32;
    }

    public function validEndpoint(): bool
    {
        // Exact wire URLs avoid parser ambiguity, duplicate parameters and
        // encoded route/host variants. Never normalize the fallback to wp-json.
        return in_array(config('crm.bridge_url'), [
            'https://crm.jakawi.com/wp-json/jakawi-fluentcrm/v1/events',
            'https://crm.jakawi.com/?rest_route=/jakawi-fluentcrm/v1/events',
        ], true);
    }
}
