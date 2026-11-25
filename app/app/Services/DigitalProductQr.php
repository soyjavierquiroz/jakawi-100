<?php

namespace App\Services;

use App\Models\{AppSetting, DigitalOrder, DigitalProduct};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/** Bank-issued, reusable fixed-amount images. Never generates payment QR codes. */
class DigitalProductQr
{
    public function key(DigitalProduct $product): string
    {
        return 'digital_product_qr_v1.'.$product->id;
    }

    public function settings(DigitalProduct $product): array
    {
        return AppSetting::where('key', $this->key($product))->first()?->value ?? [];
    }

    private function price(DigitalProduct $product, string $tier): ?string
    {
        return $tier === 'public' ? $product->public_price : $product->member_price;
    }

    private function intact(array $qr): bool
    {
        try {
            $disk = Storage::disk('digital-private');
            return filled($qr['qr_path'] ?? null) && str_starts_with($qr['qr_path'], 'qr/')
                && filled($qr['qr_sha256'] ?? null) && $disk->exists($qr['qr_path'])
                && hash_equals($qr['qr_sha256'], hash('sha256', $disk->get($qr['qr_path'])));
        } catch (\Throwable) {
            return false;
        }
    }

    public function verified(DigitalProduct $product, string $tier, ?array $qr = null): bool
    {
        $qr ??= $this->settings($product)[$tier] ?? [];
        $account = app(DigitalCollection::class)->current();
        $price = $this->price($product, $tier);
        return $account !== null && filled($qr['verified_by'] ?? null) && filled($qr['verified_at'] ?? null)
            && is_numeric($price) && DigitalCheckout::cents($price) > 0
            && ($qr['amount'] ?? null) === $price && ($qr['currency'] ?? null) === $product->currency
            && $account['currency'] === $product->currency
            && ($qr['account_key'] ?? null) === $account['account_key']
            && ($qr['version'] ?? null) === $account['version'] && $this->intact($qr);
    }

    public function missing(DigitalProduct $product): array
    {
        $missing = [];
        foreach (['public'=>'público', 'member'=>'miembro'] as $tier=>$label) {
            if (! $this->verified($product, $tier)) $missing[] = "Cargar y confirmar el QR $label para su precio, moneda y cuenta actual.";
        }
        return $missing;
    }

    public function validateSave(DigitalProduct $product, string $tier, ?UploadedFile $file, bool $confirm): void
    {
        if ($file && ! in_array($file->getMimeType(), ['image/png','image/jpeg'], true)) {
            throw ValidationException::withMessages([$tier.'_qr' => 'Sube una imagen PNG o JPG emitida por el banco.']);
        }
        if (! $confirm) return;
        $account = app(DigitalCollection::class)->current();
        if (! $account) {
            throw ValidationException::withMessages([$tier.'_qr_confirmed' => 'Primero configura y autoriza la cuenta en Cobro digital. Puedes guardar el QR sin marcar esta confirmación.']);
        }
        if ($account['currency'] !== $product->currency) {
            throw ValidationException::withMessages([$tier.'_qr_confirmed' => 'La moneda del producto no coincide con la cuenta autorizada. Corrige la moneda o la cuenta antes de confirmar el QR.']);
        }
        $price = $this->price($product, $tier);
        if (! is_numeric($price) || DigitalCheckout::cents($price) <= 0) {
            throw ValidationException::withMessages([$tier.'_price' => 'Indica el importe fijo del QR antes de confirmarlo.']);
        }
        $otherTier = $tier === 'public' ? 'member' : 'public';
        $other = $this->settings($product)[$otherTier] ?? [];
        $hash = $file ? hash_file('sha256', $file->getRealPath()) : ($this->settings($product)[$tier]['qr_sha256'] ?? null);
        if ($hash && ($other['qr_sha256'] ?? null) === $hash && ($other['amount'] ?? null) !== null && $other['amount'] !== $price) {
            throw ValidationException::withMessages([$tier.'_qr' => 'Este mismo QR de importe fijo ya está asociado a otro importe. Sube el QR bancario correspondiente; no hay evidencia de que admita ambos.']);
        }
        if ($hash) {
            foreach (AppSetting::where('key', 'like', 'digital_product_qr_v1.%')->get() as $setting) {
                foreach ($setting->value as $stored) {
                    if (($stored['qr_sha256'] ?? null) === $hash && filled($stored['verified_by'] ?? null) && filled($stored['amount'] ?? null) && DigitalCheckout::cents((string)$stored['amount']) !== DigitalCheckout::cents($price)) {
                        throw ValidationException::withMessages([$tier.'_qr' => 'Este archivo QR ya fue confirmado para otro importe. Se requiere un QR bancario del importe actual.']);
                    }
                }
            }
        }
        if (! $file && ! $this->intact($this->settings($product)[$tier] ?? [])) {
            throw ValidationException::withMessages([$tier.'_qr' => 'Carga una imagen QR válida antes de confirmar. El archivo actual no está disponible o no supera la verificación de integridad.']);
        }
    }

    public function save(DigitalProduct $product, string $tier, ?UploadedFile $file, bool $confirm, int $adminId): void
    {
        abort_unless(in_array($tier, ['public', 'member'], true), 422);
        // Same lock order as checkout: product (caller), shared account, product QR settings.
        AppSetting::where('key', DigitalCollection::KEY)->lockForUpdate()->first();
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))', [$this->key($product)]);
        $setting = AppSetting::where('key', $this->key($product))->lockForUpdate()->first();
        $settings = $setting?->value ?? [];
        $qr = $settings[$tier] ?? [];
        $this->validateSave($product, $tier, $file, $confirm);
        if (! $file && ! $confirm && $qr === []) return;
        if ($file) {
            $stored = app(DigitalFiles::class)->store($file, 'qr');
            $qr = ['qr_path'=>$stored['path'], 'qr_sha256'=>$stored['sha256']];
        }
        if ($confirm) {
            $account = app(DigitalCollection::class)->current();
            $price = $this->price($product, $tier);
            if (! $this->intact($qr)) throw ValidationException::withMessages([$tier.'_qr' => 'No se pudo verificar la imagen QR guardada. Selecciónala de nuevo.']);
            $qr = array_merge($qr, ['amount'=>$price, 'currency'=>$product->currency,
                'account_key'=>$account['account_key'], 'version'=>$account['version'],
                'verified_by'=>$adminId, 'verified_at'=>now()->toIso8601String()]);
        } else {
            $qr['verified_by'] = null;
            $qr['verified_at'] = null;
        }
        $settings[$tier] = $qr;
        AppSetting::updateOrCreate(['key'=>$this->key($product)], ['value'=>$settings]);
    }

    public function invalidate(DigitalProduct $product, array $tiers): void
    {
        $setting = AppSetting::where('key', $this->key($product))->lockForUpdate()->first();
        if (! $setting) return;
        $settings = $setting->value;
        foreach ($tiers as $tier) {
            if (isset($settings[$tier])) {
                $settings[$tier]['verified_by'] = null;
                $settings[$tier]['verified_at'] = null;
            }
        }
        $setting->update(['value'=>$settings]);
    }

    public function adminState(DigitalProduct $product): array
    {
        $settings = $this->settings($product);
        $state = [];
        foreach (['public','member'] as $tier) {
            $state[$tier] = ['has_file'=>filled($settings[$tier]['qr_path'] ?? null),
                'verified'=>$this->verified($product, $tier, $settings[$tier] ?? []),
                'amount'=>$this->price($product, $tier), 'currency'=>$product->currency];
        }
        return $state;
    }

    public function snapshot(DigitalProduct $product, bool $member): array
    {
        $tier = $member ? 'member' : 'public';
        $qr = $this->settings($product)[$tier] ?? [];
        abort_unless($this->verified($product, $tier, $qr), 422, 'QR del producto pendiente de verificar.');
        return array_merge(app(DigitalCollection::class)->current(), $qr,
            ['qr_mode'=>'fixed_product_v1', 'qr_tier'=>$tier, 'digital_product_id'=>$product->id]);
    }

    public function orderReady(DigitalOrder $order): bool
    {
        $snapshot = $order->collection_snapshot;
        $account = app(DigitalCollection::class)->current();
        return $account !== null && ($snapshot['qr_mode'] ?? null) === 'fixed_product_v1'
            && ($snapshot['digital_product_id'] ?? null) === $order->digital_product_id
            && ($snapshot['qr_tier'] ?? null) === ($order->member_at_purchase ? 'member' : 'public')
            && ($snapshot['amount'] ?? null) === $order->amount && ($snapshot['currency'] ?? null) === $order->currency
            && $account['currency'] === $order->currency
            && ($snapshot['version'] ?? null) === $account['version']
            && ($snapshot['account_key'] ?? null) === $account['account_key'] && $this->intact($snapshot);
    }
}
