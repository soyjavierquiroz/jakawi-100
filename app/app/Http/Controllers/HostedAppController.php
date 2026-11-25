<?php
namespace App\Http\Controllers;
use App\Models\{DigitalAccessGrant, DigitalProduct};
use App\Services\HostedApps;
use Illuminate\Http\Request;

class HostedAppController extends Controller
{
    public function open(Request $request, DigitalAccessGrant $access, HostedApps $apps)
    {
        abort_unless($access->user_id === $request->user()->id, 403);
        $token = $apps->issue($access);
        $entry = $apps->registry()[DigitalProduct::findOrFail($access->digital_product_id)->app_key]['entry'];
        return response()->view('digital-app-pass', compact('entry', 'token'))->withHeaders([
            'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'strict-origin',
            'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; form-action https://jakawi.store; frame-ancestors 'none'; base-uri 'none'",
        ]);
    }

    public function exchange(Request $request, HostedApps $apps)
    {
        $secret = config('hosted_apps.secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32 && hash_equals($secret, $request->bearerToken() ?? ''), 401);
        $data = $request->validate(['action' => 'required|in:consume,check', 'app' => ['required', \Illuminate\Validation\Rule::in(array_keys($apps->registry()))], 'token' => 'required_if:action,consume|string|max:64', 'credential' => 'required_if:action,check|string|max:4096']);
        $result = $data['action'] === 'consume' ? $apps->consume($data['token'], $data['app']) : ['authorized' => $apps->check($data['credential'], $data['app'])];
        return response()->json($result)->header('Cache-Control', 'no-store');
    }
}
