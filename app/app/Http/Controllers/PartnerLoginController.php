<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\LoginRequest;

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
        $request->session()->put('partner_login', true);

        return $sessions->store($request);
    }
}
