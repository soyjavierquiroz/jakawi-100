<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RewardRule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRewardRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/reward-rules/index', ['rules' => RewardRule::latest()->get()]);
    }

    public function store(Request $request)
    {
        $rule = RewardRule::create($this->data($request));
        $this->audit($request, 'reward_rule_created', $rule);

        return back();
    }

    public function update(Request $request, RewardRule $rule)
    {
        $before = $rule->toArray();
        $rule->update($this->data($request));
        $this->audit($request, 'reward_rule_updated', $rule, $before);

        return back();
    }

    private function data(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:255'], 'participant_type' => ['nullable', 'in:PROMOTER,AFFILIATE,CREATOR,PARTNER,MEMBER'], 'event' => ['required', 'string', 'max:100'], 'product_key' => ['nullable', 'string', 'max:100'], 'reward_type' => ['required', 'in:CASH'], 'calculation_type' => ['required', 'in:FIXED,PERCENTAGE'], 'value' => ['required', 'numeric', 'gt:0'], 'currency' => ['nullable', 'string', 'size:3'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'priority' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:active,inactive'], 'maximum_rewards' => ['nullable', 'integer', 'min:1'], 'maximum_per_user' => ['nullable', 'integer', 'min:1']]);
    }

    private function audit(Request $request, string $action, RewardRule $rule, ?array $before = null): void
    {
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => RewardRule::class, 'subject_id' => $rule->id, 'metadata' => ['before' => $before, 'after' => $rule->fresh()->toArray()]]);
    }
}
