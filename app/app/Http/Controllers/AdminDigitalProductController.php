<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DigitalProduct;
use App\Services\DigitalCatalog;
use App\Services\DigitalFiles;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminDigitalProductController extends Controller
{
    public function index(DigitalCatalog $catalog)
    {
        return Inertia::render('admin/digital/products', ['products' => DigitalProduct::where('partner_id', $catalog->internalPartner()->id)->latest('id')->paginate(25), 'partner' => $catalog->internalPartner()->only('id', 'name')]);
    }

    public function edit(DigitalCatalog $catalog, ?DigitalProduct $product = null)
    {
        if ($product) {
            $catalog->assertInternal($product);
        }

        return Inertia::render('admin/digital/product-form', ['product' => $product ? $product->toArray() + ['coming_soon' => $product->isComingSoon(), 'qrs' => app(\App\Services\DigitalProductQr::class)->adminState($product), 'sale' => app(\App\Services\DigitalSaleAvailability::class)->adminState($product), 'has_file' => filled($product->file_path), 'gallery_urls' => array_map(fn ($path) => app(\App\Services\MediaUrl::class)->url($path, 'thumbnail'), $product->gallery_paths ?? [])] : null, 'partner' => $catalog->internalPartner()->only('id', 'name')]);
    }

    public function save(Request $request, DigitalCatalog $catalog, DigitalFiles $files, ?DigitalProduct $product = null)
    {
        if ($product) {
            $catalog->assertInternal($product);
        }
        $data = $request->validate([
            'public_qr' => 'nullable|image|mimes:png,jpg,jpeg|max:10240', 'member_qr' => 'nullable|image|mimes:png,jpg,jpeg|max:10240',
            'public_qr_confirmed' => 'sometimes|boolean', 'member_qr_confirmed' => 'sometimes|boolean',
            'pdf_final' => 'sometimes|boolean', 'sale_enabled' => 'sometimes|boolean',
            'name' => 'required|string|max:255', 'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('digital_products', 'slug')->ignore($product?->id)],
            'short_description' => 'nullable|string|max:1000', 'description' => 'nullable|string|max:30000', 'commercial_content' => 'nullable|string|max:30000',
            'status' => 'required|in:draft,published', 'public_price' => 'nullable|decimal:0,2|gt:0|max:99999999.99',
            'member_price' => 'nullable|decimal:0,2|gt:0|lte:public_price', 'currency' => 'required|in:BOB,USD',
            'support_information' => 'nullable|string|max:3000', 'access_terms' => 'nullable|string|max:10000',
            'pdf' => 'nullable|file|mimes:pdf|max:25600', 'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'category' => ['nullable', Rule::in(config('jakawi.categories'))],
            'gallery' => 'nullable|array|max:8', 'gallery.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            'gallery_clear' => 'sometimes|boolean', 'gallery_keep' => 'nullable|array|max:8', 'gallery_keep.*' => ['string', Rule::in($product?->gallery_paths ?? [])],
            'partner_id' => 'prohibited', 'delivery_type' => 'prohibited',
        ]);
        if ($data['status'] === 'published' && ! $product?->isComingSoon()) {
            Validator::make($data, ['public_price' => 'required', 'description' => 'required', 'support_information' => 'required', 'access_terms' => 'required'])->validate();
            abort_unless($request->hasFile('cover') || $product?->cover_path, 422, 'Sube la portada antes de publicar.');
            abort_unless($request->hasFile('pdf') || $product?->file_path, 422, 'Sube el PDF privado antes de publicar.');
        }
        $stored = $request->hasFile('pdf') ? $files->store($request->file('pdf'), 'products') : null;
        $keep = $request->boolean('gallery_clear') ? [] : ($data['gallery_keep'] ?? ($product?->gallery_paths ?? []));
        abort_if(count($keep) + count($request->file('gallery', [])) > 8, 422, 'La galería admite hasta 8 imágenes.');
        unset($data['public_qr'], $data['member_qr'], $data['public_qr_confirmed'], $data['member_qr_confirmed'], $data['pdf_final'], $data['sale_enabled'], $data['pdf'],$data['cover'],$data['gallery'],$data['gallery_keep'],$data['gallery_clear']);
        DB::transaction(function () use ($request, $data, $stored, $catalog, $keep, &$product) {
            $product ??= new DigitalProduct;
            $product->fill($data);
            $product->partner_id = $catalog->internalPartner()->id;
            if ($stored) {
                $product->fill(['file_path' => $stored['path'], 'file_sha256' => $stored['sha256']]);
            }
            $product->save();
            if ($request->hasFile('cover')) {
                $product->cover_path = app(MediaUploadService::class)->store($request->file('cover'), 'digital-products', $product->id, 'cover');
                $product->save();
            }
            $gallery = $keep;
            foreach ($request->file('gallery', []) as $image) {
                $gallery[] = app(MediaUploadService::class)->store($image, 'digital-products', $product->id, 'image');
            }
            $product->gallery_paths = $gallery;
            $product->save();
            foreach (['public','member'] as $tier) {
                if ($request->hasFile($tier.'_qr') || $request->has($tier.'_qr_confirmed')) {
                    app(\App\Services\DigitalProductQr::class)->save($product, $tier, $request->file($tier.'_qr'), $request->boolean($tier.'_qr_confirmed'), $request->user()->id);
                }
            }
            if ($request->has('sale_enabled') || $request->has('pdf_final')) {
                app(\App\Services\DigitalSaleAvailability::class)->configure($product, $request->boolean('pdf_final'), $request->boolean('sale_enabled'), $request->user()->id);
            }
            AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'digital_product_saved', 'subject_type' => DigitalProduct::class, 'subject_id' => $product->id, 'metadata' => ['status' => $product->status, 'file_sha256' => $product->file_sha256]]);
        });

        return redirect('/admin/productos-digitales')->with('success', 'Producto guardado.');
    }

    public function qr(DigitalProduct $product, string $tier, DigitalCatalog $catalog, DigitalFiles $files)
    {
        $catalog->assertInternal($product);
        $path = app(\App\Services\DigitalProductQr::class)->settings($product)[$tier]['qr_path'] ?? null;
        abort_unless($path, 404);
        return $files->response($path, 'qr-'.$tier.'.'.pathinfo($path, PATHINFO_EXTENSION), false);
    }

    public function pdf(DigitalProduct $product, DigitalCatalog $catalog, DigitalFiles $files)
    {
        $catalog->assertInternal($product);
        abort_unless($product->file_path, 404);

        return $files->response($product->file_path,$product->slug.'.pdf');
    }
}
