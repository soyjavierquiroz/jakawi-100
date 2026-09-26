<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\RewardRule;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRewardRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/reward-rules/index', ['rules' => RewardRule::latest()->get(), 'partners' => Partner::orderBy('name')->get(['id', 'name']), 'campaigns' => Campaign::latest()->get(['id', 'name', 'code'])]);
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
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'campaign_id' => ['nullable', 'exists:campaigns,id'], 'beneficiary_type' => ['nullable', 'in:USER,PARTNER'], 'beneficiary_id' => ['nullable', 'integer'], 'participant_type' => ['nullable', 'in:PROMOTER,AFFILIATE,CREATOR,PARTNER,MEMBER'], 'event' => ['required', 'string', 'max:100'], 'product_key' => ['nullable', 'string', 'max:100'], 'reward_type' => ['required', 'in:CASH,JP'], 'calculation_type' => ['required', 'in:FIXED,PERCENTAGE'], 'value' => ['required', 'numeric', 'gt:0'], 'currency' => ['nullable', 'string', 'size:3'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'priority' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:active,inactive'], 'maximum_rewards' => ['nullable', 'integer', 'min:1'], 'maximum_per_user' => ['nullable', 'integer', 'min:1']]);
        if ($data['campaign_id'] ?? null) { $campaign = Campaign::findOrFail($data['campaign_id']); abort_unless($campaign->event === $data['event'] && $campaign->eligibleFor($data['participant_type']), 422, 'La regla debe coincidir con el evento y participante de la campaña.'); }
        abort_if(($data['beneficiary_type'] ?? null) === 'PARTNER' && ! Partner::whereKey($data['beneficiary_id'])->exists(), 422, 'Partner beneficiary invalid.');
        abort_if(($data['beneficiary_type'] ?? null) === 'USER' && ! User::whereKey($data['beneficiary_id'])->exists(), 422, 'User beneficiary invalid.');
        if (($data['beneficiary_type'] ?? null) === 'USER') $data['beneficiary_user_id'] = $data['beneficiary_id'];
        if ($data['reward_type'] === 'JP') {
            abort_unless($data['participant_type'] === 'MEMBER' && $data['calculation_type'] === 'FIXED' && floor((float) $data['value']) === (float) $data['value'], 422, 'JP requiere MEMBER, FIXED y un valor entero.');
            $data['currency'] = 'JP';
        }
        return $data;
    }

    private function audit(Request $request, string $action, RewardRule $rule, ?array $before = null): void
    {
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => RewardRule::class, 'subject_id' => $rule->id, 'metadata' => ['before' => $before, 'after' => $rule->fresh()->toArray()]]);
    }
}
