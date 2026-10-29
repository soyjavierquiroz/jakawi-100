<?php

return [
    'issuer' => 'https://jakawi.com',
    'club_key_id' => env('IDENTITY_CLUB_KEY_ID', 'club-identity-v1'),
    'club_secret' => env('IDENTITY_CLUB_SECRET', ''),
    'club_email_proof_secret' => env('IDENTITY_CLUB_EMAIL_PROOF_SECRET', ''),
    'clock_skew_seconds' => 300,
    'handoff_ttl_seconds' => 900,
    'fresh_auth_seconds' => 300,
];
