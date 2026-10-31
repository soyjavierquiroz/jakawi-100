<?php

namespace App\Http\Controllers;

use App\Services\PartnerEconomicAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubPartnerAccessController extends Controller
{
    public function __invoke(Request $request, PartnerEconomicAccess $access): JsonResponse
    {
        $data = $request->validate(['global_subject_id' => ['required', 'uuid']]);
        $subject = strtolower($data['global_subject_id']);
        // One live query: no email matching, cached user relations or OIDC claims.
        $partners = DB::table('partner_user')
            ->join('users', 'users.id', '=', 'partner_user.user_id')
            ->join('partners', 'partners.id', '=', 'partner_user.partner_id')
            ->where('users.global_subject_id', $subject)
            ->orderBy('partners.id')
            ->get(['partners.id', 'partners.name', 'partners.status', 'partner_user.role', 'partner_user.economic_access'])
            ->map(fn ($partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'status' => $partner->status,
                'role' => $partner->role,
                'economic_access' => $access->allows($partner->status, $partner->role, (bool) $partner->economic_access),
            ]);

        return response()->json([
            'global_subject_id' => $subject,
            'partners' => $partners,
        ])->header('Cache-Control', 'private, no-store');
    }
}
