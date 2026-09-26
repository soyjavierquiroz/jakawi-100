<?php

namespace App\Http\Responses;

use App\Support\ConversionIntent;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        $request = $request instanceof Request ? $request : request();
        $paywallPath = app(ConversionIntent::class)->paywallPath($request);

        return $request->wantsJson()
            ? response()->json('', 201)
            : redirect()->to($paywallPath ?? Fortify::redirects('register'));
    }
}
