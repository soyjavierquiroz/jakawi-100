<?php

namespace App\Providers;

use App\Models\OidcClient;
use App\Services\Identity\NoOidcRefreshTokens;
use App\Services\Identity\OidcClientRepository;
use DateInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response;
use OpenIDConnect\Grant\AuthCodeGrant;
use OpenIDConnect\Laravel\LaravelCurrentRequestService;
use OpenIDConnect\Laravel\PassportServiceProvider;

class OidcServiceProvider extends PassportServiceProvider
{
    public function register(): void
    {
        Passport::ignoreRoutes();
        parent::register();
        $this->app->bind(\Laravel\Passport\Bridge\ClientRepository::class, OidcClientRepository::class);
    }

    protected function registerAuthorizationServer(): void
    {
        // The upstream server performs all OAuth/PKCE/code validation and signing.
        // Enable exactly the grant offered to CLUB.
        $this->app->singleton(AuthorizationServer::class, function (): AuthorizationServer {
            $server = $this->makeAuthorizationServer();
            $server->enableGrantType(new AuthCodeGrant(
                $this->app->make(AuthCodeRepository::class),
                $this->app->make(NoOidcRefreshTokens::class),
                new DateInterval('PT2M'),
                new Response,
                $this->app->make(LaravelCurrentRequestService::class),
            ), new DateInterval('PT5M'));

            return $server;
        });
    }

    public function boot(): void
    {
        Passport::useClientModel(OidcClient::class);
        Passport::loadKeysFrom(config('oidc.key_directory'));
        Passport::tokensCan(config('openid.passport.tokens_can'));
        // Required by Passport's controller signature; first-party CLUB is preauthorized.
        Passport::authorizationView('oidc.unavailable');
        parent::boot();

        RateLimiter::for('oidc-authorize', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('oidc-token', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        $this->loadRoutesFrom(base_path('routes/oidc.php'));
    }
}
