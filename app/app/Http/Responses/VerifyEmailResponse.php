<?php

namespace App\Http\Responses;

use App\Services\PublicJourneyContinuation;
use Laravel\Fortify\Contracts\VerifyEmailResponse as Contract;

class VerifyEmailResponse implements Contract
{
    public function toResponse($request)
    {
        $continuation = app(PublicJourneyContinuation::class);
        $intent = $continuation->get();
        if (isset($intent['landing_presentation_id']) && ($destination = $continuation->destination($intent))) {
            if (($intent['journey'] ?? null) === 'SOCIAL_CHALLENGE') {
                $participation = \App\Models\ChallengeParticipation::where('challenge_id', $intent['resource_id'])->where('user_id', $request->user()->id)->first();
                if ($participation) {
                    app(\App\Services\ChallengeService::class)->evaluate($participation);
                    $destination = route('landing-presentations.result', \App\Models\LandingPresentation::findOrFail($intent['landing_presentation_id']), false);
                }
            }
            $continuation->consume();
            $request->session()->forget('url.intended');
            return redirect()->to($destination);
        }
        return (new \Laravel\Fortify\Http\Responses\VerifyEmailResponse)->toResponse($request);
    }
}
