<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->session()->pull('partner_login')) {
            $partners = $request->user()?->partners()->orderBy('name')->get(['partners.id', 'partners.slug']);
            if ($partners === null || $partners->isEmpty()) {
                auth()->logout();

                return redirect()->route('partner.login')->with('status', 'Tu cuenta no tiene acceso al Portal Partner.');
            }

            return redirect()->intended($partners->count() === 1
                ? route('partner.portal.show', $partners->first())
                : route('partner.index'));
        }

        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->intended($this->homePath($request));
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
