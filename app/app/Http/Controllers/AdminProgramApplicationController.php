<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ProgramApplication;
use App\Models\ProgramEnrollment;
use App\Models\User;
use App\Services\ReferralCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminProgramApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'program' => ['nullable', Rule::in(ProgramApplication::programTypes())],
            'status' => ['nullable', Rule::in(ProgramApplication::statuses())],
        ]);
        $query = ProgramApplication::with('user:id,name,email')->latest();
        if (! empty($filters['program'])) $query->where('program_type', $filters['program']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        return Inertia::render('admin/program-applications/index', [
            'applications' => $query->paginate(20)->withQueryString(), 'filters' => $filters,
            'programs' => ProgramApplication::programTypes(), 'statuses' => ProgramApplication::statuses(),
        ]);
    }

    public function show(ProgramApplication $programApplication): Response
    {
        return Inertia::render('admin/program-applications/show', [
            'application' => $programApplication->load('user:id,name,email', 'user.profile:user_id,whatsapp,city'),
            'statuses' => ProgramApplication::statuses(),
            'enrollment' => $programApplication->user->programEnrollments()->where('program_type', $programApplication->program_type)->latest()->first(),
        ]);
    }

    public function status(Request $request, ProgramApplication $programApplication, ReferralCodeService $codes)
    {
        $data = $request->validate(['status' => ['required', Rule::in(ProgramApplication::statuses())]]);
        DB::transaction(function () use ($request, $programApplication, $data, $codes): void {
            $application = ProgramApplication::whereKey($programApplication->id)->lockForUpdate()->firstOrFail();
            $before = $application->status;
            $next = $data['status'];
            if ($before === $next) return;
            abort_unless(in_array($before, ProgramApplication::openStatuses(), true), 422);
            if ($next === ProgramApplication::APPROVED) {
                $user = User::whereKey($application->user_id)->lockForUpdate()->firstOrFail();
                $enrollment = $user->programEnrollments()->where('program_type', $application->program_type)->latest()->first();
                if ($enrollment && ! $enrollment->isActive()) {
                    $enrollment->update(['status' => ProgramEnrollment::STATUS_ACTIVE, 'starts_at' => null, 'ends_at' => null]);
                } elseif (! $enrollment) {
                    $enrollment = $user->programEnrollments()->create(['program_type' => $application->program_type, 'status' => ProgramEnrollment::STATUS_ACTIVE]);
                }
                $codes->ensureFor($user);
                AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'program_enrollment_activated', 'subject_type' => ProgramEnrollment::class, 'subject_id' => $enrollment->id, 'metadata' => ['program_application_id' => $application->id]]);
            }
            $application->update(['status' => $next]);
            AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'program_application_status_changed', 'subject_type' => ProgramApplication::class, 'subject_id' => $application->id, 'metadata' => ['before' => ['status' => $before], 'after' => ['status' => $next]]]);
        });
        return back();
    }
}
