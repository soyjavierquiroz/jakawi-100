<?php

namespace App\Http\Middleware;

use App\Models\Experience;
use App\Models\Unlock;
use App\Services\PublicJourneyContinuation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keep the existing mutation boundary, returning Landing users to their context. */
class EnsureContextualActionVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->hasVerifiedEmail()) {
            return $next($request);
        }

        $subject = $request->route('experience') ?? $request->route('unlock');
        if (! ($subject instanceof Experience) && ! ($subject instanceof Unlock)) {
            return $request->expectsJson() ? abort(403, 'Your email address is not verified.') : redirect()->guest(route('verification.notice'));
        }
        $continuation = app(PublicJourneyContinuation::class);
        $intent = $continuation->get();
        $landingId = $request->input('landing_presentation_id');
        if ($landingId === null && ($intent['resource_id'] ?? null) === $subject->id
            && ($intent['journey'] ?? null) === ($subject instanceof Experience ? 'EXPERIENCE' : 'UNLOCK')) {
            $landingId = $intent['landing_presentation_id'] ?? null;
        }
        if ($landingId === null) {
            return $request->expectsJson() ? abort(403, 'Your email address is not verified.') : redirect()->guest(route('verification.notice'));
        }
        $request->validate(['landing_presentation_id' => ['nullable', 'integer']]);
        $landing = $subject->landingPresentations()->whereKey($landingId)->where('status', 'PUBLISHED')->firstOrFail();
        $context = [];
        if ($subject instanceof Experience) {
            $data = $request->validate(['experience_session_id' => ['required', 'integer']]);
            abort_unless($subject->isPublished() && $subject->reservation_method === 'jakawi'
                && $subject->upcomingSessions()->whereKey($data['experience_session_id'])->exists(), 422);
            $context = ['experience_session_id' => (int) $data['experience_session_id']];
        }
        $continuation->set($subject instanceof Experience ? 'EXPERIENCE' : 'UNLOCK', $subject->id,
            $subject instanceof Experience ? 'RESERVE' : match ($request->route()->getName()) {
                'unlocks.interest' => 'INTEREST', 'unlocks.cancel' => 'CANCEL', 'unlocks.confirm' => 'CONFIRM', default => 'COMMIT',
            }, $context);
        $continuation->attachLanding($landing->id);
        // Generated GET only. Verification never replays a protected POST.
        return redirect()->to($continuation->destination($continuation->get()).'?verify=1');
    }
}
