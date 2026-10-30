<?php

namespace App\Services\Identity;

use Laravel\Passport\Bridge\RefreshTokenRepository;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

class NoOidcRefreshTokens extends RefreshTokenRepository
{
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return null;
    }
}
