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
    public function index(Request $request, DigitalCatalog $catalog)
    {
        return Inertia::render('admin/digital/products', app(\App\Services\DigitalAdminCatalog::class)->listing($request, $catalog));
    }

    public function edit(DigitalCatalog $catalog, ?DigitalProduct $product = null)
    {
        if ($product) {
            $catalog->assertInternal($product);
        }

        return Inertia::render('admin/digital/product-form', ['product' => $product ? $product->toArray() + ['gift_pdf_final' => filled($product->gift_pdf_sha256) && $product->gift_pdf_sha256 === $product->file_sha256, 'coming_soon' => $product->isComingSoon(), 'use_shared_qr' => app(\App\Services\DigitalProductQr::class)->usesShared($product), 'qrs' => app(\App\Services\DigitalProductQr::class)->adminState($product), 'sale' => app(\App\Services\DigitalSaleAvailability::class)->adminState($product), 'has_file' => filled($product->file_path), 'gallery_urls' => array_map(fn ($path) => app(\App\Services\MediaUrl::class)->url($path, 'thumbnail'), $product->gallery_paths ?? [])] : null, 'partner' => $catalog->internalPartner()->only('id', 'name'), 'categoryLabels' => config('jakawi.category_labels'), 'hostedApps' => array_keys(app(\App\Services\HostedApps::class)->registry())]);
    }

    public function save(Request $request, DigitalCatalog $catalog, DigitalFiles $files, ?DigitalProduct $product = null)
    {
        if ($product) {
            $catalog->assertInternal($product);
        }
        // Older clients serialized optional undefined values as blank multipart fields.
        foreach (['sale_enabled', 'pdf_final'] as $optional) {
            if ($request->exists($optional) && ($request->input($optional) === null || $request->input($optional) === '')) $request->request->remove($optional);
        }
        $data = $request->validate([
            'public_qr' => 'nullable|image|mimes:png,jpg,jpeg|max:10240', 'member_qr' => 'nullable|image|mimes:png,jpg,jpeg|max:10240',
            'public_qr_confirmed' => 'sometimes|boolean', 'member_qr_confirmed' => 'sometimes|boolean',
            'welcome_gift_enabled' => 'sometimes|boolean', 'promotional_gift_enabled' => 'sometimes|boolean', 'gift_pdf_final' => 'sometimes|boolean',
            'operation' => 'sometimes|in:save,authorize_sale,publish_sale', 'use_shared_qr'=>'sometimes|boolean',
            'pdf_final' => 'sometimes|boolean', 'sale_enabled' => 'sometimes|boolean',
            'name' => 'required|string|max:255', 'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('digital_products', 'slug')->ignore($product?->id)],
            'short_description' => 'nullable|string|max:1000', 'description' => 'nullable|string|max:30000', 'commercial_content' => 'nullable|string|max:30000',
            'status' => 'required|in:draft,published', 'public_price' => 'nullable|decimal:0,2|gt:0|max:99999999.99',
            'member_price' => 'nullable|decimal:0,2|gt:0|lte:public_price', 'currency' => 'required|in:BOB,USD',
            'support_information' => 'nullable|string|max:3000', 'access_terms' => 'nullable|string|max:10000',
            'pdf' => 'nullable|file|mimes:pdf|max:25600', 'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'category' => ['nullable', Rule::in(config('jakawi.digital_categories'))],
            'gallery' => 'nullable|array|max:8', 'gallery.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            'gallery_clear' => 'sometimes|boolean', 'gallery_keep' => 'nullable|array|max:8', 'gallery_keep.*' => ['string', Rule::in($product?->gallery_paths ?? [])],
            'partner_id' => 'prohibited', 'delivery_type' => 'sometimes|in:pdf,hosted_app', 'app_key' => ['nullable', Rule::in(array_keys(app(\App\Services\HostedApps::class)->registry()))],
        ]);
        $hosted = ($data['delivery_type'] ?? $product?->delivery_type) === 'hosted_app';
        if ($hosted && empty($data['app_key'] ?? $product?->app_key)) throw ValidationException::withMessages(['app_key' => 'Elige una APP registrada.']);
        if ($request->input('operation') !== 'publish_sale' && $data['status'] === 'published' && ! ($hosted && $product?->status === 'published' && $request->input('operation', 'save') === 'save') && ! $product?->isComingSoon()) {
            Validator::make($data, ['public_price' => 'required', 'description' => 'required', 'support_information' => 'required', 'access_terms' => 'required'])->validate();
            if (! ($request->hasFile('cover') || $product?->cover_path)) throw ValidationException::withMessages(['cover' => 'Sube la portada antes de publicar.']);
            if (! $hosted && ! ($request->hasFile('pdf') || $product?->file_path)) throw ValidationException::withMessages(['pdf' => 'Sube el PDF privado antes de publicar.']);
        }
        $keep = $request->boolean('gallery_clear') ? [] : ($data['gallery_keep'] ?? ($product?->gallery_paths ?? []));
        if (count($keep) + count($request->file('gallery', [])) > 8) throw ValidationException::withMessages(['gallery' => 'La galería admite hasta 8 imágenes entre las conservadas y las nuevas.']);
        $publishing = $request->input('operation') === 'publish_sale';
        if ($publishing) $data['status'] = $product?->status ?? 'draft';
        unset($data['operation'], $data['use_shared_qr']);
        unset($data['welcome_gift_enabled'], $data['promotional_gift_enabled'], $data['gift_pdf_final']);
        unset($data['public_qr'], $data['member_qr'], $data['public_qr_confirmed'], $data['member_qr_confirmed'], $data['pdf_final'], $data['sale_enabled'], $data['pdf'],$data['cover'],$data['gallery'],$data['gallery_keep'],$data['gallery_clear']);
        $publishMissing = [];
        DB::transaction(function () use ($request, $data, $files, $catalog, $keep, $publishing, &$publishMissing, &$product) {
            if ($product) $product = DigitalProduct::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $oldMedia = array_filter([$product?->cover_path, ...($product?->gallery_paths ?? [])]);
            $wasPublished = $product?->status === 'published';
            $product ??= new DigitalProduct;
            $deliveryChanged = $product->exists && (($data['delivery_type'] ?? $product->delivery_type) !== $product->delivery_type || ($data['app_key'] ?? $product->app_key) !== $product->app_key);
            if (array_diff($keep, $product->gallery_paths ?? [])) throw ValidationException::withMessages(['gallery_keep'=>'La galería cambió. Recarga antes de conservar imágenes anteriores.']);
            $product->fill($data);
            $hosted = $product->delivery_type === 'hosted_app';
            if ($hosted && $product->status === 'published' && (! $wasPublished || $request->input('operation') === 'authorize_sale') && ! app(\App\Services\HostedApps::class)->ready($product)) throw ValidationException::withMessages(['app_key' => 'La APP debe estar registrada, funcional y protegida.']);
            $product->partner_id = $catalog->internalPartner()->id;
            if ($request->input('operation') === 'authorize_sale' && ($request->hasFile('pdf') || $request->hasFile('cover') || $request->hasFile('gallery') || $request->hasFile('public_qr') || $request->hasFile('member_qr'))) throw ValidationException::withMessages(['sale_enabled' => 'Primero guarda los archivos con Guardar producto. Después habilita la venta con las referencias ya guardadas.']);
            // Validate administrative intent before writing any object to MinIO.
            \App\Models\AppSetting::where('key', \App\Services\DigitalCollection::KEY)->lockForUpdate()->first();
            foreach (['public','member'] as $tier) {
                $qrService = app(\App\Services\DigitalProductQr::class);
                if (! $request->boolean('use_shared_qr') && ($request->hasFile($tier.'_qr') || ($request->has($tier.'_qr_confirmed') && $request->boolean($tier.'_qr_confirmed') !== $qrService->verified($product, $tier)))) {
                    $qrService->validateSave($product, $tier, $request->file($tier.'_qr'), $request->boolean($tier.'_qr_confirmed'));
                }
            }
            if ($request->boolean('public_qr_confirmed') && $request->boolean('member_qr_confirmed') && $request->hasFile('public_qr') && $request->hasFile('member_qr') && $product->public_price !== $product->member_price
                && hash_equals(hash_file('sha256', $request->file('public_qr')->getRealPath()), hash_file('sha256', $request->file('member_qr')->getRealPath()))) {
                throw ValidationException::withMessages(['member_qr' => 'Un mismo QR de importe fijo no puede confirmarse para dos importes diferentes. Sube el QR bancario correspondiente.']);
            }
            $giftFinal = filled($product->gift_pdf_sha256) && $product->gift_pdf_sha256 === $product->file_sha256;
            $giftChanged = $request->has('gift_pdf_final') && (
                $request->boolean('gift_pdf_final') !== $giftFinal
                || $request->boolean('welcome_gift_enabled') !== (bool) $product->welcome_gift_enabled
                || $request->boolean('promotional_gift_enabled') !== (bool) $product->promotional_gift_enabled
                || ($request->hasFile('pdf') && ($giftFinal || $request->boolean('gift_pdf_final')))
            );
            if (! $hosted && $giftChanged && ($request->boolean('welcome_gift_enabled') || $request->boolean('promotional_gift_enabled')) && ! $request->boolean('gift_pdf_final')) {
                throw ValidationException::withMessages(['gift_pdf_final' => 'Confirma el PDF definitivo para cambiar la autorización de regalos. Puedes guardar sin modificar los permisos de regalo actuales.']);
            }
            $sale = app(\App\Services\DigitalSaleAvailability::class);
            $saleSettings = $product->exists ? $sale->settings($product) : [];
            $saleChanged = $request->input('operation') === 'authorize_sale' || $deliveryChanged || ($request->has('sale_enabled') || $request->has('pdf_final')) && (
                ($request->has('sale_enabled') && $request->boolean('sale_enabled') !== ($saleSettings['enabled'] ?? false))
                || $request->boolean('pdf_final') !== ($hosted ? (($saleSettings['app_key'] ?? null) === $product->app_key) : (filled($saleSettings['pdf_sha256'] ?? null) && ($saleSettings['pdf_sha256'] ?? null) === $product->file_sha256))
                || $request->hasFile('pdf')
            );
            if ($request->boolean('sale_enabled') && ! ($saleSettings['enabled'] ?? false) && ! in_array($request->input('operation'), ['authorize_sale','publish_sale'], true)) {
                throw ValidationException::withMessages(['sale_enabled' => 'Guarda el producto y utiliza Habilitar venta solo cuando completes los requisitos comerciales.']);
            }
            if (! $hosted && ($request->boolean('pdf_final') || ($giftChanged && $request->boolean('gift_pdf_final'))) && ! $request->hasFile('pdf') && ! $sale->pdfVerified($product)) {
                throw ValidationException::withMessages(['pdf' => 'Sube un PDF privado válido antes de confirmarlo como definitivo.']);
            }
            if ($request->input('operation') === 'authorize_sale') {
                if (! $request->boolean('pdf_final')) throw ValidationException::withMessages(['pdf_final' => 'Confirma la entrega definitiva antes de habilitar la venta.']);
                if (! app(\App\Services\DigitalCollection::class)->readyFor($product->currency)) throw ValidationException::withMessages(['sale_enabled' => 'Configura y autoriza la cuenta para la moneda del producto antes de habilitar la venta.']);
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
            if ($request->has('use_shared_qr') && ! $request->boolean('use_shared_qr')) app(\App\Services\DigitalProductQr::class)->setShared($product, false);
            foreach (['public','member'] as $tier) {
                if (! $request->boolean('use_shared_qr') && ($request->hasFile($tier.'_qr') || ($request->has($tier.'_qr_confirmed') && $request->boolean($tier.'_qr_confirmed') !== app(\App\Services\DigitalProductQr::class)->verified($product, $tier)))) {
                    app(\App\Services\DigitalProductQr::class)->save($product, $tier, $request->file($tier.'_qr'), $request->boolean($tier.'_qr_confirmed'), $request->user()->id);
                }
            }
            if ($request->has('use_shared_qr')) app(\App\Services\DigitalProductQr::class)->setShared($product, $request->boolean('use_shared_qr'));
            if ($publishing) $publishMissing = $sale->publishAndSell($product, $request->user()->id, $request->boolean('pdf_final'));
            if ($saleChanged && ! $publishing) {
                app(\App\Services\DigitalSaleAvailability::class)->configure($product, $request->boolean('pdf_final'), $request->input('operation') === 'authorize_sale', $request->user()->id);
            }
            if ($giftChanged) {
                app(\App\Services\DigitalGifts::class)->configure($product, $request->boolean('welcome_gift_enabled'), $request->boolean('promotional_gift_enabled'), $request->boolean('gift_pdf_final'));
            }
            app(\App\Services\DigitalAssetCleanup::class)->afterCommit(config('media.disk'), $oldMedia);
            AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'digital_product_saved', 'subject_type' => DigitalProduct::class, 'subject_id' => $product->id, 'metadata' => ['status' => $product->status, 'file_sha256' => $product->file_sha256]]);
        });

        if ($publishMissing) return back()->withErrors(['sale_enabled'=>implode(' ', $publishMissing)])->with('success', 'Datos guardados. Venta pendiente de requisitos.');
        return redirect('/admin/productos-digitales')->with('success', $publishing ? 'Producto publicado y venta activada.' : 'Producto guardado.');
    }

    public function qr(DigitalProduct $product, string $tier, DigitalCatalog $catalog, DigitalFiles $files)
    {
        $catalog->assertInternal($product);
        $path = app(\App\Services\DigitalProductQr::class)->resolved($product, $tier)['qr_path'] ?? null;
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
