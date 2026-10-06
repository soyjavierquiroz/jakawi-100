<?php

namespace App\Http\Responses;

use App\Services\PublicJourneyContinuation;
use App\Services\AnalyticsTracker;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json('', 201);
        }

        $continuation = app(PublicJourneyContinuation::class);
        $intent = $continuation->consume();
        if ($intent !== null && ($destination = $continuation->destination($intent)) !== null) {
            $request->session()->forget('url.intended');

            try {
                app(AnalyticsTracker::class)->journeyAuthReturned($intent, 'register');
            } catch (\Throwable) {
                \Illuminate\Support\Facades\Log::warning('Journey auth return analytics failed.', ['method' => 'register']);
            }

            return redirect()->to($destination);
        }

        $intended = $request->session()->get('url.intended');
        if (is_string($intended) && $this->validIntended($request, $intended)) {
            $request->session()->forget('url.intended');

            return redirect()->to($intended);
        }

        $request->session()->forget('url.intended');

        return redirect()->to(Fortify::redirects('register'));
    }

    private function validIntended(Request $request, string $url): bool
    {
        if (str_starts_with($url, '//') || str_contains($url, '\\')) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['path']) || ! str_starts_with($parts['path'], '/')) {
            return false;
        }

        if (isset($parts['host']) && (strcasecmp($parts['host'], $request->getHost()) !== 0
            || ($parts['scheme'] ?? '') !== $request->getScheme()
            || ($parts['port'] ?? $request->getPort()) !== $request->getPort()
            || isset($parts['user']) || isset($parts['pass']))) {
            return false;
        }

        return $parts['path'] === '/mi-jakawi'
            || preg_match('#^/experiencias/[a-zA-Z0-9_-]+$#D', $parts['path']) === 1
            || preg_match('#^/d/[a-zA-Z0-9_-]+$#D', $parts['path']) === 1;
    }
}
