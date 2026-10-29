<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A full local login (password + any configured 2FA, or a passkey) is required.
        // Remember-cookie restoration alone never authorizes identity linking.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && request()->hasSession() && ! Auth::guard('web')->viaRemember()) {
                request()->session()->put('identity.authenticated_at', time());
                request()->session()->put('identity.authenticated_user_id', $event->user->getAuthIdentifier());
            }
        });
        RateLimiter::for('club-identity', fn () => Limit::perMinute(120)->by('club-identity'));
    }
}
