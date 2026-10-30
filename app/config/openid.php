<?php

return [
    'passport' => ['tokens_can' => [
        'openid' => 'Identidad JAKAWI',
        'profile' => 'Nombre',
        'email' => 'Correo y estado de verificación',
    ]],
    'custom_claim_sets' => [],
    'repositories' => ['identity' => \OpenIDConnect\Repositories\IdentityRepository::class],
    // Register the bounded COM routes explicitly, rather than the package defaults.
    'routes' => ['discovery' => false, 'jwks' => false, 'userinfo' => false],
    'signer' => \Lcobucci\JWT\Signer\Rsa\Sha256::class,
    'token_headers' => ['kid' => env('OIDC_KEY_ID')],
    'use_microseconds' => false,
    'issuedBy' => env('OIDC_ISSUER', env('APP_URL', 'https://jakawi.com')),
    'forceHttps' => true,
];
