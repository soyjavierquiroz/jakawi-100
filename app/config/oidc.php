<?php

return [
    'issuer' => env('OIDC_ISSUER', env('APP_URL', 'https://jakawi.com')),
    'key_directory' => env('OIDC_KEY_DIRECTORY', storage_path('app/private/oidc')),
    'key_id' => env('OIDC_KEY_ID'),
    'club_client_id' => env('OIDC_CLUB_CLIENT_ID'),
    'club_redirect_uri' => env('OIDC_CLUB_REDIRECT_URI'),
    'club_client_secret' => env('OIDC_CLUB_CLIENT_SECRET'),
];
