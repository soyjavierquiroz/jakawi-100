<?php

namespace App\Http\Controllers;

use App\Models\ProgramApplication;
use App\Models\ProgramEnrollment;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\ProgramApplicationService;
use App\Services\PublicJourneyContinuation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramApplicationController extends Controller
{
    private const PROGRAMS = ['afiliados' => ProgramEnrollment::TYPE_AFFILIATE, 'creadores' => ProgramEnrollment::TYPE_CREATOR, 'promotores' => ProgramEnrollment::TYPE_PROMOTER];

    public function show(Request $request, string $program, AttributionService $attribution, AnalyticsTracker $analytics): Response
    {
        $type = $this->type($program);
        if ($request->filled('campaign') && ! $request->filled('utm_campaign')) $request->merge(['utm_campaign' => $request->query('campaign')]);
        $ref = $request->query('ref');
        $referrer = is_string($ref) ? $attribution->findReferrer($ref) : null;
        $touch = $attribution->recordLandingTouch($request, $referrer);
        if ($touch) $request->session()->push('attribution_touch_ids', $touch->id);
        $analytics->record('landing_view', [], ['landing' => $program]);

        $user = $request->user();
        $enrollment = $user?->programEnrollments()->where('program_type', $type)->latest()->first();
        $application = $user?->programApplications()->where('program_type', $type)->latest()->first();
        return Inertia::render('programs/index', [
            'program' => $program, 'canonical' => route('programs.show', $program),
            'authenticated' => (bool) $user,
            'application' => $application?->only(['id', 'status', 'created_at']),
            'enrollment' => $enrollment?->only(['status', 'starts_at', 'ends_at']),
            'active' => $enrollment?->isActive() ?? false,
            'profileCity' => $user?->profile?->city,
        ]);
    }

    public function apply(Request $request, string $program, AnalyticsTracker $analytics, PublicJourneyContinuation $continuation)
    {
        $type = $this->type($program);
        $analytics->record('landing_cta_click', [], ['landing' => $program]);
        if (! $request->user()) {
            $continuation->set('ACQUISITION', $type, 'APPLY');
            return to_route('register');
        }
        return redirect()->to(route('programs.show', $program, false).'#solicitud');
    }

    public function store(Request $request, string $program, ProgramApplicationService $applications, AnalyticsTracker $analytics)
    {
        $type = $this->type($program);
        $user = $request->user();
        if ($user->programEnrollments()->where('program_type', $type)->active()->exists()) return to_route('programs.show', $program);
        $data = $request->validate([
            'channel_url' => ['nullable', 'url:http,https', 'max:500'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        [, $created] = $applications->record($user, $type, $request, $data);
        if ($created) $analytics->record('program_application_submitted', [], ['program' => $type, 'landing' => $program]);
        return to_route('programs.show', $program);
    }

    private function type(string $program): string
    {
        abort_unless(isset(self::PROGRAMS[$program]), 404);
        return self::PROGRAMS[$program];
    }
}
