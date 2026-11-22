<?php

namespace App\Services;

use App\Models\{AuditLog, DigitalAccessGrant, DigitalGift, DigitalProduct, Membership, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class DigitalGifts
{
    public function enabled(): bool
    {
        return config('digital_gifts.enabled') && ! config('jakawi.prelaunch_mode');
    }

    public function assertEnabled(): void
    {
        abort_unless($this->enabled(), 403, 'Los regalos digitales todavía no están habilitados.');
    }

    // Called inside the existing activation transaction, including when model events are suppressed.
    public function firstActivation(Membership $membership): void
    {
        if (! $this->enabled() || $membership->status !== Membership::STATUS_ACTIVE) return;
        if (Membership::where('user_id', $membership->user_id)->where('id', '<>', $membership->id)->exists()) return;
        DigitalGift::firstOrCreate(['kind' => 'welcome', 'user_id' => $membership->user_id], [
            'membership_id' => $membership->id, 'granted_at' => now(),
        ]);
    }

    public function pending(?User $user): ?DigitalGift
    {
        return $user && $this->enabled()
            ? DigitalGift::where('user_id', $user->id)->where('kind', 'welcome')->where('status', 'pending')->first() : null;
    }

    public function catalogQuery($query)
    {
        // Final hash is captured separately from commercial sale authorization.
        return $query->where('welcome_gift_enabled', true)->whereNotNull('gift_pdf_sha256')
            ->whereColumn('gift_pdf_sha256', 'file_sha256')->whereNotNull('file_path');
    }

    public function eligible(DigitalProduct $product, string $kind): bool
    {
        return (bool) $product->getAttribute($kind === 'welcome' ? 'welcome_gift_enabled' : 'promotional_gift_enabled')
            && $product->partner?->slug === 'jakawi-digital'
            && DigitalProduct::published()->whereKey($product->id)->exists()
            && filled($product->gift_pdf_sha256) && $product->gift_pdf_sha256 === $product->file_sha256
            && app(DigitalSaleAvailability::class)->pdfVerified($product);
    }

    public function configure(DigitalProduct $product, bool $welcome, bool $promotional, bool $final): void
    {
        if ($final) abort_unless(app(DigitalSaleAvailability::class)->pdfVerified($product), 422, 'Sube y verifica el PDF privado definitivo antes de autorizar regalos.');
        abort_if(($welcome || $promotional) && ! $final, 422, 'Confirma el PDF definitivo para habilitar regalos.');
        $product->update(['welcome_gift_enabled' => $welcome, 'promotional_gift_enabled' => $promotional,
            'gift_pdf_sha256' => $final ? $product->file_sha256 : null]);
    }

    public function owned(?User $user, DigitalProduct $product): ?DigitalAccessGrant
    {
        return $user ? DigitalAccessGrant::where('user_id', $user->id)->where('digital_product_id', $product->id)
            ->where('status', 'active')->where('delivery_status', 'ready')->first() : null;
    }

    public function context(Request $request, DigitalProduct $product): array
    {
        $owned = $this->owned($request->user(), $product);
        if ($owned) return ['kind' => 'owned', 'url' => '/mi-jakawi/productos-digitales/accesos/'.$owned->id.'/pdf'];
        $invitation = $this->invitation($request);
        if ($invitation && $invitation->digital_product_id === $product->id && $this->eligible($product, 'promotional')) {
            return ['kind' => 'promotional', 'authenticated' => $request->user() !== null, 'url' => $request->user() ? '/productos-digitales/'.$product->slug.'/regalo-promocional' : '/regalos-digitales/continuar'];
        }
        if ($this->pending($request->user()) && $request->user()->hasActiveMembership() && $this->eligible($product, 'welcome')) {
            return ['kind' => 'welcome', 'url' => '/productos-digitales/'.$product->slug.'/regalo-bienvenida'];
        }
        return ['kind' => null];
    }

    public function invitation(Request $request): ?DigitalGift
    {
        $hash = $request->session()->get('digital_gift_hash');
        if (! $this->enabled() || ! is_string($hash)) return null;
        return DigitalGift::where('token_hash', $hash)->where('kind', 'promotional')->where('status', 'pending')
            ->where('expires_at', '>', now())->when($request->user(), fn ($q) => $q->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $request->user()->id)))->first();
    }

    public function issue(DigitalProduct $product, User $admin, ?User $recipient, string $key): array
    {
        $this->assertEnabled();
        // Serialize issuer retries. Token is returned once; only its hash is persisted.
        return DB::transaction(function () use ($product, $admin, $recipient, $key) {
            User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            abort_if(DigitalGift::where('issuance_key', $key)->exists(), 409, 'Esta invitación ya fue creada. Conserva el enlace copiado.');
            $product = DigitalProduct::lockForUpdate()->findOrFail($product->id);
            abort_unless($this->eligible($product, 'promotional'), 422, 'El producto no está preparado y autorizado para regalar.');
            $token = bin2hex(random_bytes(32));
            $gift = DigitalGift::create(['kind' => 'promotional', 'user_id' => $recipient?->id, 'digital_product_id' => $product->id,
                'created_by' => $admin->id, 'issuance_key' => $key, 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(max(1, min(90, (int) config('digital_gifts.invitation_days')))), 'granted_at' => now()]);
            $this->audit($admin->id, 'digital_gift_issued', $gift);
            return ['url' => url('/regalos-digitales/invitacion/'.$token), 'expires_at' => $gift->expires_at->toIso8601String()];
        });
    }

    public function claim(User $user, DigitalProduct $product, ?string $tokenHash = null): DigitalAccessGrant
    {
        $this->assertEnabled();
        return DB::transaction(function () use ($user, $product, $tokenHash) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $q = DigitalGift::query();
            if ($tokenHash !== null) $q->where('kind', 'promotional')->where('token_hash', $tokenHash);
            else $q->where('kind', 'welcome')->where('user_id', $user->id);
            $gift = $q->lockForUpdate()->first();
            abort_unless($gift && $gift->status === 'pending', 409, 'Este regalo no está disponible o ya fue reclamado.');
            abort_unless($gift->user_id === null || $gift->user_id === $user->id, 403);
            if ($gift->kind === 'welcome') abort_unless($user->hasActiveMembership(), 403, 'Necesitas una membresía activa para elegir tu regalo.');
            else abort_unless($gift->expires_at->isFuture() && $gift->digital_product_id === $product->id, 410, 'La invitación venció o no corresponde a este producto.');
            $product = DigitalProduct::lockForUpdate()->findOrFail($product->id);
            abort_unless($this->eligible($product, $gift->kind), 422, 'El producto no está preparado y autorizado para regalar.');
            abort_if($this->owned($user, $product), 422, 'Ya tienes este producto en tu biblioteca.');
            $grant = DigitalAccessGrant::create([
                'user_id' => $user->id, 'digital_product_id' => $product->id, 'source' => 'gift', 'status' => 'active',
                'delivery_type' => 'pdf', 'delivery_status' => 'ready', 'granted_at' => now(), 'delivered_at' => now(),
                'gift_file_path' => $product->file_path, 'gift_file_sha256' => $product->file_sha256,
                'gift_product_slug' => $product->slug, 'gift_product_name' => $product->name,
            ]);
            $gift->update(['status' => 'claimed', 'user_id' => $user->id, 'digital_product_id' => $product->id,
                'digital_access_grant_id' => $grant->id, 'claimed_at' => now()]);
            $this->audit($user->id, 'digital_gift_claimed', $gift);
            return $grant;
        }, 3);
    }

    private function audit(int $actor, string $action, DigitalGift $gift): void
    {
        AuditLog::create(['actor_user_id' => $actor, 'action' => $action, 'subject_type' => DigitalGift::class,
            'subject_id' => $gift->id, 'metadata' => ['kind' => $gift->kind, 'product_id' => $gift->digital_product_id, 'user_id' => $gift->user_id]]);
    }
}
