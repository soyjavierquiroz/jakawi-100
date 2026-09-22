<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMerchantRequest;
use App\Http\Requests\Admin\UpdateMerchantRequest;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class MerchantController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/merchants/index', [
            'merchants' => Merchant::query()
                ->withCount('benefits')
                ->orderBy('sort_order')
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/merchants/form', [
            'merchant' => null,
        ]);
    }

    public function store(StoreMerchantRequest $request): RedirectResponse
    {
        $merchant = new Merchant($this->validatedData($request->validated()));
        $this->storeUploads($merchant, $request);
        $this->storeRedemptionPin($merchant, $request->validated('redemption_pin'));
        $merchant->slug = Merchant::uniqueSlug($merchant->name);
        $merchant->save();

        return to_route('admin.merchants.index');
    }

    public function edit(Merchant $merchant): Response
    {
        return Inertia::render('admin/merchants/form', [
            'merchant' => $this->serializeMerchant($merchant),
        ]);
    }

    public function update(UpdateMerchantRequest $request, Merchant $merchant): RedirectResponse
    {
        $merchant->fill($this->validatedData($request->validated()));
        $this->storeUploads($merchant, $request);
        $this->storeRedemptionPin($merchant, $request->validated('redemption_pin'));
        $merchant->save();

        return to_route('admin.merchants.index');
    }

    /** @param array<string, mixed> $data */
    private function validatedData(array $data): array
    {
        unset($data['logo'], $data['cover'], $data['redemption_pin']);

        return [
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeMerchant(Merchant $merchant): array
    {
        return [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'short_description' => $merchant->short_description,
            'description' => $merchant->description,
            'category' => $merchant->category,
            'address' => $merchant->address,
            'city' => $merchant->city,
            'instagram' => $merchant->instagram,
            'whatsapp' => $merchant->whatsapp,
            'is_active' => $merchant->is_active,
            'is_featured' => $merchant->is_featured,
            'sort_order' => $merchant->sort_order,
            'has_redemption_pin' => (bool) $merchant->redemption_pin_hash,
        ];
    }

    private function storeUploads(Merchant $merchant, StoreMerchantRequest $request): void
    {
        if ($request->hasFile('logo')) {
            $merchant->logo_path = $request->file('logo')->store('merchants/logos', 'public');
        }

        if ($request->hasFile('cover')) {
            $merchant->cover_path = $request->file('cover')->store('merchants/covers', 'public');
        }
    }

    private function storeRedemptionPin(Merchant $merchant, ?string $pin): void
    {
        if ($pin) {
            $merchant->forceFill(['redemption_pin_hash' => Hash::make($pin)]);
        }
    }
}
