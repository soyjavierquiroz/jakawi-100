<?php

namespace App\Http\Controllers;

use App\Models\AttributionTouch;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Conversion;
use App\Models\RewardTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $campaigns = Campaign::query()->with('participants')->withCount('conversions')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->participant_type, fn ($q, $type) => $q->whereHas('participants', fn ($p) => $p->where('participant_type', $type)))
            ->latest()->get()->map(fn (Campaign $campaign) => $this->summary($campaign));
        return Inertia::render('admin/campaigns/index', ['campaigns' => $campaigns, 'filters' => $request->only('status', 'participant_type')]);
    }

    public function store(Request $request)
    {
        $campaign = Campaign::create($this->data($request));
        $campaign->participants()->createMany(collect($request->input('participant_types'))->unique()->map(fn ($type) => ['participant_type' => $type])->all());
        $this->audit($request, 'campaign_created', $campaign);
        return back();
    }

    public function update(Request $request, Campaign $campaign)
    {
        $before = $campaign->load('participants')->toArray();
        $campaign->update($this->data($request));
        $campaign->participants()->delete();
        $campaign->participants()->createMany(collect($request->input('participant_types'))->unique()->map(fn ($type) => ['participant_type' => $type])->all());
        $this->audit($request, 'campaign_updated', $campaign, $before);
        return back();
    }

    private function data(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'alpha_dash', 'max:100', 'unique:campaigns,code,'.optional($request->route('campaign'))->id], 'description' => ['nullable', 'string'], 'status' => ['required', 'in:draft,active,inactive'], 'start_at' => ['nullable', 'date'], 'end_at' => ['nullable', 'date', 'after_or_equal:start_at'], 'event' => ['required', 'in:membership_purchased'], 'product_key' => ['nullable', 'string', 'max:100'], 'participant_types' => ['required', 'array', 'min:1'], 'participant_types.*' => ['in:PROMOTER,AFFILIATE,CREATOR,MEMBER,PARTNER']]);
    }

    private function summary(Campaign $campaign): array
    {
        $confirmed = $campaign->conversions()->where('status', 'confirmed');
        $conversionIds = (clone $confirmed)->pluck('id');
        return $campaign->toArray() + ['participant_types' => $campaign->participants->pluck('participant_type')->values(), 'clicks' => AttributionTouch::where('utm_campaign', $campaign->code)->count(), 'conversions_count' => $confirmed->count(), 'revenue' => (string) $confirmed->sum('eligible_amount'), 'rewards_generated' => (string) RewardTransaction::whereIn('conversion_id', $conversionIds)->where('status', '!=', RewardTransaction::STATUS_CANCELLED)->sum('amount')];
    }

    private function audit(Request $request, string $action, Campaign $campaign, ?array $before = null): void
    {
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => Campaign::class, 'subject_id' => $campaign->id, 'metadata' => ['before' => $before, 'after' => $campaign->fresh()->load('participants')->toArray()]]);
    }
}
