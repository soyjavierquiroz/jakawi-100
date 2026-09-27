<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ProgramEnrollment;
use App\Models\Partner;
use App\Models\RewardPayout;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\AnalyticsTracker;
use App\Services\RewardPayoutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPayoutController extends Controller
{
    public function index(Request $request, RewardPayoutService $payouts): Response
    {
        $query = RewardPayout::query()->with(['beneficiary:id,name,email', 'requestedBy:id,name,email'])->latest('requested_at');
        if (in_array($request->string('status')->toString(), [RewardPayout::STATUS_REQUESTED, RewardPayout::STATUS_PAID, RewardPayout::STATUS_REJECTED], true)) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('affiliate')) {
            $query->where('beneficiary_user_id', $request->integer('affiliate'));
        }
        if ($request->filled('date')) {
            $query->whereDate('requested_at', $request->string('date'));
        }

        $page = $query->paginate(30)->withQueryString();
        $names = Partner::whereIn('id', $page->getCollection()->where('beneficiary_type', 'PARTNER')->pluck('beneficiary_id'))->pluck('name', 'id');
        $page->getCollection()->each(function (RewardPayout $payout) use ($names): void { $type = $payout->beneficiary_type ?: 'USER'; $payout->setAttribute('beneficiary_label', $type === 'PARTNER' ? $names[$payout->beneficiary_id] ?? 'Partner eliminado' : $payout->beneficiary?->name); $payout->setAttribute('beneficiary_kind', $type === 'PARTNER' ? 'Partner' : 'Affiliate / Creator'); });
        return Inertia::render('admin/payouts/index', ['payouts' => $page, 'affiliates' => User::whereHas('programEnrollments', fn ($q) => $q->where('program_type', ProgramEnrollment::TYPE_AFFILIATE))->orderBy('name')->get(['id', 'name']), 'filters' => $request->only('status', 'affiliate', 'date'), 'minimumPayout' => $payouts->minimum(), 'partnerMinimumPayout' => $payouts->partnerMinimum()]);
    }

    public function show(RewardPayout $payout): Response
    {
        $payout->load(['beneficiary:id,name,email', 'requestedBy:id,name,email', 'paidBy:id,name,email', 'rewards.conversion']);
        $type = $payout->beneficiary_type ?: 'USER';
        $payout->setAttribute('beneficiary_label', $type === 'PARTNER' ? Partner::find($payout->beneficiary_id)?->name : $payout->beneficiary?->name);
        $payout->setAttribute('beneficiary_kind', $type === 'PARTNER' ? 'Partner' : 'Affiliate / Creator');

        return Inertia::render('admin/payouts/show', ['payout' => $payout, 'audit' => AuditLog::where('subject_type', RewardPayout::class)->where('subject_id', $payout->id)->orWhere(fn ($q) => $q->where('subject_type', RewardTransaction::class)->whereIn('subject_id', $payout->rewards()->pluck('reward_transactions.id')))->latest()->get()]);
    }

    public function settings(Request $request): mixed
    {
        $data = $request->validate(['affiliate_minimum_payout' => ['required', 'numeric', 'min:0'], 'partner_minimum_payout' => ['required', 'numeric', 'min:0']]);
        $before = AppSetting::where('key', 'affiliate_minimum_payout')->first()?->value;
        AppSetting::updateOrCreate(['key' => 'affiliate_minimum_payout'], ['value' => ['amount' => $data['affiliate_minimum_payout']]]);
        $partnerBefore = AppSetting::where('key', 'partner_minimum_payout')->first()?->value;
        AppSetting::updateOrCreate(['key' => 'partner_minimum_payout'], ['value' => ['amount' => $data['partner_minimum_payout']]]);
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'affiliate_minimum_payout_updated', 'subject_type' => AppSetting::class, 'metadata' => ['before' => $before, 'after' => ['amount' => $data['affiliate_minimum_payout']]]]);
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'partner_minimum_payout_updated', 'subject_type' => AppSetting::class, 'metadata' => ['before' => $partnerBefore, 'after' => ['amount' => $data['partner_minimum_payout']]]]);

        return back()->with('success', 'Mínimo de pago actualizado.');
    }

    public function pay(Request $request, RewardPayout $payout, RewardPayoutService $payouts, AnalyticsTracker $analytics): mixed
    {
        $data = $request->validate(['payment_reference' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $wasPaid = $payout->status === RewardPayout::STATUS_PAID;
        $paid = $payouts->pay($payout, $request->user(), $data['payment_reference'], $data['notes'] ?? null);
        if (! $wasPaid) {
            $analytics->record('affiliate_payout_completed', ['beneficiary_type' => $paid->beneficiary_type ?: 'USER', 'beneficiary_id' => $paid->beneficiary_id ?: $paid->beneficiary_user_id]);
        }

        return to_route('admin.payouts.show', $paid)->with('success', 'Pago registrado.');
    }

    public function reject(Request $request, RewardPayout $payout, RewardPayoutService $payouts): mixed
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $payouts->reject($payout, $request->user(), $data['reason']);

        return to_route('admin.payouts.show', $payout)->with('success', 'Solicitud rechazada; las comisiones vuelven a estar disponibles.');
    }
}
