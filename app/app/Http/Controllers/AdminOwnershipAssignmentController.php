<?php

namespace App\Http\Controllers;

use App\Models\OwnershipAssignment;
use App\Models\PartnerApplication;
use App\Models\ProgramApplication;
use App\Models\User;
use App\Services\OwnershipAssignmentService;
use Illuminate\Http\Request;

class AdminOwnershipAssignmentController extends Controller
{
    public function save(Request $request, string $targetType, int $targetId, OwnershipAssignmentService $ownership)
    {
        $target = $this->target($targetType, $targetId);
        $data = $request->validate(['owner_user_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'max:2000']]);
        $ownership->reassign($target, User::findOrFail($data['owner_user_id']), $request->user(), $data['reason']);
        return back();
    }

    public function remove(Request $request, string $targetType, int $targetId, OwnershipAssignmentService $ownership)
    {
        $target = $this->target($targetType, $targetId);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $ownership->unassign($target, $request->user(), $data['reason']);
        return back();
    }

    private function target(string $type, int $id): User|PartnerApplication|ProgramApplication
    {
        return match ($type) {
            OwnershipAssignment::TARGET_USER => User::findOrFail($id),
            OwnershipAssignment::TARGET_PARTNER_APPLICATION => PartnerApplication::findOrFail($id),
            OwnershipAssignment::TARGET_PROGRAM_APPLICATION => ProgramApplication::findOrFail($id),
            default => abort(404),
        };
    }
}
