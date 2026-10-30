<?php

namespace App\Services\Identity;

use Laravel\Passport\Bridge\ClientRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class OidcClientRepository extends ClientRepository
{
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        return $clientIdentifier === config('oidc.club_client_id')
            ? parent::getClientEntity($clientIdentifier) : null;
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        return $clientIdentifier === config('oidc.club_client_id')
            && $grantType === 'authorization_code'
            && parent::validateClient($clientIdentifier, $clientSecret, $grantType);
    }
}
