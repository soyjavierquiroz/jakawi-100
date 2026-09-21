<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBenefitRequest;
use App\Http\Requests\Admin\UpdateBenefitRequest;
use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BenefitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/benefits/index', [
            'benefits' => Benefit::query()
                ->with('merchant')
                ->orderBy('sort_order')
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/benefits/form', [
            'benefit' => null,
            'merchants' => $this->merchantOptions(),
        ]);
    }

    public function store(StoreBenefitRequest $request): RedirectResponse
    {
        $benefit = new Benefit($this->validatedData($request->validated()));
        $benefit->slug = Benefit::uniqueSlug($benefit->title);
        $this->storeUpload($benefit, $request);
        $benefit->save();

        return to_route('admin.benefits.index');
    }

    public function edit(Benefit $benefit): Response
    {
        return Inertia::render('admin/benefits/form', [
            'benefit' => $benefit,
            'merchants' => $this->merchantOptions(),
        ]);
    }

    public function update(UpdateBenefitRequest $request, Benefit $benefit): RedirectResponse
    {
        $benefit->fill($this->validatedData($request->validated()));
        $benefit->slug = Benefit::uniqueSlug($benefit->title, $benefit->id);
        $this->storeUpload($benefit, $request);
        $benefit->save();

        return to_route('admin.benefits.index');
    }

    /** @return array<int, array{id: int, name: string}> */
    private function merchantOptions(): array
    {
        return Merchant::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Merchant $merchant) => ['id' => $merchant->id, 'name' => $merchant->name])
            ->all();
    }

    /** @param array<string, mixed> $data */
    private function validatedData(array $data): array
    {
        unset($data['image']);

        return [
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function storeUpload(Benefit $benefit, StoreBenefitRequest $request): void
    {
        if ($request->hasFile('image')) {
            $benefit->image_path = $request->file('image')->store('benefits', 'public');
        }
    }
}
