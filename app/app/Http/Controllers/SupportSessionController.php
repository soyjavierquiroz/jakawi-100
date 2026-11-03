<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ValidateOidcAuthorization;
use App\Models\Partner;
use App\Models\SupportSession;
use App\Models\User;
use App\Services\Support\SupportAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class SupportSessionController extends Controller
{
    public function index(Request $request, SupportAccess $access)
    {
        abort_unless($access->allows($request->user()), 403);
        $data = $request->validate(['q' => 'nullable|string|max:200', 'user' => 'nullable|integer', 'partner' => 'nullable|integer', 'subject' => 'nullable|uuid']);
        $query = User::query()->where(fn ($q) => $q->where('is_admin', false)->orWhereKey($request->user()->id))->where('email', 'not like', '%@purchases.invalid');
        if (! empty($data['partner'])) {
            $partner = Partner::findOrFail($data['partner']);
            $query->whereHas('partners', fn ($q) => $q->whereKey($partner->id)->whereIn('partner_user.role', ['owner', 'manager', 'staff']));
        }
        if (! empty($data['user'])) {
            $query->whereKey($data['user']);
        }
        if (! empty($data['subject'])) {
            $query->where('global_subject_id', $data['subject']);
        }
        if ($q = trim($data['q'] ?? '')) {
            $query->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($q, '\\%_').'%')->orWhere('email', 'ilike', '%'.addcslashes($q, '\\%_').'%'));
        }

        return Inertia::render('admin/support', [
            'users' => $query->orderBy('name')->paginate(25)->withQueryString()->through(fn ($u) => $u->only('id', 'name', 'email')),
            'partner' => isset($partner) ? $partner->only('id', 'name') : null,
            'fresh' => $access->fresh($request),
            'mfa' => $request->user()->hasEnabledTwoFactorAuthentication(),
        ]);
    }

    public function reauthenticate(Request $request, SupportAccess $access)
    {
        abort_unless($access->allows($request->user()), 403);
        $data = $request->validate(['password' => 'required|string|max:1000', 'code' => 'nullable|string|max:20']);
        $actor = $request->user();
        $valid = Hash::check($data['password'], $actor->password);
        if ($actor->hasEnabledTwoFactorAuthentication()) {
            $valid = $valid && app(TwoFactorAuthenticationProvider::class)->verify(Crypt::decryptString($actor->two_factor_secret), $data['code'] ?? '');
        }
        if (! $valid) {
            throw ValidationException::withMessages(['password' => 'Verifica tu contraseña y el código MFA vigente.']);
        }
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();
        $request->session()->put('support.reauthenticated_at', time());
        $request->session()->put('support.reauthenticated_user_id', $actor->id);

        return back();
    }

    public function store(Request $request, SupportAccess $access)
    {
        abort_unless($access->allows($request->user()) && $access->fresh($request), 403, 'Reautentica tu cuenta administrativa para iniciar soporte.');
        abort_if($request->session()->has('support_session_id'), 409);
        $data = $request->validate(['user_id' => 'required|integer', 'partner_id' => 'nullable|integer', 'reason' => 'required|string|min:10|max:1000']);
        $target = User::findOrFail($data['user_id']);
        abort_if((($target->is_admin || $target->is_superadmin) && $target->id !== $request->user()->id) || str_ends_with(strtolower($target->email), '@purchases.invalid'), 403);
        $partner = ! empty($data['partner_id']) ? Partner::findOrFail($data['partner_id']) : null;
        if ($partner) {
            abort_unless($target->partners()->whereKey($partner->id)->whereIn('partner_user.role', ['owner', 'manager', 'staff'])->exists(), 403);
        }
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();
        $support = new SupportSession;
        $support->id = (string) Str::uuid();
        $support->fill(['actor_user_id' => $request->user()->id, 'target_user_id' => $target->id, 'partner_id' => $partner?->id,
            'reason' => $data['reason'], 'session_hash' => hash('sha256', $request->session()->getId()),
            'started_at' => now(), 'expires_at' => now()->addMinutes(15), 'ended_at' => now()->addMinutes(15), 'end_reason' => 'expired']);
        $support->save();
        $request->session()->forget('support.oidc_continuations');
        $request->session()->put('support_session_id', $support->id);
        $request->session()->forget(['support.reauthenticated_at', 'support.reauthenticated_user_id']);

        return $partner ? to_route('partner.portal.show', $partner) : to_route('mi-jakawi');
    }

    public function destroy(Request $request)
    {
        $this->end($request, 'exit');

        return to_route('admin.index');
    }

    public function club(Request $request)
    {
        abort_unless(app(SupportAccess::class)->allows($request->user()), 403);
        $support = $request->attributes->get('support_session');
        abort_unless($support, 409);
        $subject = User::findOrFail($support->target_user_id)->global_subject_id;
        abort_unless(Str::isUuid($subject), 409);
        $this->end($request, 'club');
        $url = 'https://jakawi.club/admin/soporte?'.http_build_query(['subject' => $subject], '', '&', PHP_QUERY_RFC3986);

        return $request->header('X-Inertia') ? Inertia::location($url) : redirect()->away($url, 303);
    }

    public function continueClub(Request $request)
    {
        abort_unless(app(SupportAccess::class)->allows($request->user()), 403);
        $data = $request->validate(['continuation_id' => 'required|uuid']);
        $pending = $request->session()->get('support.oidc_continuations.'.$data['continuation_id']);
        $support = $request->attributes->get('support_session');
        abort_unless($support && is_array($pending) && $pending['support_id'] === $support->id
            && $pending['actor_id'] === $request->user()->id && $pending['expires_at'] >= now()->timestamp, 409);
        abort_if(app(ValidateOidcAuthorization::class)->queryError($pending['query']) !== null, 400);
        $url = '/oauth/authorize?'.http_build_query($pending['query'], '', '&', PHP_QUERY_RFC3986);
        $this->end($request, 'oidc_resume');

        return redirect($url, 303);
    }

    private function end(Request $request, string $reason): void
    {
        // SupportMode verifies the real actor, deadline and session binding before these POSTs.
        $support = $request->attributes->get('support_session');
        abort_unless($support, 409);
        $support->update(['ended_at' => now(), 'end_reason' => $reason]);
        $request->session()->forget(['support_session_id', 'support.oidc_continuations']);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();
    }
}
