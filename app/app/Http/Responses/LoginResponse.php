<?php

namespace App\Http\Responses;

use App\Support\ConversionIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $paywallPath = app(ConversionIntent::class)->paywallPath($request);
        if ($paywallPath !== null) {
            return $request->wantsJson() ? response()->noContent() : redirect()->to($paywallPath);
        }

        if ($request->session()->pull('partner_login')) {
            $partners = $request->user()?->partners()->orderBy('name')->get(['partners.id', 'partners.slug']);
            if ($partners === null || $partners->isEmpty()) {
                auth()->logout();

                return redirect()->route('partner.login')->with('status', 'Tu cuenta no tiene acceso al Portal Partner.');
            }

            return redirect()->to($this->intendedOrFallback($request, $partners->count() === 1
                ? route('partner.portal.show', $partners->first())
                : route('partner.index'), true));
        }

        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->to($this->intendedOrFallback($request, $this->homePath($request)));
    }

    private function intendedOrFallback(Request $request, string $fallback, bool $partnerOnly = false): string
    {
        $intended = $request->session()->pull('url.intended');
        if (! is_string($intended) || ! $this->canAccessIntended($request, $intended, $partnerOnly)) {
            return $fallback;
        }

        return $intended;
    }

    private function canAccessIntended(Request $request, string $intended, bool $partnerOnly): bool
    {
        $path = parse_url($intended, PHP_URL_PATH);
        if (! is_string($path) || ! Str::startsWith($path, '/')) {
            return false;
        }

        $user = $request->user();
        if ($partnerOnly) {
            if (! Str::startsWith($path, '/partner')) {
                return false;
            }
            $slug = Str::after($path, '/partner/');

            return $slug === $path || $slug === '' || ! Str::contains($slug, '/') || $user->partners()->where('slug', Str::before($slug, '/'))->exists();
        }

        if ($path === '/admin' || Str::startsWith($path, '/admin/')) {
            return (bool) $user?->is_admin;
        }
        if ($path === '/mi-jakawi') {
            return ! $user?->isPartnerOnly();
        }
        if (Str::startsWith($path, '/partner')) {
            return $user?->partners()->exists() && $this->canAccessIntended($request, $intended, true);
        }

        return false;
    }

    private function homePath(Request $request): string
    {
        $user = $request->user();

        if ($user?->is_admin) {
            return '/admin';
        }

        if ($user?->partners()->where('status', 'published')->exists()) {
            return '/partner';
        }

        return $user ? '/mi-jakawi' : '/';
    }
}
