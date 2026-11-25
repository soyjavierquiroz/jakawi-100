<?php

namespace App\Services;
use App\Models\{AppSetting, DigitalProduct};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Individual commercial authorization and verified product requirements. */
class DigitalSaleAvailability
{
    public const KEY = 'gluteo_perfecto_sale_v1';

    public function eligible(DigitalProduct $product): bool
    {
        if ($product->app_key !== 'gluteos' && $product->delivery_type === 'hosted_app' && $product->partner?->slug === 'jakawi-digital') return true;
        return $product->slug === 'gluteo-perfecto' && $product->partner?->slug === 'jakawi-digital';
    }

    public function key(DigitalProduct $product): string
    {
        return $product->app_key === 'gluteos' || $product->slug === 'gluteo-perfecto' ? self::KEY : 'digital_product_sale_v1.'.$product->id;
    }

    public function settings(DigitalProduct $product): array
    {
        $value = AppSetting::where('key', $this->key($product))->first()?->value ?? [];
        return ($value['product_id'] ?? null) === $product->id ? $value : [];
    }

    public function pdfVerified(DigitalProduct $product): bool
    {
        try {
            $disk = Storage::disk('digital-private');
            return filled($product->file_path) && filled($product->file_sha256)
                && str_starts_with($product->file_path, 'products/')
                && $disk->exists($product->file_path)
                && hash_equals($product->file_sha256, hash('sha256', $disk->get($product->file_path)));
        } catch (\Throwable) {
            return false;
        }
    }

    public function missing(DigitalProduct $product, ?array $settings = null): array
    {
        $settings ??= $this->settings($product);
        $missing = [];
        if (! $this->eligible($product)) $missing[] = 'La autorización comercial requiere un producto compatible de JAKAWI Digital.';
        if (! DigitalProduct::published()->whereKey($product->id)->exists()) $missing[] = 'Publicar el producto y su Partner.';
        if (! is_numeric($product->public_price) || DigitalCheckout::cents((string) $product->public_price) <= 0) $missing[] = 'Configurar el precio público definitivo.';
        if (! is_numeric($product->member_price) || (float) $product->member_price <= 0 || (float) $product->member_price > (float) $product->public_price) $missing[] = 'Configurar el precio miembro definitivo, menor o igual al público.';
        if ($product->delivery_type === 'hosted_app') {
            if (! app(HostedApps::class)->ready($product)) $missing[] = 'Registrar y verificar la APP protegida en JAKAWI.STORE.';
            if (($settings['app_key'] ?? null) !== $product->app_key) $missing[] = 'Autorizar individualmente esta APP para compradores.';
        } else {
            if (! $this->pdfVerified($product)) $missing[] = 'Subir y verificar el PDF privado.';
            if (empty($settings['pdf_sha256']) || $settings['pdf_sha256'] !== $product->file_sha256) $missing[] = 'Confirmar que el PDF actual es el definitivo para compradores.';
        }
        if (! app(DigitalCollection::class)->readyFor($product->currency)) $missing[] = 'Configurar y autorizar la cuenta en Cobro digital.';
        if ($product->delivery_type === 'hosted_app' && $product->app_key !== 'gluteos') {
            foreach (['description'=>'Completar la descripción.', 'support_information'=>'Completar el soporte.', 'access_terms'=>'Completar las condiciones de acceso.', 'cover_path'=>'Subir la portada.'] as $field=>$message) {
                if (blank($product->$field)) $missing[]=$message;
            }
        }
        return array_merge($missing, app(DigitalProductQr::class)->missing($product));
    }

    public function canSell(DigitalProduct $product): bool
    {
        $settings = $this->settings($product);
        return ($settings['enabled'] ?? false) === true && $this->missing($product, $settings) === [];
    }

    public function configure(DigitalProduct $product, bool $final, bool $enabled, int $adminId): void
    {
        abort_unless($this->eligible($product), 422, 'Producto no compatible con autorización comercial.');
        $settings = ['product_id' => $product->id, 'pdf_sha256' => $final && $this->pdfVerified($product) ? $product->file_sha256 : null,
            'app_key' => $final && $product->delivery_type === 'hosted_app' ? $product->app_key : null,
            'enabled' => $enabled, 'authorized_by' => $adminId, 'authorized_at' => now()->toIso8601String()];
        if ($enabled && ($missing = $this->missing($product, $settings))) {
            throw ValidationException::withMessages(['sale_enabled' => implode(' ', $missing)]);
        }
        AppSetting::updateOrCreate(['key' => $this->key($product)], ['value' => $settings]);
    }

    public function adminState(DigitalProduct $product): array
    {
        $settings = $this->settings($product);
        return ['eligible' => $this->eligible($product), 'enabled' => $settings['enabled'] ?? false,
            'available' => $this->canSell($product), 'pdf_verified' => $this->pdfVerified($product),
            'pdf_final' => $product->delivery_type === 'hosted_app' ? (($settings['app_key'] ?? null) === $product->app_key) : (filled($settings['pdf_sha256'] ?? null) && $settings['pdf_sha256'] === $product->file_sha256),
            'missing' => $this->missing($product)];
    }
}
