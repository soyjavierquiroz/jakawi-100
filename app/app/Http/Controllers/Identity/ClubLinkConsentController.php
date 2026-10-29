<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Services\Identity\IdentityAuthority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClubLinkConsentController extends Controller
{
    public function show(Request $request, string $handoff, IdentityAuthority $authority)
    {
        $pending = $authority->getHandoff($handoff);
        abort_unless($pending->target_user_id === $request->user()->id, 403, 'Inicia sesión con la cuenta COM correspondiente a esta solicitud.');
        abort_unless(in_array($pending->status, ['AUTH_REQUIRED', 'LINKED'], true), 409);
        abort_if($pending->status === 'AUTH_REQUIRED' && now()->greaterThan($pending->expires_at), 410, 'La solicitud caducó. Iníciala de nuevo desde CLUB.');

        return response()->view('identity.club-link', ['handoff' => $handoff,
            'linked' => $pending->status === 'LINKED', 'fresh' => $this->fresh($request), 'email' => $request->user()->email])
            ->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Frame-Options' => 'DENY', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function store(Request $request, string $handoff, IdentityAuthority $authority)
    {
        abort_unless($this->fresh($request), 403, 'Vuelve a autenticarte en COM antes de autorizar el vínculo.');
        $request->validate(['authorize_link' => ['required', 'accepted']]);
        $result = $authority->authorize($handoff, $request->user(), (int) $request->session()->get('identity.authenticated_at'));
        abort_unless($result['status'] === 'LINKED', 409, 'El vínculo tiene un conflicto y requiere revisión.');

        return redirect()->route('identity.club.consent', ['handoff' => $handoff]);
    }

    public function reauthenticate(Request $request, string $handoff, IdentityAuthority $authority)
    {
        $pending = $authority->getHandoff($handoff);
        abort_unless($pending->status === 'AUTH_REQUIRED' && now()->lessThan($pending->expires_at), 410);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', route('identity.club.consent', ['handoff' => $handoff]));

        return redirect()->route('login');
    }

    private function fresh(Request $request): bool
    {
        $stamp = $request->session()->get('identity.authenticated_at');

        return $request->session()->get('identity.authenticated_user_id') === $request->user()->id
            && is_int($stamp) && $stamp >= time() - config('identity.fresh_auth_seconds') && $stamp <= time() + 30;
    }
}
