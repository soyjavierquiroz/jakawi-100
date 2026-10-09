<?php

return [
    'enabled' => env('META_PROVIDER_ENABLED', false),
    'browser_enabled' => env('META_BROWSER_ENABLED', false),
    'capi_enabled' => env('META_CAPI_ENABLED', false),
    'pixel_id' => env('META_PIXEL_ID', ''),
    'access_token' => env('META_CAPI_ACCESS_TOKEN', ''),
    'api_version' => env('META_GRAPH_API_VERSION', ''),
    'test_event_code' => env('META_TEST_EVENT_CODE', ''),
];
