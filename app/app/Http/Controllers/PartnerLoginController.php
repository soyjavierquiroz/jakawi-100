<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\LoginRequest;
use Laravel\Fortify\Features;

class PartnerLoginController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'partnerPortal' => true,
        ]);
    }

    public function store(LoginRequest $request, AuthenticatedSessionController $sessions)
    {
        // Keep this on the request instead of the session: a failed partner
        // login must not change the destination of a later normal login.
        $request->merge(['partner_login' => true]);

        return $sessions->store($request);
    }
}
