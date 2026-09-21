<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMerchantRequest;
use App\Http\Requests\Admin\UpdateMerchantRequest;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
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
        $merchant->slug = Merchant::uniqueSlug($merchant->name);
        $merchant->save();

        return to_route('admin.merchants.index');
    }

    public function edit(Merchant $merchant): Response
    {
        return Inertia::render('admin/merchants/form', [
            'merchant' => $merchant,
        ]);
    }

    public function update(UpdateMerchantRequest $request, Merchant $merchant): RedirectResponse
    {
        $merchant->fill($this->validatedData($request->validated()));
        $this->storeUploads($merchant, $request);
        $merchant->save();

        return to_route('admin.merchants.index');
    }

    /** @param array<string, mixed> $data */
    private function validatedData(array $data): array
    {
        unset($data['logo'], $data['cover']);

        return [
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'sort_order' => $data['sort_order'] ?? 0,
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
}
