<?php

namespace App\Http\Controllers;

use App\Services\Commercial\MembershipEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubAcquisitionEvidenceController
{
    public function __invoke(Request $request, MembershipEvidence $evidence): JsonResponse
    {
        $data = $request->validate([
            'purchase_reference' => ['required', 'string', 'max:100'],
            'affiliate_global_subject_id' => ['nullable', 'uuid'],
        ]);

        return DB::transaction(function () use ($data, $evidence): JsonResponse {
            // HMAC nonce was already committed by the middleware. Domain reads share one snapshot.
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }

            return response()->json($evidence->resolve($data['purchase_reference'], isset($data['affiliate_global_subject_id']) ? strtolower($data['affiliate_global_subject_id']) : null));
        });
    }
}
