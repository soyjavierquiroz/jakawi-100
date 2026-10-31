<?php

namespace App\Http\Controllers;

use App\Models\MembershipBankPayment;
use App\Models\Partner;
use App\Services\ClubPurchaseIdentity;
use App\Services\Commercial\ComOriginProof;
use App\Services\Commercial\MembershipAcquisition;
use App\Support\PartnerSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComOriginController extends Controller
{
    public function redirect(Request $request, Partner $partner, string $destination, MembershipAcquisition $origins, ComOriginProof $proof)
    {
        abort_unless($partner->isPublished() && PartnerSlug::valid($partner->slug), 404);
        $origins->capture($request, $partner);
        $candidate = $origins->candidate($request, $request->user());
        $token = $proof->issue($candidate);
        abort_if($candidate && ! $token, 503, 'El origen comercial no está disponible.');
        $query = $token ? ['com_origin' => $token] : [];
        if (isset($candidate['club_referral_code'])) {
            $query['club_ref'] = $candidate['club_referral_code'];
        }
        $path = $destination === 'checkout' ? '/activar' : '/membresia';

        return redirect()->away('https://jakawi.club'.$path.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function resolve(Request $request, ComOriginProof $proof, MembershipAcquisition $origins, ClubPurchaseIdentity $identity)
    {
        $data = $request->validate(['com_origin' => 'required|string|max:8192', 'club_user_uuid' => 'nullable|uuid|lowercase']);
        $candidate = $proof->read($data['com_origin']);
        // HMAC backend caller + an existing authorized identity link only. Never email.
        $candidate = DB::transaction(function () use ($data, $candidate, $origins, $identity) {
            if (! empty($data['club_user_uuid'])) {
                $user = $identity->resolve(new MembershipBankPayment(['club_user_uuid' => $data['club_user_uuid']]));
                if ($user && ($original = $origins->original($user))) {
                    return $original;
                }
            }

            return $candidate;
        });

        return response()->json(['contract' => 'jakawi-com-origin-v1', 'source' => 'jakawi.com',
            'acquisition' => $candidate, 'beneficiary_resolution' => 'CLUB'])->header('Cache-Control', 'private, no-store');
    }
}
