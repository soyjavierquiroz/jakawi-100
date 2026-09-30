<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\City;
use App\Models\PartnerApplication;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PartnerApplication::with('city:id,name,slug')->latest();
        if ($request->filled('city')) $query->where('city_id', $request->integer('city'));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return Inertia::render('admin/partner-applications/index', ['applications' => $query->paginate(20)->withQueryString(), 'cities' => City::orderBy('name')->get(['id', 'name']), 'statuses' => PartnerApplication::statuses(), 'filters' => $request->only('city', 'status')]);
    }
    public function show(PartnerApplication $partnerApplication): Response
    {
        return Inertia::render('admin/partner-applications/show', ['application' => $partnerApplication->load('city:id,name,slug', 'user:id,name,email'), 'statuses' => PartnerApplication::statuses()]);
    }
    public function status(Request $request, PartnerApplication $partnerApplication)
    {
        $data = $request->validate(['status' => ['required', Rule::in(PartnerApplication::statuses())]]); $before = $partnerApplication->status;
        if ($before !== $data['status']) { $partnerApplication->update(['status' => $data['status']]); AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'partner_application_status_changed', 'subject_type' => PartnerApplication::class, 'subject_id' => $partnerApplication->id, 'metadata' => ['before' => ['status' => $before], 'after' => ['status' => $partnerApplication->status]]]); }
        return back();
    }
}
