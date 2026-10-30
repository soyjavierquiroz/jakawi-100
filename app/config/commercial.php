<?php

return [
    'enabled' => (bool) env('CLUB_COMMERCIAL_ENABLED', false),
    'secret' => env('CLUB_COMMERCIAL_HMAC_SECRET'),
    // Deliberately fixed: this credential must never be sent to another host/path.
    'endpoint' => 'https://jakawi.club/internal/commercial/events',
    'key_id' => 'com-commercial-v1',
    'pilot_landing' => 'dossis-43-cafe-y-antojo',
    'pilot_partner' => 'dossis43',
];
