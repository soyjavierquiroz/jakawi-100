<?php

return [
    'enabled' => env('CRM_SYNC_ENABLED', false),
    'provider' => env('CRM_PROVIDER', 'fluentcrm'),
    'bridge_url' => env('CRM_BRIDGE_URL', ''),
    'secret' => env('CRM_BRIDGE_SECRET', ''),
    'connect_timeout' => env('CRM_CONNECT_TIMEOUT', 3),
    'timeout' => env('CRM_TIMEOUT', 8),
];
