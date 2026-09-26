<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Conversion;
use App\Models\ProgramEnrollment;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\AffiliateMetrics;
use App\Services\ReferralCodeService;
use App\Services\RewardResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminAffiliateController extends Controller
{
    protected function programType(): string { return ProgramEnrollment::TYPE_AFFILIATE; }
    protected function programSlug(): string { return 'affiliates'; }
    protected function page(string $name): string { return 'admin/'.$this->programSlug().'/'.$name; }
    protected function auditPrefix(): string { return 'affiliate'; }
    public function index(Request $request, AffiliateMetrics $metrics): Response
    {
        $query = User::query()->whereHas('programEnrollments', fn ($q) => $q->where('program_type', $this->programType()))
            ->with(['programEnrollments' => fn ($q) => $q->where('program_type', $this->programType())->latest()]);
        if ($request->filled('q')) {
            $term = trim((string) $request->string('q'));
            $query->where(fn ($q) => $q->where('name', 'ilike', "%{$term}%")->orWhere('email', 'ilike', "%{$term}%"));
        }
        if (in_array($request->string('status')->toString(), ['active', 'inactive'], true)) {
            $query->whereHas('programEnrollments', fn ($q) => $q->where('program_type', $this->programType())->where('status', $request->string('status')));
        }

        return Inertia::render($this->page('index'), ['affiliates' => $query->orderBy('name')->paginate(20)->through(fn (User $user) => $this->summary($user, $metrics)), 'filters' => $request->only('q', 'status')]);
    }

    public function create(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $users = Str::length($q) < 2 ? [] : User::query()->where(fn ($query) => $query->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%"))->orderBy('name')->limit(10)->get(['id', 'name', 'email']);

        return Inertia::render($this->page('create'), ['users' => $users, 'query' => $q]);
    }

    public function store(Request $request)
    {
        $data = $this->enrollmentData($request, true);
        [$user, $newUser] = DB::transaction(function () use ($data, $request): array {
            $newUser = ! isset($data['user_id']);
            $user = $newUser ? User::create(['name' => $data['name'], 'email' => Str::lower(trim($data['email'])), 'password' => Str::password(64)]) : User::findOrFail($data['user_id']);
            if ($newUser) {
                app(ReferralCodeService::class)->ensureFor($user);
                $this->audit($request, 'affiliate_created', $user, null, ['email' => $user->email]);
            }
            $enrollment = $this->enrollment($user);
            $before = $enrollment?->toArray();
            if ($enrollment) {
                $enrollment->update($this->enrollmentAttributes($data));
                $action = 'affiliate_enrollment_updated';
            } else {
                $enrollment = $user->programEnrollments()->create(['program_type' => $this->programType()] + $this->enrollmentAttributes($data));
                $action = 'program_enrollment_added';
            }
            $this->audit($request, $action, $enrollment, $before, $enrollment->fresh()->toArray());
            if ($enrollment->status === 'active') {
                $this->audit($request, 'affiliate_activated', $enrollment, null, ['operationally_active' => $enrollment->fresh()->isActive()]);
            }
            if (($data['commission_mode'] ?? 'default') === 'custom') {
                $this->saveCommission($request, $user, $data);
            }

            return [$user, $newUser];
        });
        $setup = $newUser ? Password::sendResetLink(['email' => $user->email]) : null;

        return to_route('admin.'.$this->programSlug().'.show', $user)->with('success', $newUser && $setup !== Password::RESET_LINK_SENT ? 'Cuenta creada; el enlace de contraseña no pudo confirmarse.' : 'Enrolamiento activado.');
    }

    public function show(User $user, AffiliateMetrics $metrics): Response
    {
        abort_unless($enrollment = $this->enrollment($user), 404);
        $rule = app(RewardResolver::class)->ruleFor($user, RewardRule::BENEFICIARY_USER, $this->programType(), 'membership_purchased', config('jakawi.membership.product_key', 'jakawi_annual'));
        $conversions = Conversion::query()->where('type', 'membership_purchased')->whereHas('relationship', fn ($q) => $q->where('referrer_user_id', $user->id))->latest('occurred_at')->limit(10)->get(['id', 'order_reference', 'eligible_amount', 'currency', 'status', 'occurred_at']);
        $rewards = RewardTransaction::query()->where('beneficiary_user_id', $user->id)->with('rule')->latest()->limit(10)->get();
        $audit = AuditLog::query()->where(fn ($q) => $q->where('subject_type', User::class)->where('subject_id', $user->id)->orWhere('subject_type', ProgramEnrollment::class)->where('subject_id', $enrollment->id)->orWhere('subject_type', RewardRule::class)->whereIn('subject_id', RewardRule::where('beneficiary_user_id', $user->id)->pluck('id'))->orWhere('subject_type', RewardTransaction::class)->whereIn('subject_id', RewardTransaction::where('beneficiary_user_id', $user->id)->pluck('id')))->latest()->get();

        return Inertia::render($this->page('show'), ['affiliate' => $this->summary($user, $metrics), 'commission' => ['effective' => $rule, 'source' => $rule ? ($rule->beneficiary_user_id ? 'individual' : ($rule->participant_type ? 'program' : 'global')) : null, 'individual' => RewardRule::where('beneficiary_user_id', $user->id)->where('event', 'membership_purchased')->where('reward_type', 'CASH')->latest()->first()], 'conversions' => $conversions, 'rewards' => $rewards, 'audit' => $audit]);
    }

    public function updateEnrollment(Request $request, User $user)
    {
        abort_unless($enrollment = $this->enrollment($user), 404);
        $data = $this->enrollmentData($request, false);
        $before = $enrollment->toArray();
        $enrollment->update($this->enrollmentAttributes($data));
        $this->audit($request, $before['status'] !== $enrollment->status ? ($enrollment->status === 'active' ? 'affiliate_activated' : 'affiliate_deactivated') : 'affiliate_validity_changed', $enrollment, $before, $enrollment->fresh()->toArray());

        return back()->with('success', 'Enrolamiento actualizado.');
    }

    public function referralCode(Request $request, User $user)
    {
        abort_unless($this->enrollment($user), 404);
        $before = $user->only(['referral_code', 'referral_code_normalized']);
        $code = $request->boolean('regenerate') ? app(ReferralCodeService::class)->regenerate($user) : app(ReferralCodeService::class)->ensureFor($user);
        $this->audit($request, $before['referral_code'] ? 'affiliate_referral_code_changed' : 'affiliate_referral_code_generated', $user, $before, ['referral_code' => $code]);

        return back()->with('success', 'Código de referido listo.');
    }

    public function commission(Request $request, User $user)
    {
        abort_unless($this->enrollment($user), 404);
        $data = $this->commissionData($request);
        if ($data['mode'] === 'default') {
            foreach (RewardRule::where('beneficiary_user_id', $user->id)->where('event', 'membership_purchased')->where('reward_type', 'CASH')->where('status', 'active')->get() as $rule) {
                $before = $rule->toArray();
                $rule->update(['status' => 'inactive']);
                $this->audit($request, 'individual_reward_rule_deactivated', $rule, $before, $rule->fresh()->toArray());
            }
        } else {
            $this->saveCommission($request, $user, $data);
        }

return back()->with('success', 'Comisión actualizada.');
    }

    public function makeAvailable(Request $request, User $user, RewardTransaction $reward)
    {
        abort_unless($this->enrollment($user) && $reward->beneficiary_user_id === $user->id, 404);
        abort_unless($reward->status === RewardTransaction::STATUS_PENDING, 422);
        $before = $reward->toArray();
        $reward->update(['status' => RewardTransaction::STATUS_AVAILABLE, 'available_at' => now()]);
        $this->audit($request, 'affiliate_reward_made_available', $reward, $before, $reward->fresh()->toArray());

        return back()->with('success', 'Comisión disponible.');
    }

    private function enrollmentData(Request $request, bool $identity): array
    {
        return $request->validate(array_merge($identity ? ['user_id' => ['nullable', 'integer', 'exists:users,id'], 'name' => ['required_without:user_id', 'nullable', 'string', 'max:255'], 'email' => ['required_without:user_id', 'nullable', 'email', 'max:255', 'unique:users,email']] : [], ['status' => ['required', 'in:active,inactive'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'commission_mode' => ['nullable', 'in:default,custom'], 'calculation_type' => ['required_if:commission_mode,custom', 'nullable', 'in:FIXED,PERCENTAGE'], 'value' => ['required_if:commission_mode,custom', 'nullable', 'numeric', 'gt:0'], 'currency' => ['nullable', 'string', 'size:3']]));
    }

    private function commissionData(Request $request): array
    {
        return $request->validate(['mode' => ['required', 'in:default,custom'], 'calculation_type' => ['required_if:mode,custom', 'nullable', 'in:FIXED,PERCENTAGE'], 'value' => ['required_if:mode,custom', 'nullable', 'numeric', 'gt:0'], 'currency' => ['nullable', 'string', 'size:3'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'status' => ['required_if:mode,custom', 'nullable', 'in:active,inactive']]);
    }

    private function enrollmentAttributes(array $data): array
    {
        return ['status' => $data['status'], 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null];
    }

    private function enrollment(User $user): ?ProgramEnrollment
    {
        return $user->programEnrollments()->where('program_type', $this->programType())->latest()->first();
    }

    private function saveCommission(Request $request, User $user, array $data): void
    {
        $rule = RewardRule::where('beneficiary_user_id', $user->id)->where('event', 'membership_purchased')->where('product_key', config('jakawi.membership.product_key', 'jakawi_annual'))->where('reward_type', 'CASH')->latest()->first();
        $attributes = ['name' => 'Comisión individual: '.$user->name, 'beneficiary_user_id' => $user->id, 'participant_type' => $this->programType(), 'event' => 'membership_purchased', 'product_key' => config('jakawi.membership.product_key', 'jakawi_annual'), 'reward_type' => 'CASH', 'calculation_type' => $data['calculation_type'], 'value' => $data['value'], 'currency' => $data['currency'] ?? 'BOB', 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null, 'priority' => 0, 'status' => $data['status'] ?? 'active'];
        $before = $rule?->toArray();
        $rule ? $rule->update($attributes) : $rule = RewardRule::create($attributes);
        $this->audit($request, $before ? 'individual_reward_rule_changed' : 'individual_reward_rule_added', $rule, $before, $rule->fresh()->toArray());
    }

    private function summary(User $user, AffiliateMetrics $metrics): array
    {
        $enrollment = $this->enrollment($user);

        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'referral_code' => $user->referral_code, 'enrollment' => $enrollment, 'operationally_active' => $enrollment?->isActive() ?? false, 'metrics' => $metrics->for($user)];
    }

    private function audit(Request $request, string $action, object $subject, ?array $before, array $after): void
    {
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id, 'metadata' => ['before' => $before, 'after' => $after]]);
    }
}
