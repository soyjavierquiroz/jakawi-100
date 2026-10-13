<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Support\PartnerSlug;
use Illuminate\Http\Request;

class PartnerPublicController extends Controller
{
    public function redirect(Partner $partner)
    {
        abort_unless($partner->isPublished() && PartnerSlug::valid($partner->slug), 404);
        return redirect()->route('partners.public', $partner, 301);
    }

    public function authenticate(Request $request, Partner $partner, string $method)
    {
        abort_unless($partner->isPublished() && PartnerSlug::valid($partner->slug), 404);
        // Server-resolved profile, never a public numeric Partner parameter.
        $request->session()->put('partner_profile_return', $partner->id);
        app(\App\Services\PublicJourneyContinuation::class)->consume();
        return redirect()->route($method);
    }

    public static function consumeReturn(Request $request): ?string
    {
        $id = $request->session()->pull('partner_profile_return');
        $partner = is_int($id) ? Partner::published()->find($id) : null;
        return $partner && PartnerSlug::valid($partner->slug) ? route('partners.public', $partner, false) : null;
    }
}
