<?php

namespace App\Services;
use App\Models\{DigitalProduct, LandingPresentation, Partner};
use Illuminate\Support\Facades\DB;

/** Explicit, idempotent release operation for the existing product. Never creates products or sales. */
class GluteoPerfectoVisibility
{
    public function publish(bool $requireLiveIds = true): array
    {
        return DB::transaction(function () use ($requireLiveIds) {
            $partner = Partner::where('slug', 'jakawi-digital')->lockForUpdate()->sole();
            $product = DigitalProduct::where('slug', 'gluteo-perfecto')->lockForUpdate()->sole();
            $landing = LandingPresentation::where('slug', 'gluteo-perfecto')->lockForUpdate()->sole();
            abort_unless($partner->name === 'JAKAWI Digital' && $product->partner_id === $partner->id && $product->name === 'Glúteo Perfecto' && $landing->subject_type === $product->getMorphClass() && $landing->subject_id === $product->id, 409);
            abort_unless(! $requireLiveIds || [$partner->id, $product->id, $landing->id] === [29, 1, 24], 409);
            abort_unless($product->public_price === null && $product->member_price === null && ! $product->file_path && count($landing->editorial_blocks ?? []) === 11, 409);
            $blocks = $landing->editorial_blocks;
            foreach ($blocks as &$block) {
                if ($block['type'] === 'hero') {
                    $block['availability'] = 'COMING_SOON';
                    $block['primary_label'] = 'CONOCER LA GUÍA';
                }
                if ($block['type'] === 'offer') $block['primary_label'] = 'Ver detalles de la guía';
                if ($block['type'] === 'final') $block['primary_label'] = 'VER DETALLES';
            }
            $product->fill(['status' => 'published']);
            if ($product->isDirty()) $product->save();
            $landing->fill(['status' => 'PUBLISHED', 'default_scope' => 'ALL', 'publish_scope' => 'ALL', 'editorial_blocks' => $blocks]);
            if ($landing->isDirty()) $landing->save();
            return ['product_id' => $product->id, 'landing_id' => $landing->id, 'public' => true, 'coming_soon' => true, 'purchasable' => false];
        });
    }
}
