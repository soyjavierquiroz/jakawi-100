<?php

namespace App\Services;

use App\Models\DigitalProduct;
use App\Models\LandingPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DigitalProductLandingPresenter
{
    public function assertPublishable(DigitalProduct $product): void
    {
        if ($product->isComingSoon() && DigitalProduct::published()->whereKey($product->id)->exists() && filled($product->description)) return;
        if (! DigitalProduct::published()->whereKey($product->id)->exists() || ! $product->public_price || ! $product->description || ! $product->support_information || ! $product->access_terms || ! $product->cover_path || ! $product->file_path || ! Storage::disk('digital-private')->exists($product->file_path)) {
            throw ValidationException::withMessages(['presentation' => 'Completa y publica el producto: precio aprobado, portada, PDF privado, descripción, soporte y condiciones.']);
        }
    }

    public function present(DigitalProduct $product, LandingPresentation $presentation, Request $request, bool $preview = true): array
    {
        $giftAction = $preview ? ['kind' => null] : app(DigitalGifts::class)->context($request, $product);
        $comingSoon = $product->isComingSoon();
        $prelaunch = (bool) config('jakawi.prelaunch_mode');
        $canBuy = ! $preview && app(DigitalSaleAvailability::class)->canSell($product);
        return [
            'giftAction' => $giftAction,
            'product' => app(DigitalCatalog::class)->present($product),
            'presentation' => $presentation->only(['id', 'name', 'slug', 'status', 'hero_alt', 'hero_layout', 'density', 'marketing_theme', 'media_mode']),
            'copy' => ['eyebrow' => $comingSoon ? 'Disponible próximamente' : ($canBuy ? 'Disponible' : ($presentation->eyebrow ?: 'Producto digital')), 'headline' => $presentation->headline ?: $product->name, 'subheadline' => $presentation->subheadline ?: $product->short_description, 'finalHeadline' => $presentation->final_cta_headline ?: 'Da el primer paso'],
            'blocks' => app(LandingEditorialBlocks::class)->present($presentation->editorial_blocks ?? [], $preview, $product->landingPresentations()->where('status', 'PUBLISHED')->whereJsonContains('editorial_blocks', [['type' => 'hero', 'availability' => 'COMING_SOON']])->exists()),
            'heroUrl' => app(MediaUrl::class)->url($presentation->hero_path ?: $product->cover_path, 'hero'),
            'vsl' => app(LandingVideoConfiguration::class)->forPresentation($presentation),
            'preview' => $preview, 'noindex' => $preview || $prelaunch, 'prelaunch' => $prelaunch,
            'authenticated' => $request->user() !== null, 'canBuy' => (bool) $canBuy,
            'memberActive' => $request->user()?->hasActiveMembership() ?? false,
            'checkoutUrl' => route('digital.orders.start', $product, false),
            'notice' => $giftAction['kind'] ? ($giftAction['kind'] === 'owned' ? 'Este producto ya está en tu biblioteca digital.' : 'Confirma tu regalo para guardarlo en Mis productos digitales.') : ($comingSoon ? 'Disponible próximamente. Presentación editorial en revisión; las compras y la entrega todavía no están habilitadas.' : ($preview ? 'Vista previa editorial. Las compras están desactivadas.' : ($prelaunch && ! $canBuy ? 'Estamos en prelanzamiento. Las compras todavía no están habilitadas.' : ($canBuy ? 'Pago por QR de JAKAWI Digital, con verificación bancaria. Descarga privada después de confirmar el pago.' : 'La compra todavía no está disponible.')))),
        ];
    }
}
