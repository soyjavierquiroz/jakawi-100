<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MembershipStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubMembershipStatusController
{
    public function __invoke(Request $request, MembershipStatus $status): JsonResponse
    {
        $data = $request->validate(['global_subject_id' => ['required', 'uuid']]);

        return DB::transaction(function () use ($data, $status): JsonResponse {
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }
            $user = User::where('global_subject_id', strtolower($data['global_subject_id']))->firstOrFail();

            return response()->json([
                'contract' => 'jakawi-com-membership-status-v1',
                'global_subject_id' => $user->global_subject_id,
                'membership' => $status->for($user),
            ])->header('Cache-Control', 'private, no-store');
        });
    }
}
