<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MembershipPurchase;
use App\Models\ProgramEnrollment;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\MembershipPurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSalesController extends Controller
{
    public function index(Request $request): Response
    {
        $query = MembershipPurchase::query()->with(['beneficiary', 'recordedBy', 'collectedBy', 'membership'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('promoter')) {
            $query->where('collected_by_user_id', $request->integer('promoter'));
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->string('date'));
        }

        return Inertia::render('admin/sales/index', ['sales' => $query->paginate(30)->withQueryString(), 'promoters' => User::whereHas('programEnrollments', fn ($q) => $q->active()->where('program_type', ProgramEnrollment::TYPE_PROMOTER))->orderBy('name')->get(['id', 'name']), 'filters' => $request->only('status', 'promoter', 'date')]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/sales/create', ['customers' => $this->customers($request), 'membership' => config('jakawi.membership')]);
    }

    public function store(Request $request, CustomerAccountService $customers, MembershipPurchaseService $purchases)
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id'], 'name' => ['required_without:user_id', 'nullable', 'string', 'max:255'], 'email' => ['required_without:user_id', 'nullable', 'email', 'max:255', 'unique:users,email'], 'manual_reference' => ['required', 'string', 'max:255'], 'idempotency_key' => ['required', 'uuid']]);
        $actor = $request->user();
        $customer = isset($data['user_id']) ? User::findOrFail($data['user_id']) : $customers->create($actor, $data['name'], $data['email']);
        $purchase = $purchases->confirmManualCash($customer, $actor, null, $data['manual_reference'], $data['idempotency_key']);

        return to_route('admin.sales.show', $purchase)->with('success', 'Membresía activada.');
    }

    public function show(MembershipPurchase $sale): Response
    {
        $sale->load(['beneficiary', 'recordedBy', 'collectedBy', 'membership', 'conversion']);

        return Inertia::render('admin/sales/show', ['sale' => $sale, 'reward' => $sale->conversion_id ? RewardTransaction::where('conversion_id', $sale->conversion_id)->first() : null, 'audit' => AuditLog::where('subject_type', MembershipPurchase::class)->where('subject_id', $sale->id)->orWhere(fn ($q) => $q->where('subject_type', RewardTransaction::class)->whereIn('subject_id', RewardTransaction::where('conversion_id', $sale->conversion_id)->pluck('id')))->latest()->get()]);
    }

    public function refund(Request $request, MembershipPurchase $sale, MembershipPurchaseService $purchases)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $purchases->refund($sale, $request->user(), $data['reason']);

        return back()->with('success', 'Venta reembolsada y auditada.');
    }

    private function customers(Request $request): array
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return [];
        }

        return User::query()->where(fn ($query) => $query->where('email', 'ilike', "%{$q}%")->orWhere('name', 'ilike', "%{$q}%"))->orderBy('name')->limit(10)->get(['id', 'name', 'email'])->all();
    }
}
