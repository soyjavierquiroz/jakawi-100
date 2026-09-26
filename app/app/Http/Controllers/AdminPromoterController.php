<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MembershipPurchase;
use App\Models\ProgramEnrollment;
use App\Models\RewardRule;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\ReferralCodeService;
use App\Services\RewardResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminPromoterController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()->whereHas('programEnrollments', fn ($q) => $q->where('program_type', ProgramEnrollment::TYPE_PROMOTER))
            ->with(['programEnrollments' => fn ($q) => $q->where('program_type', ProgramEnrollment::TYPE_PROMOTER)->latest()]);
        if ($request->filled('q')) {
            $term = trim((string) $request->string('q'));
            $query->where(fn ($q) => $q->where('name', 'ilike', "%{$term}%")->orWhere('email', 'ilike', "%{$term}%"));
        }
        if (in_array($request->string('status')->toString(), ['active', 'inactive'], true)) {
            $query->whereHas('programEnrollments', fn ($q) => $q->where('program_type', ProgramEnrollment::TYPE_PROMOTER)->where('status', $request->string('status')));
        }
        $promoters = $query->orderBy('name')->paginate(20)->through(fn (User $user) => $this->summary($user));

        return Inertia::render('admin/promoters/index', ['promoters' => $promoters, 'filters' => $request->only('q', 'status')]);
    }

    public function create(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $users = Str::length($q) < 2 ? [] : User::query()->where(fn ($query) => $query->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%"))->orderBy('name')->limit(10)->get(['id', 'name', 'email', 'referral_code']);

        return Inertia::render('admin/promoters/create', ['users' => $users, 'query' => $q]);
    }

    public function store(Request $request)
    {
        $data = $this->enrollmentData($request, true);
        [$user, $newUser] = DB::transaction(function () use ($data, $request): array {
            $user = isset($data['user_id'])
                ? User::findOrFail($data['user_id'])
                : User::create(['name' => $data['name'], 'email' => Str::lower(trim($data['email'])), 'password' => Str::password(64)]);
            if (! isset($data['user_id'])) {
                app(ReferralCodeService::class)->ensureFor($user);
                $this->audit($request, 'promoter_created', $user, null, ['email' => $user->email]);
            }
            $enrollment = $user->programEnrollments()->where('program_type', ProgramEnrollment::TYPE_PROMOTER)->where('status', ProgramEnrollment::STATUS_ACTIVE)->latest()->first();
            $before = $enrollment?->toArray();
            if ($enrollment) {
                $enrollment->update($this->enrollmentAttributes($data));
                $action = 'promoter_enrollment_updated';
            } else {
                $enrollment = $user->programEnrollments()->create(['program_type' => ProgramEnrollment::TYPE_PROMOTER] + $this->enrollmentAttributes($data));
                $action = 'program_enrollment_added';
            }
            $this->audit($request, $action, $enrollment, $before, $enrollment->fresh()->toArray());
            if ($enrollment->status === ProgramEnrollment::STATUS_ACTIVE) {
                $this->audit($request, 'promoter_activated', $enrollment, null, ['operationally_active' => $enrollment->fresh()->isActive()]);
            }
            if (($data['commission_mode'] ?? 'default') === 'custom') {
                $this->saveCommission($request, $user, $data);
            }

            return [$user, ! isset($data['user_id'])];
        });
        $setup = $newUser ? Password::sendResetLink(['email' => $user->email]) : null;

        return to_route('admin.promoters.show', $user)->with('success', $newUser && $setup !== Password::RESET_LINK_SENT ? 'Promotor creado; el enlace de contraseña no pudo confirmarse.' : 'Promotor activado.');
    }

    public function show(User $user): Response
    {
        abort_unless($this->enrollment($user), 404);
        $enrollment = $this->enrollment($user);
        $sales = MembershipPurchase::query()->where('collected_by_user_id', $user->id)->with('beneficiary')->latest()->limit(10)->get();
        $rule = app(RewardResolver::class)->ruleFor($user, ProgramEnrollment::TYPE_PROMOTER, 'membership_purchased', 'jakawi_annual');
        $audit = AuditLog::query()->where(fn ($q) => $q->where('subject_type', User::class)->where('subject_id', $user->id)->orWhere('subject_type', ProgramEnrollment::class)->where('subject_id', $enrollment->id)->orWhere('subject_type', RewardRule::class)->whereIn('subject_id', RewardRule::where('beneficiary_user_id', $user->id)->pluck('id')))->latest()->get();

        return Inertia::render('admin/promoters/show', ['promoter' => $this->summary($user, $enrollment), 'commission' => ['effective' => $rule, 'source' => $rule ? ($rule->beneficiary_user_id ? 'individual' : ($rule->participant_type ? 'program' : 'global')) : null, 'individual' => RewardRule::where('beneficiary_user_id', $user->id)->where('event', 'membership_purchased')->where('reward_type', 'CASH')->latest()->first()], 'sales' => $sales, 'audit' => $audit]);
    }

    public function updateEnrollment(Request $request, User $user)
    {
        $enrollment = $this->enrollment($user);
        abort_unless($enrollment, 404);
        $data = $this->enrollmentData($request, false);
        $before = $enrollment->toArray();
        $enrollment->update($this->enrollmentAttributes($data));
        $action = $before['status'] !== $enrollment->status ? ($enrollment->status === 'active' ? 'promoter_activated' : 'promoter_deactivated') : 'promoter_validity_changed';
        $this->audit($request, $action, $enrollment, $before, $enrollment->fresh()->toArray());

        return back()->with('success', 'Enrolamiento actualizado.');
    }

    public function referralCode(Request $request, User $user)
    {
        abort_unless($this->enrollment($user), 404);
        $before = $user->only(['referral_code', 'referral_code_normalized']);
        $code = $request->boolean('regenerate') ? app(ReferralCodeService::class)->regenerate($user) : app(ReferralCodeService::class)->ensureFor($user);
        $this->audit($request, $before['referral_code'] ? 'promoter_referral_code_changed' : 'promoter_referral_code_generated', $user, $before, ['referral_code' => $code]);

        return back()->with('success', 'Código de referido listo.');
    }

    public function commission(Request $request, User $user)
    {
        abort_unless($this->enrollment($user), 404);
        $data = $request->validate(['mode' => ['required', 'in:default,custom'], 'calculation_type' => ['required_if:mode,custom', 'nullable', 'in:FIXED,PERCENTAGE'], 'value' => ['required_if:mode,custom', 'nullable', 'numeric', 'gt:0'], 'currency' => ['nullable', 'string', 'size:3'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'status' => ['required_if:mode,custom', 'nullable', 'in:active,inactive']]);
        if ($data['mode'] === 'default') {
            $rules = RewardRule::where('beneficiary_user_id', $user->id)->where('event', 'membership_purchased')->where('reward_type', 'CASH')->where('status', 'active')->get();
            foreach ($rules as $rule) { $before = $rule->toArray(); $rule->update(['status' => 'inactive']); $this->audit($request, 'individual_reward_rule_deactivated', $rule, $before, $rule->fresh()->toArray()); }
        } else {
            $this->saveCommission($request, $user, $data);
        }

        return back()->with('success', 'Comisión actualizada.');
    }

    private function saveCommission(Request $request, User $user, array $data): void
    {
        $rule = RewardRule::where('beneficiary_user_id', $user->id)->where('event', 'membership_purchased')->where('product_key', 'jakawi_annual')->where('reward_type', 'CASH')->latest()->first();
        $attributes = ['name' => 'Comisión individual: '.$user->name, 'beneficiary_user_id' => $user->id, 'participant_type' => ProgramEnrollment::TYPE_PROMOTER, 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => $data['calculation_type'], 'value' => $data['value'], 'currency' => $data['currency'] ?? 'BOB', 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null, 'priority' => 0, 'status' => $data['status'] ?? 'active'];
        $before = $rule?->toArray();
        $rule ? $rule->update($attributes) : $rule = RewardRule::create($attributes);
        $this->audit($request, $before ? 'individual_reward_rule_changed' : 'individual_reward_rule_added', $rule, $before, $rule->fresh()->toArray());
    }

    private function enrollmentData(Request $request, bool $withIdentity): array
    {
        return $request->validate(array_merge($withIdentity ? ['user_id' => ['nullable', 'integer', 'exists:users,id'], 'name' => ['required_without:user_id', 'nullable', 'string', 'max:255'], 'email' => ['required_without:user_id', 'nullable', 'email', 'max:255', 'unique:users,email']] : [], ['status' => ['required', 'in:active,inactive'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'commission_mode' => ['nullable', 'in:default,custom'], 'calculation_type' => ['required_if:commission_mode,custom', 'nullable', 'in:FIXED,PERCENTAGE'], 'value' => ['required_if:commission_mode,custom', 'nullable', 'numeric', 'gt:0'], 'currency' => ['nullable', 'string', 'size:3']]));
    }

    private function enrollmentAttributes(array $data): array { return ['status' => $data['status'], 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null]; }
    private function enrollment(User $user): ?ProgramEnrollment { return $user->programEnrollments()->where('program_type', ProgramEnrollment::TYPE_PROMOTER)->latest()->first(); }
    private function summary(User $user, ?ProgramEnrollment $enrollment = null): array { $enrollment ??= $this->enrollment($user); $sales = MembershipPurchase::where('collected_by_user_id', $user->id); return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'referral_code' => $user->referral_code, 'enrollment' => $enrollment, 'operationally_active' => $enrollment?->isActive() ?? false, 'sales_count' => (clone $sales)->count(), 'sales_amount' => (string) (clone $sales)->where('status', MembershipPurchase::STATUS_CONFIRMED)->sum('amount'), 'pending_commission' => (string) RewardTransaction::where('beneficiary_user_id', $user->id)->where('status', RewardTransaction::STATUS_PENDING)->sum('amount')]; }
    private function audit(Request $request, string $action, object $subject, ?array $before, array $after): void { AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id, 'metadata' => ['before' => $before, 'after' => $after]]); }
}
