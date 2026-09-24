<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminContentReviewController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/review', ['benefits' => Benefit::with(['partner','submittedBy'])->where('review_status', 'submitted')->get(), 'experiences' => Experience::with(['partners','submittedBy'])->where('review_status', 'submitted')->get()]);
    }

    public function benefit(Request $request, Benefit $benefit)
    {
        $data = $request->validate(['action' => 'required|in:approve,changes,reject', 'notes' => 'nullable|string']);
        abort_if(in_array($data['action'], ['changes','reject'], true) && blank($data['notes']), 422);
        abort_unless($benefit->review_status === 'submitted', 409);
        DB::transaction(function () use ($benefit, $request, $data) {
            $fields = ['review_status' => match ($data['action']) {'approve' => 'approved', 'changes' => 'changes_requested', default => 'rejected'}, 'reviewed_at' => now(), 'reviewed_by_user_id' => $request->user()->id, 'review_notes' => $data['notes'] ?? null];
            if ($data['action'] === 'approve') $fields += ['status' => 'published', 'published_at' => now()];
            $benefit->update($fields);
        });
        return back();
    }

    public function experience(Request $request, Experience $experience)
    {
        $data = $request->validate(['action' => 'required|in:approve,changes,reject', 'notes' => 'nullable|string']);
        abort_if(in_array($data['action'], ['changes','reject'], true) && blank($data['notes']), 422);
        abort_unless($experience->review_status === 'submitted', 409);
        DB::transaction(function () use ($experience, $request, $data) {
            $fields = ['review_status' => match ($data['action']) {'approve' => 'approved', 'changes' => 'changes_requested', default => 'rejected'}, 'reviewed_at' => now(), 'reviewed_by_user_id' => $request->user()->id, 'review_notes' => $data['notes'] ?? null];
            if ($data['action'] === 'approve') $fields += ['status' => 'published', 'published_at' => now()];
            $experience->update($fields);
        });
        return back();
    }
}
