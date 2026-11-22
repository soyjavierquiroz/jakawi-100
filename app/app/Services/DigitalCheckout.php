<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DigitalAccessGrant;
use App\Models\DigitalOrder;
use App\Models\DigitalPayment;
use App\Models\DigitalProduct;
use App\Models\User;
use App\Support\PrelaunchOperations;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DigitalCheckout
{
    private function transaction(callable $work): mixed
    {
        try {
            return DB::transaction($work);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages(['payment' => 'El comprobante o ingreso bancario ya está asignado. Actualiza el pedido.']);
            }
            throw $e;
        }
    }

    public static function cents(string $value): int
    {
        [$a,$b] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $a) * 100 + (int) str_pad(substr($b, 0, 2), 2, '0');
    }

    public function audit(User $actor, string $action, DigitalOrder $order, array $metadata = []): void
    {
        AuditLog::create(['actor_user_id' => $actor->id, 'action' => $action, 'subject_type' => DigitalOrder::class, 'subject_id' => $order->id, 'metadata' => $metadata]);
    }

    public function start(User $user, DigitalProduct $product): DigitalOrder
    {
        PrelaunchOperations::assertAllowed();

        return $this->transaction(function () use ($user, $product) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $product = DigitalProduct::published()->lockForUpdate()->findOrFail($product->id);
            app(DigitalCatalog::class)->assertInternal($product);
            abort_if($product->isComingSoon(), 423, 'Disponible próximamente. Las compras de este producto están desactivadas.');
            \App\Models\AppSetting::where('key', DigitalCollection::KEY)->lockForUpdate()->first();
            abort_unless(app(DigitalCollection::class)->readyFor($product->currency), 422, 'Cobro pendiente de configurar');
            $existing = DigitalOrder::where('user_id', $user->id)->where('digital_product_id', $product->id)->where('status', 'pending')->lockForUpdate()->first();
            if ($existing && ($existing->expires_at->isFuture() || $existing->payments()->where('status', 'under_review')->exists())) {
                return $existing;
            }
            if ($existing) {
                $existing->update(['status' => 'expired']);
            }
            abort_if(DigitalAccessGrant::where('user_id', $user->id)->where('digital_product_id', $product->id)->where('status', 'active')->exists(), 422, 'Ya tienes este producto en tu biblioteca.');
            abort_unless($product->public_price && $product->file_path && Storage::disk('digital-private')->exists($product->file_path), 422, 'Producto pendiente de preparar.');
            $membership = $user->activeMembership()->lockForUpdate()->first();
            $order = DigitalOrder::create(['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'digital_product_id' => $product->id, 'partner_id' => $product->partner_id, 'product_name' => $product->name, 'product_slug' => $product->slug, 'public_price' => $product->public_price, 'amount' => $membership && $product->member_price !== null ? $product->member_price : $product->public_price, 'currency' => $product->currency, 'member_at_purchase' => $membership !== null, 'membership_id' => $membership?->id, 'file_path' => $product->file_path, 'file_sha256' => $product->file_sha256, 'access_terms' => $product->access_terms, 'support_information' => $product->support_information, 'collection_snapshot' => app(DigitalCollection::class)->current(), 'status' => 'pending', 'expires_at' => now()->addHours(24)]);
            $this->audit($user, 'digital_order_started', $order);
            app(DigitalMeasurement::class)->record('digital_checkout_started', $order, $order);
            app(DigitalMeasurement::class)->record('digital_order_pending', $order, $order);

            return $order;
        });
    }

    public function report(DigitalOrder $order, User $user, UploadedFile $receipt): DigitalPayment
    {
        PrelaunchOperations::assertAllowed();

        return $this->transaction(function () use ($order, $user, $receipt) {
            $order = DigitalOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless($order->user_id === $user->id, 403);
            abort_unless($order->status === 'pending' && $order->expires_at->isFuture(), 422, 'Pedido vencido o ya procesado.');
            \App\Models\AppSetting::where('key', DigitalCollection::KEY)->lockForUpdate()->first();
            abort_unless(app(DigitalCollection::class)->readyFor($order->currency) && app(DigitalCollection::class)->current()['version'] === $order->collection_snapshot['version'], 422, 'La configuración de cobro cambió. Contacta con soporte.');
            $hash = hash_file('sha256', $receipt->getRealPath());
            $existing = DigitalPayment::where('receipt_sha256', $hash)->first();
            if ($existing) {
                abort_unless($existing->digital_order_id === $order->id, 422, 'Comprobante ya presentado para otro pedido.');

                return $existing;
            }
            abort_if($order->payments()->where('status', 'under_review')->exists(), 422, 'Ya hay un comprobante en revisión.');
            $file = app(DigitalFiles::class)->store($receipt, 'receipts');
            $payment = $order->payments()->create(['receipt_path' => $file['path'], 'receipt_sha256' => $file['sha256'], 'status' => 'under_review']);
            $this->audit($user, 'digital_payment_reported', $order, ['payment_id' => $payment->id]);

            return $payment;
        });
    }

    public function confirm(DigitalPayment $payment, User $admin, array $evidence): DigitalOrder
    {
        PrelaunchOperations::assertAllowed();
        abort_unless($admin->is_admin, 403);

        return $this->transaction(function () use ($payment, $admin, $evidence) {
            $order = DigitalOrder::lockForUpdate()->findOrFail($payment->digital_order_id);
            $payment = DigitalPayment::lockForUpdate()->findOrFail($payment->id);
            $reference = mb_strtoupper(trim($evidence['bank_reference'] ?? ''));
            if ($payment->status === 'confirmed') {
                abort_unless($reference === $payment->bank_reference, 409, 'Ya confirmado con otra referencia bancaria.');

                return $order;
            }
            abort_unless($order->status === 'pending' && $payment->status === 'under_review', 422, 'Pago no disponible para confirmar.');
            abort_unless(($evidence['bank_verified'] ?? false) && $reference !== '' && ! empty($evidence['bank_date']) && ! empty($evidence['statement']) && ($evidence['account_key'] ?? null) === $order->collection_snapshot['account_key'], 422, 'Verifica el abono real y adjunta evidencia bancaria.');
            abort_unless(($evidence['currency'] ?? null) === $order->currency && self::cents((string) ($evidence['received_amount'] ?? '0')) === self::cents($order->amount), 422, 'Importe o moneda no coinciden con el pedido.');
            abort_if(($evidence['bank_date'] ?? '') < $order->created_at->timezone('America/La_Paz')->toDateString(), 422, 'El ingreso precede al pedido.');
            abort_if(DigitalPayment::where('bank_account_key', $order->collection_snapshot['account_key'])->where('bank_reference', $reference)->exists(), 422, 'Ingreso bancario ya utilizado.');
            $file = app(DigitalFiles::class)->store($evidence['statement'], 'statements');
            $payment->update(['status' => 'confirmed', 'bank_account_key' => $order->collection_snapshot['account_key'], 'bank_reference' => $reference, 'bank_date' => $evidence['bank_date'], 'received_amount' => $order->amount, 'currency' => $order->currency, 'statement_path' => $file['path'], 'statement_sha256' => $file['sha256'], 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            $grant = DigitalAccessGrant::firstOrCreate(['digital_order_id' => $order->id], ['user_id' => $order->user_id, 'digital_product_id' => $order->digital_product_id, 'status' => 'active', 'delivery_status' => 'pending', 'granted_at' => now()]);
            $this->audit($admin, 'digital_payment_confirmed', $order, ['payment_id' => $payment->id, 'bank_reference' => $reference, 'statement_sha256' => $file['sha256']]);
            app(DigitalMeasurement::class)->record('digital_purchase_confirmed', $order, $order);
            $this->deliver($grant, $admin);

            return $order->refresh();
        });
    }

    public function deliver(DigitalAccessGrant $grant, User $admin): void
    {
        PrelaunchOperations::assertAllowed();
        abort_unless($admin->is_admin, 403);
        $this->transaction(function () use ($grant, $admin) {
            $order = DigitalOrder::lockForUpdate()->findOrFail($grant->digital_order_id);
            $grant = DigitalAccessGrant::lockForUpdate()->findOrFail($grant->id);
            abort_unless($order->status === 'confirmed' && $grant->status === 'active', 422);
            if ($grant->delivery_status === 'ready') {
                return;
            }
            $valid = false;
            try {
                $disk = Storage::disk('digital-private');
                $valid = $disk->exists($order->file_path) && hash_equals($order->file_sha256, hash('sha256', $disk->get($order->file_path)));
            } catch (\Throwable) {
            }
            $grant->update(['delivery_attempts' => $grant->delivery_attempts + 1, 'delivery_status' => $valid ? 'ready' : 'failed', 'delivery_error' => $valid ? null : 'PDF_UNAVAILABLE', 'delivered_at' => $valid ? now() : null]);
            $this->audit($admin, $valid ? 'digital_access_granted' : 'digital_delivery_failed', $order, ['grant_id' => $grant->id]);
            if ($valid) {
                app(DigitalMeasurement::class)->record('digital_access_granted', $grant, $order);
            }
        });
    }

    public function reject(DigitalPayment $payment, User $admin, string $reason): void
    {
        PrelaunchOperations::assertAllowed();
        abort_unless($admin->is_admin, 403);
        $this->transaction(function () use ($payment, $admin, $reason) {
            $order = DigitalOrder::lockForUpdate()->findOrFail($payment->digital_order_id);
            $payment = DigitalPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'rejected') {
                return;
            }
            abort_unless($order->status === 'pending' && $payment->status === 'under_review' && trim($reason) !== '', 422);
            $payment->update(['status' => 'rejected', 'review_note' => $reason, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            $this->audit($admin, 'digital_payment_rejected', $order, ['payment_id' => $payment->id, 'reason' => $reason]);
        });
    }

    public function refund(DigitalOrder $order, User $admin, string $reference, string $reason): void
    {
        PrelaunchOperations::assertAllowed();
        abort_unless($admin->is_admin, 403);
        $this->transaction(function () use ($order, $admin, $reference, $reason) {
            $order = DigitalOrder::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'refunded') {
                return;
            }
            abort_unless($order->status === 'confirmed' && trim($reference) !== '' && trim($reason) !== '', 422);
            $payment = $order->payments()->where('status', 'confirmed')->lockForUpdate()->firstOrFail();
            $payment->update(['status' => 'refunded', 'refund_reference' => $reference, 'refund_amount' => $order->amount, 'refunded_by' => $admin->id]);
            $grant = $order->access()->lockForUpdate()->firstOrFail();
            $grant->update(['status' => 'revoked', 'revoked_at' => now()]);
            $order->update(['status' => 'refunded', 'refunded_at' => now()]);
            $this->audit($admin,'digital_refund_recorded',$order,['reference' => $reference, 'reason' => $reason, 'amount' => $order->amount]);
        });
    }
}
