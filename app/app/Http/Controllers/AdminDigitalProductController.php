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
use Illuminate\Validation\ValidationException;
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

        return Inertia::render('admin/digital/product-form', ['product' => $product ? $product->toArray() + ['gift_pdf_final' => filled($product->gift_pdf_sha256) && $product->gift_pdf_sha256 === $product->file_sha256, 'coming_soon' => $product->isComingSoon(), 'qrs' => app(\App\Services\DigitalProductQr::class)->adminState($product), 'sale' => app(\App\Services\DigitalSaleAvailability::class)->adminState($product), 'has_file' => filled($product->file_path), 'gallery_urls' => array_map(fn ($path) => app(\App\Services\MediaUrl::class)->url($path, 'thumbnail'), $product->gallery_paths ?? [])] : null, 'partner' => $catalog->internalPartner()->only('id', 'name')]);
    }

    public function save(Request $request, DigitalCatalog $catalog, DigitalFiles $files, ?DigitalProduct $product = null)
    {
        if ($product) {
            $catalog->assertInternal($product);
        }
        $data = $request->validate([
            'public_qr' => 'nullable|image|mimes:png,jpg,jpeg|max:10240', 'member_qr' => 'nullable|image|mimes:png,jpg,jpeg|max:10240',
            'public_qr_confirmed' => 'sometimes|boolean', 'member_qr_confirmed' => 'sometimes|boolean',
            'welcome_gift_enabled' => 'sometimes|boolean', 'promotional_gift_enabled' => 'sometimes|boolean', 'gift_pdf_final' => 'sometimes|boolean',
            'operation' => 'sometimes|in:save,authorize_sale',
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
            if (! ($request->hasFile('cover') || $product?->cover_path)) throw ValidationException::withMessages(['cover' => 'Sube la portada antes de publicar.']);
            if (! ($request->hasFile('pdf') || $product?->file_path)) throw ValidationException::withMessages(['pdf' => 'Sube el PDF privado antes de publicar.']);
        }
        $keep = $request->boolean('gallery_clear') ? [] : ($data['gallery_keep'] ?? ($product?->gallery_paths ?? []));
        if (count($keep) + count($request->file('gallery', [])) > 8) throw ValidationException::withMessages(['gallery' => 'La galería admite hasta 8 imágenes entre las conservadas y las nuevas.']);
        unset($data['operation']);
        unset($data['welcome_gift_enabled'], $data['promotional_gift_enabled'], $data['gift_pdf_final']);
        unset($data['public_qr'], $data['member_qr'], $data['public_qr_confirmed'], $data['member_qr_confirmed'], $data['pdf_final'], $data['sale_enabled'], $data['pdf'],$data['cover'],$data['gallery'],$data['gallery_keep'],$data['gallery_clear']);
        DB::transaction(function () use ($request, $data, $files, $catalog, $keep, &$product) {
            $product ??= new DigitalProduct;
            $product->fill($data);
            $product->partner_id = $catalog->internalPartner()->id;
            if ($request->input('operation') === 'authorize_sale' && ($request->hasFile('pdf') || $request->hasFile('cover') || $request->hasFile('gallery') || $request->hasFile('public_qr') || $request->hasFile('member_qr'))) throw ValidationException::withMessages(['sale_enabled' => 'Primero guarda los archivos con Guardar producto. Después habilita la venta con las referencias ya guardadas.']);
            // Validate administrative intent before writing any object to MinIO.
            \App\Models\AppSetting::where('key', \App\Services\DigitalCollection::KEY)->lockForUpdate()->first();
            foreach (['public','member'] as $tier) {
                app(\App\Services\DigitalProductQr::class)->validateSave($product, $tier, $request->file($tier.'_qr'), $request->boolean($tier.'_qr_confirmed'));
            }
            $giftFinal = filled($product->gift_pdf_sha256) && $product->gift_pdf_sha256 === $product->file_sha256;
            $giftChanged = $request->has('gift_pdf_final') && (
                $request->boolean('gift_pdf_final') !== $giftFinal
                || $request->boolean('welcome_gift_enabled') !== (bool) $product->welcome_gift_enabled
                || $request->boolean('promotional_gift_enabled') !== (bool) $product->promotional_gift_enabled
                || ($request->hasFile('pdf') && ($giftFinal || $request->boolean('gift_pdf_final')))
            );
            if ($giftChanged && ($request->boolean('welcome_gift_enabled') || $request->boolean('promotional_gift_enabled')) && ! $request->boolean('gift_pdf_final')) {
                throw ValidationException::withMessages(['gift_pdf_final' => 'Confirma el PDF definitivo para cambiar la autorización de regalos. Puedes guardar sin modificar los permisos de regalo actuales.']);
            }
            $sale = app(\App\Services\DigitalSaleAvailability::class);
            $saleSettings = $product->exists ? $sale->settings($product) : [];
            $saleChanged = ($request->has('sale_enabled') || $request->has('pdf_final')) && (
                $request->boolean('sale_enabled') !== ($saleSettings['enabled'] ?? false)
                || $request->boolean('pdf_final') !== (filled($saleSettings['pdf_sha256'] ?? null) && ($saleSettings['pdf_sha256'] ?? null) === $product->file_sha256)
                || $request->hasFile('pdf')
            );
            if ($request->boolean('sale_enabled') && ! ($saleSettings['enabled'] ?? false) && $request->input('operation') !== 'authorize_sale') {
                throw ValidationException::withMessages(['sale_enabled' => 'Guarda el producto y utiliza Habilitar venta solo cuando completes los requisitos comerciales.']);
            }
            if (($request->boolean('pdf_final') || ($giftChanged && $request->boolean('gift_pdf_final'))) && ! $request->hasFile('pdf') && ! $sale->pdfVerified($product)) {
                throw ValidationException::withMessages(['pdf' => 'Sube un PDF privado válido antes de confirmarlo como definitivo.']);
            }
            if ($request->boolean('sale_enabled')) {
                if (! $request->boolean('pdf_final')) throw ValidationException::withMessages(['pdf_final' => 'Confirma el PDF comercial definitivo antes de habilitar la venta.']);
                if (! app(\App\Services\DigitalCollection::class)->readyFor($product->currency)) throw ValidationException::withMessages(['sale_enabled' => 'Configura y autoriza la cuenta exclusiva para la moneda del producto antes de habilitar la venta.']);
                foreach (['public','member'] as $tier) {
                    if (! $request->boolean($tier.'_qr_confirmed') && ! app(\App\Services\DigitalProductQr::class)->verified($product, $tier)) throw ValidationException::withMessages([$tier.'_qr_confirmed' => 'Confirma este QR para su precio, moneda y cuenta autorizada antes de habilitar la venta.']);
                }
            }
            $stored = $request->hasFile('pdf') ? $files->store($request->file('pdf'), 'products') : null;
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
            if ($saleChanged) {
                app(\App\Services\DigitalSaleAvailability::class)->configure($product, $request->boolean('pdf_final'), $request->boolean('sale_enabled'), $request->user()->id);
            }
            if ($giftChanged) {
                app(\App\Services\DigitalGifts::class)->configure($product, $request->boolean('welcome_gift_enabled'), $request->boolean('promotional_gift_enabled'), $request->boolean('gift_pdf_final'));
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
