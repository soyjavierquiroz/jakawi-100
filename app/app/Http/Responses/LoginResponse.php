<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->boolean('partner_login')) {
            if (! $request->user()?->is_admin && ! $request->user()?->partners()->where('status', 'published')->exists()) {
                auth()->logout();

                return redirect()->route('partner.login')->with('status', 'Tu cuenta no tiene acceso al Portal Partner.');
            }

            return redirect()->intended('/partner');
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
