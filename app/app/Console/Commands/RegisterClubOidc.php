<?php

namespace App\Console\Commands;

use App\Models\OidcClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterClubOidc extends Command
{
    protected $signature = 'oidc:register-club';

    protected $description = 'Register the exclusively configured CLUB OIDC client without printing its secret';

    public function handle(): int
    {
        $id = config('oidc.club_client_id');
        $uri = config('oidc.club_redirect_uri');
        $secret = config('oidc.club_client_secret');
        $parts = is_string($uri) ? parse_url($uri) : false;
        if (! is_string($id) || ! Str::isUuid($id) || ! is_string($secret) || strlen($secret) < 48
            || ! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') !== 'jakawi.club' || ($parts['path'] ?? '') === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            $this->error('Invalid private CLUB OIDC configuration.');

            return self::FAILURE;
        }

        $client = OidcClient::query()->find($id);
        if ($client !== null) {
            if ($client->revoked || $client->redirect_uris !== [$uri]
                || $client->grant_types !== ['authorization_code'] || $client->provider !== 'users'
                || ! Hash::check($secret, $client->secret)) {
                $this->error('Existing client differs. Explicit rotation is required.');

                return self::FAILURE;
            }
        } else {
            OidcClient::query()->forceCreate([
                'id' => $id, 'name' => 'JAKAWI CLUB', 'secret' => $secret,
                'provider' => 'users', 'redirect_uris' => [$uri],
                'grant_types' => ['authorization_code'], 'revoked' => false,
            ]);
        }

        $this->info('CLUB OIDC client registered and verified. Secret omitted.');

        return self::SUCCESS;
    }
}
