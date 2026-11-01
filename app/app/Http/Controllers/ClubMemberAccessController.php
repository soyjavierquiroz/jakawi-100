<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ClubMemberAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClubMemberAccessController extends Controller
{
    public function issue(Request $request, string $reference, ClubMemberAccess $access)
    {
        $data = $request->validate(['club_user_uuid' => ['required', 'uuid', 'lowercase'],
            'claim_secret' => ['required', 'string', 'min:32', 'max:128'],
            'global_subject_id' => ['prohibited'], 'user_id' => ['prohibited'], 'redirect' => ['prohibited']]);

        return response()->json($access->issue($reference, $data['club_user_uuid'], $data['claim_secret']));
    }

    public function entry(Request $request)
    {
        abort_if($request->getQueryString() !== null, 400);

        return view('identity.club-access', ['entry' => true,
            'staged' => $request->session()->has('club_access_hash')]);
    }

    public function stage(Request $request, ClubMemberAccess $access)
    {
        // No validation exception: ticket must never be flashed into old input.
        $token = $request->input('ticket');
        abort_unless(is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token), 410, 'Vuelve a CLUB y solicita otro acceso.');
        $hash = hash('sha256', $token);
        $ticket = $access->inspect($hash);
        $request->session()->put('club_access_hash', $hash);

        if ($request->expectsJson()) {
            return response()->json(['different' => $request->user() && $request->user()->id !== $ticket->user_id,
                'destination' => $request->user()?->id === $ticket->user_id ? '/beneficios' : null]);
        }

        return redirect('/club/access/continue');
    }

    public function show(Request $request, ClubMemberAccess $access)
    {
        $ticket = $access->inspect((string) $request->session()->get('club_access_hash'));
        if ($request->user()?->id === $ticket->user_id) {
            return redirect('/beneficios');
        }

        return view('identity.club-access', ['entry' => false,
            'different' => $request->user() && $request->user()->id !== $ticket->user_id]);
    }

    public function consume(Request $request, ClubMemberAccess $access)
    {
        if ($request->input('action') === 'cancel') {
            $request->session()->forget('club_access_hash');

            return redirect('https://jakawi.club');
        }
        $hash = (string) $request->session()->get('club_access_hash');
        $user = $access->consume($hash, function (User $user) use ($request) {
            $different = $request->user() && $request->user()->id !== $user->id;
            abort_if($different && $request->input('switch_account') !== 'yes', 409,
                'Hay otra cuenta COM abierta. Confirma el cambio de cuenta o cancela.');
        });
        // Commit the single-use consumption before changing browser authentication.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect('/beneficios')->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
