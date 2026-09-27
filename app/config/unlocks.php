<?php

return [
    // Slice 3 must enable this explicitly in production once confirmation/forfeiture exists.
    'jp_commitments_enabled' => env('UNLOCK_JP_COMMITMENTS_ENABLED', env('APP_ENV') !== 'production'),
];
