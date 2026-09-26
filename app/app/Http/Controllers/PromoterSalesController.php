<?php

namespace App\Http\Controllers;

use App\Models\MembershipPurchase;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\MembershipPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PromoterSalesController extends Controller
{
    public function index(Request $request): Response
    {
        $sales = MembershipPurchase::query()->where('recorded_by_user_id', $request->user()->id)->with(['beneficiary', 'membership', 'conversion'])->latest()->paginate(20);
        $today = MembershipPurchase::query()->where('recorded_by_user_id', $request->user()->id)->whereDate('created_at', today())->where('status', 'confirmed');
        $conversionIds = (clone $today)->pluck('conversion_id')->filter();
        return Inertia::render('promoter/sales/index', ['sales' => $sales, 'stats' => ['memberships_sold' => (clone $today)->count(), 'sales_amount' => (string) $today->sum('amount'), 'commission_generated' => (string) RewardTransaction::whereIn('conversion_id', $conversionIds)->sum('amount')]]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('promoter/sales/create', ['customers' => $this->customers($request), 'membership' => config('jakawi.membership')]);
    }

    public function store(Request $request, CustomerAccountService $customers, MembershipPurchaseService $purchases)
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id'], 'name' => ['required_without:user_id', 'nullable', 'string', 'max:255'], 'email' => ['required_without:user_id', 'nullable', 'email', 'max:255', 'unique:users,email'], 'manual_reference' => ['required', 'string', 'max:255'], 'idempotency_key' => ['required', 'uuid']]);
        $actor = $request->user();
        $customer = isset($data['user_id']) ? User::findOrFail($data['user_id']) : $customers->create($actor, $data['name'], $data['email']);
        $purchase = $purchases->confirmManualCash($customer, $actor, $actor, $data['manual_reference'], $data['idempotency_key']);
        return to_route('promoter.sales.show', $purchase)->with('success', 'Membresía activada.');
    }

    public function show(Request $request, MembershipPurchase $purchase): Response
    {
        abort_unless($purchase->recorded_by_user_id === $request->user()->id, 403);
        return Inertia::render('promoter/sales/show', ['sale' => $this->sale($purchase)]);
    }

    /** @return array<int, array{id:int,name:string,email:string}> */
    private function customers(Request $request): array
    {
        $q = trim((string) $request->query('q'));
        if (Str::length($q) < 2) return [];
        return User::query()->where(fn ($query) => $query->where('email', 'ilike', "%{$q}%")->orWhere('name', 'ilike', "%{$q}%"))->orderBy('name')->limit(10)->get(['id', 'name', 'email'])->all();
    }

    private function sale(MembershipPurchase $purchase): array
    {
        $purchase->load(['beneficiary', 'membership', 'conversion']);
        return $purchase->toArray() + ['reward' => $purchase->conversion_id ? RewardTransaction::where('conversion_id', $purchase->conversion_id)->first() : null];
    }
}
