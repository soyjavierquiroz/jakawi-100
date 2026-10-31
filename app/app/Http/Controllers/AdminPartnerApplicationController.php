<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\City;
use App\Models\PartnerApplication;
use App\Models\ProgramEnrollment;
use App\Models\User;
use App\Services\OwnershipAssignmentService;
use App\Services\PartnerOnboardingService;
use App\Support\ListingPage;
use App\Support\PartnerSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PartnerApplication::with('city:id,name,slug')->latest();
        if ($request->filled('city')) {
            $query->where('city_id', $request->integer('city'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return Inertia::render('admin/partner-applications/index', ['applications' => ListingPage::make($query, 25), 'cities' => City::orderBy('name')->get(['id', 'name']), 'statuses' => PartnerApplication::statuses(), 'filters' => $request->only('city', 'status')]);
    }

    public function show(Request $request, PartnerApplication $partnerApplication, OwnershipAssignmentService $ownership): Response
    {
        $assignment = $ownership->current($partnerApplication);
        $search = $request->validate(['owner_q' => 'nullable|string|max:150'])['owner_q'] ?? '';
        $candidates = User::query()->whereRaw('false');
        if (mb_strlen(trim($search)) >= 2) {
            $like = '%'.addcslashes(trim($search), '\\%_').'%';
            $candidates = User::query()->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like));
        }

        return Inertia::render('admin/partner-applications/show', [
            'application' => $partnerApplication->load('city:id,name,slug', 'user:id,name,email', 'partner:id,name,slug,status'),
            'partnerOwners' => $partnerApplication->partner?->users()->wherePivot('role', 'owner')->get(['users.id', 'users.name', 'users.email', 'users.email_verified_at']) ?? [],
            'accessCandidates' => $candidates->orderBy('name')->limit(20)->get(['id', 'name', 'email', 'email_verified_at']),
            'ownerSearch' => $search,
            'invitationEmailAvailable' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'statuses' => PartnerApplication::statuses(),
            'ownership' => $assignment?->load('owner:id,name'),
            'ownerCandidates' => User::query()->where(fn ($q) => $q->where('is_admin', true)
                ->orWhereHas('programEnrollments', fn ($enrollments) => $enrollments->active()->whereIn('program_type', [ProgramEnrollment::TYPE_PROMOTER])))
                ->when($partnerApplication->user_id, fn ($q) => $q->whereKeyNot($partnerApplication->user_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function status(Request $request, PartnerApplication $partnerApplication)
    {
        $data = $request->validate(['status' => ['required', Rule::in(PartnerApplication::statuses())]]);
        DB::transaction(function () use ($request, $partnerApplication, $data): void {
            $partnerApplication = PartnerApplication::lockForUpdate()->findOrFail($partnerApplication->id);
            $before = $partnerApplication->status;
            if ($before !== $data['status']) {
                $partnerApplication->update(['status' => $data['status']]);
                AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'partner_application_status_changed', 'subject_type' => PartnerApplication::class, 'subject_id' => $partnerApplication->id, 'metadata' => ['before' => ['status' => $before], 'after' => ['status' => $partnerApplication->status]]]);
            }
        });

        return back();
    }

    public function convert(Request $request, PartnerApplication $partnerApplication, PartnerOnboardingService $onboarding)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', PartnerSlug::rule()],
            'category' => 'nullable|in:food,cafe,fitness,wellness,beauty,entertainment,nightlife,shopping,services,experiences',
        ]);
        $onboarding->convert($partnerApplication, $request->user(), $data);

        return back()->with('success', 'Partner creado en borrador. Completa sus sucursales y vincula al owner autorizado.');
    }

    public function owner(Request $request, PartnerApplication $partnerApplication, PartnerOnboardingService $onboarding)
    {
        $data = $request->validate([
            'owner_user_id' => 'required|integer|exists:users,id',
            'authorization_confirmed' => 'required|accepted',
            'authorization_reason' => 'required|string|min:10|max:1000',
        ]);
        $onboarding->assignOwner($partnerApplication, $request->user(), (int) $data['owner_user_id'], $data['authorization_reason']);

        return back()->with('success', 'Owner vinculado. El acceso conserva el login y la verificación de correo existentes.');
    }
}
