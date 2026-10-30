<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

class OidcClient extends Client
{
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        // Explicitly registered first-party CLUB only; no dynamic registration.
        return ! $this->revoked && $this->confidential()
            && $this->getKey() === config('oidc.club_client_id')
            && $this->redirect_uris === [config('oidc.club_redirect_uri')];
    }
}
