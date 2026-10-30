<?php

namespace App\Http\Controllers\Identity;

use Illuminate\Http\JsonResponse;

class OidcDiscoveryController
{
    public function __invoke(): JsonResponse
    {
        $issuer = rtrim(config('oidc.issuer'), '/');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'jwks_uri' => $issuer.'/oauth/jwks',
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email'],
            'claims_supported' => ['iss', 'sub', 'aud', 'iat', 'exp', 'nonce', 'name', 'email', 'email_verified'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256'],
            'request_parameter_supported' => false,
            'request_uri_parameter_supported' => false,
            'claims_parameter_supported' => false,
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }
}
