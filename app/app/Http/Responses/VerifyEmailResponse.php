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
            $continuation->consume();
            $request->session()->forget('url.intended');
            return redirect()->to($destination);
        }
        return (new \Laravel\Fortify\Http\Responses\VerifyEmailResponse)->toResponse($request);
    }
}
