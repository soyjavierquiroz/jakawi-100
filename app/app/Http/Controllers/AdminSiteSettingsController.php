<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSiteSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/site-settings', ['showDemoBanner' => AppSetting::showDemoBanner()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['show_demo_banner' => ['required', 'boolean']]);
        $before = AppSetting::showDemoBanner();
        $enabled = (bool) $data['show_demo_banner'];
        AppSetting::updateOrCreate(['key' => 'show_demo_banner'], ['value' => ['enabled' => $enabled]]);
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'demo_banner_updated', 'subject_type' => AppSetting::class, 'metadata' => ['before' => $before, 'after' => $enabled]]);

        return back()->with('success', 'Ajustes guardados.');
    }
}
