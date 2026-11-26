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
        return app(DigitalAdminSnapshot::class)->setting($this->key($product));
    }

    public const SHARED_PREFIX = 'digital_shared_qr_v1.';

    public function usesShared(DigitalProduct $product): bool
    {
        return $this->settings($product)['use_shared'] ?? (empty($this->settings($product)['public']['qr_path']) && empty($this->settings($product)['member']['qr_path']));
    }

    private function sharedKey(string $amount, string $currency, array $account): string
    {
        return self::SHARED_PREFIX.hash('sha256', implode('|', [$account['account_key'], $account['version'], $currency, DigitalCheckout::cents($amount)]));
    }

    public function resolved(DigitalProduct $product, string $tier): array
    {
        $local = $this->settings($product)[$tier] ?? [];
        // Existing specific configurations keep their mode until the owner switches explicitly.
        if (! $this->usesShared($product)) return $local;
        $account = app(DigitalCollection::class)->current();
        $price = $this->price($product, $tier);
        if (! $account || ! is_numeric($price)) return [];
        $key = $this->sharedKey($price, $product->currency, $account);
        $qr = app(DigitalAdminSnapshot::class)->setting($key);
        return $qr ? $qr + ['shared_key'=>$key] : [];
    }

    private function sharedValid(array $qr): bool
    {
        if (empty($qr['shared_key'])) return true;
        $current = app(DigitalAdminSnapshot::class)->setting($qr['shared_key']);
        if (empty($current['revision']) || ($current['revision'] ?? null) !== ($qr['revision'] ?? null) || empty($current['enabled']) || empty($current['bank_reuse_confirmed'])) return false;
        if (! empty($current['valid_until']) && now()->gte(\Carbon\Carbon::parse($current['valid_until']))) return false;
        // Owner confirmation declares reuse; the software verifies binding and integrity, not bank policy.
        if (($current['confirmation_mode'] ?? null) === 'owner') return filled($current['verified_by'] ?? null) && filled($current['verified_at'] ?? null);
        try {
            return hash_equals($current['evidence_sha256'], hash('sha256', Storage::disk('digital-private')->get($current['evidence_path'])));
        } catch (\Throwable) { return false; }
    }

    public function saveShared(array $data, UploadedFile $file, int $adminId): void
    {
        DB::transaction(function () use ($data, $file, $adminId) {
            AppSetting::where('key', DigitalCollection::KEY)->lockForUpdate()->first();
            $account = app(DigitalCollection::class)->current();
            if (! $account || $account['currency'] !== $data['currency']) throw ValidationException::withMessages(['shared_qr'=>'Autoriza primero la cuenta receptora para esta moneda.']);
            $amount = number_format((float)$data['amount'], 2, '.', '');
            // Reuse the existing fixed-amount/hash checks across product and shared resources.
            $probe = new DigitalProduct(['currency'=>$data['currency'], 'public_price'=>$amount]);
            $this->validateSave($probe, 'public', $file, true);
            $stored = app(DigitalFiles::class)->store($file, 'qr');
            $key = $this->sharedKey($amount, $data['currency'], $account);
            $value = ['amount'=>$amount, 'currency'=>$data['currency'], 'account_key'=>$account['account_key'], 'version'=>$account['version'],
                'qr_path'=>$stored['path'], 'qr_sha256'=>$stored['sha256'],
                'confirmation_mode'=>'owner', 'bank_reuse_confirmed'=>true, 'enabled'=>true, 'valid_until'=>null,
                'revision'=>(string)\Illuminate\Support\Str::uuid(), 'verified_by'=>$adminId, 'verified_at'=>now()->toIso8601String()];
            $oldPath = AppSetting::where('key',$key)->first()?->value['qr_path'] ?? null;
            AppSetting::updateOrCreate(['key'=>$key], ['value'=>$value]);
            if ($oldPath) app(DigitalAssetCleanup::class)->afterCommit('digital-private', [$oldPath]);
            \App\Models\AuditLog::create(['actor_user_id'=>$adminId, 'action'=>'digital_shared_qr_owner_confirmed', 'subject_type'=>AppSetting::class,
                'subject_id'=>AppSetting::where('key',$key)->value('id'), 'metadata'=>['amount'=>$amount,'currency'=>$data['currency'],'revision'=>$value['revision']]]);
        });
    }

    public function sharedAdminState(): array
    {
        $account = app(DigitalCollection::class)->current();
        if (! $account || $account['currency'] !== 'BOB') return [];
        $state = [];
        foreach (['public'=>'49.00', 'member'=>'29.00'] as $tier=>$amount) {
            $setting = AppSetting::where('key', $this->sharedKey($amount, 'BOB', $account))->first();
            if (! $setting) continue;
            $qr = $setting->value + ['shared_key'=>$setting->key];
            $state[] = ['id'=>$setting->id, 'tier'=>$tier, 'amount'=>$amount, 'currency'=>'BOB',
                'enabled'=>$qr['enabled'] ?? false, 'current'=>$this->sharedValid($qr) && $this->intact($qr),
                'preview_url'=>url('/admin/digital/cobro/qr-compartido/'.$setting->id.'/qr').'?v='.($qr['revision'] ?? '')];
        }
        return $state;
    }

    public function setShared(DigitalProduct $product, bool $enabled): void
    {
        $settings = $this->settings($product);
        $oldPaths = [];
        if ($enabled) {
            foreach (['public','member'] as $tier) { $oldPaths[] = $settings[$tier]['qr_path'] ?? null; unset($settings[$tier]); }
        }
        $settings['use_shared'] = $enabled;
        AppSetting::updateOrCreate(['key'=>$this->key($product)], ['value'=>$settings]);
        app(DigitalAssetCleanup::class)->afterCommit('digital-private', $oldPaths);
    }

    public function sharedMissing(DigitalProduct $product): array
    {
        $probe = clone $product;
        $snapshot = app(DigitalAdminSnapshot::class);
        // Resolve the actual shared resources without changing product settings or files.
        $account = app(DigitalCollection::class)->current();
        $missing = [];
        foreach (['public', 'member'] as $tier) {
            $price = $this->price($probe, $tier);
            $qr = $account && is_numeric($price) ? $snapshot->setting($key = $this->sharedKey($price, $product->currency, $account)) : [];
            if ($qr) $qr['shared_key'] = $key;
            if (! $this->verified($probe, $tier, $qr)) $missing[] = "QR compartido $tier incompatible con importe, moneda o cuenta actual.";
        }
        return $missing;
    }

    private function price(DigitalProduct $product, string $tier): ?string
    {
        return $tier === 'public' ? $product->public_price : $product->member_price;
    }

    private function intact(array $qr): bool
    {
        $snapshot = app(DigitalAdminSnapshot::class);
        if ($snapshot->active) {
            $key = hash('sha256', json_encode($qr));
            return $snapshot->integrity[$key] ??= $this->checkIntact($qr);
        }
        return $this->checkIntact($qr);
    }

    private function checkIntact(array $qr): bool
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
        $qr ??= $this->resolved($product, $tier);
        $account = app(DigitalCollection::class)->current();
        $price = $this->price($product, $tier);
        return $account !== null && filled($qr['verified_by'] ?? null) && filled($qr['verified_at'] ?? null)
            && is_numeric($price) && DigitalCheckout::cents($price) > 0
            && ($qr['amount'] ?? null) === $price && ($qr['currency'] ?? null) === $product->currency
            && $account['currency'] === $product->currency
            && ($qr['account_key'] ?? null) === $account['account_key']
            && ($qr['version'] ?? null) === $account['version'] && $this->sharedValid($qr) && $this->intact($qr);
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
        $other = $this->resolved($product, $otherTier);
        $hash = $file ? hash_file('sha256', $file->getRealPath()) : ($this->settings($product)[$tier]['qr_sha256'] ?? null);
        if ($hash && ($other['qr_sha256'] ?? null) === $hash && ($other['amount'] ?? null) !== null && $other['amount'] !== $price) {
            throw ValidationException::withMessages([$tier.'_qr' => 'Este mismo QR de importe fijo ya está asociado a otro importe. Sube el QR bancario correspondiente; no hay evidencia de que admita ambos.']);
        }
        if ($hash) {
            foreach (AppSetting::where(fn ($q) => $q->where('key', 'like', 'digital_product_qr_v1.%')->orWhere('key','like',self::SHARED_PREFIX.'%'))->get() as $setting) {
                foreach (str_starts_with($setting->key, self::SHARED_PREFIX) ? [$setting->value] : $setting->value as $stored) {
                    if (! is_array($stored)) continue;
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
        $oldPath = $settings[$tier]['qr_path'] ?? null;
        $settings['use_shared'] = false;
        $settings[$tier] = $qr;
        AppSetting::updateOrCreate(['key'=>$this->key($product)], ['value'=>$settings]);
        if ($oldPath && $oldPath !== ($qr['qr_path'] ?? null)) app(DigitalAssetCleanup::class)->afterCommit('digital-private', [$oldPath]);
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
        $settings = [];
        $state = [];
        foreach (['public','member'] as $tier) {
            $settings[$tier] = $this->resolved($product, $tier);
            $state[$tier] = ['has_file'=>filled($settings[$tier]['qr_path'] ?? null),
                'verified'=>$this->verified($product, $tier, $settings[$tier] ?? []), 'shared'=>!empty($settings[$tier]['shared_key']),
                'amount'=>$this->price($product, $tier), 'currency'=>$product->currency];
        }
        return $state;
    }

    public function snapshot(DigitalProduct $product, bool $member): array
    {
        $tier = $member ? 'member' : 'public';
        $qr = $this->resolved($product, $tier);
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
            && ($snapshot['account_key'] ?? null) === $account['account_key'] && $this->sharedValid($snapshot) && $this->intact($snapshot);
    }
}
