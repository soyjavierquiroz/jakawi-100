<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ProgramEnrollment;
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
        $query = RewardPayout::query()->with('beneficiary:id,name,email')->latest('requested_at');
        if (in_array($request->string('status')->toString(), [RewardPayout::STATUS_REQUESTED, RewardPayout::STATUS_PAID, RewardPayout::STATUS_REJECTED], true)) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('affiliate')) {
            $query->where('beneficiary_user_id', $request->integer('affiliate'));
        }
        if ($request->filled('date')) {
            $query->whereDate('requested_at', $request->string('date'));
        }

        return Inertia::render('admin/payouts/index', ['payouts' => $query->paginate(30)->withQueryString(), 'affiliates' => User::whereHas('programEnrollments', fn ($q) => $q->where('program_type', ProgramEnrollment::TYPE_AFFILIATE))->orderBy('name')->get(['id', 'name']), 'filters' => $request->only('status', 'affiliate', 'date'), 'minimumPayout' => $payouts->minimum()]);
    }

    public function show(RewardPayout $payout): Response
    {
        $payout->load(['beneficiary:id,name,email', 'paidBy:id,name,email', 'rewards.conversion']);

        return Inertia::render('admin/payouts/show', ['payout' => $payout, 'audit' => AuditLog::where('subject_type', RewardPayout::class)->where('subject_id', $payout->id)->orWhere(fn ($q) => $q->where('subject_type', RewardTransaction::class)->whereIn('subject_id', $payout->rewards()->pluck('reward_transactions.id')))->latest()->get()]);
    }

    public function settings(Request $request): mixed
    {
        $data = $request->validate(['affiliate_minimum_payout' => ['required', 'numeric', 'min:0']]);
        $before = AppSetting::where('key', 'affiliate_minimum_payout')->first()?->value;
        AppSetting::updateOrCreate(['key' => 'affiliate_minimum_payout'], ['value' => ['amount' => $data['affiliate_minimum_payout']]]);
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'affiliate_minimum_payout_updated', 'subject_type' => AppSetting::class, 'metadata' => ['before' => $before, 'after' => ['amount' => $data['affiliate_minimum_payout']]]]);

        return back()->with('success', 'Mínimo de pago actualizado.');
    }

    public function pay(Request $request, RewardPayout $payout, RewardPayoutService $payouts, AnalyticsTracker $analytics): mixed
    {
        $data = $request->validate(['payment_reference' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $wasPaid = $payout->status === RewardPayout::STATUS_PAID;
        $paid = $payouts->pay($payout, $request->user(), $data['payment_reference'], $data['notes'] ?? null);
        if (! $wasPaid) {
            $analytics->record('affiliate_payout_completed', ['user_id' => $paid->beneficiary_user_id]);
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
