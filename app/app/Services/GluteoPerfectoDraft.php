<?php

namespace App\Services;

use App\Models\DigitalProduct;
use App\Models\LandingPresentation;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;

/** Explicit release operation only; never runs on requests, migrations, or application startup. */
class GluteoPerfectoDraft
{
    public function create(bool $requirePartner29 = true): array
    {
        return DB::transaction(function () use ($requirePartner29) {
            $partner = Partner::where('slug', 'jakawi-digital')->lockForUpdate()->sole();
            abort_unless($partner->name === 'JAKAWI Digital' && (! $requirePartner29 || $partner->id === 29), 409);
            $product = DigitalProduct::where('slug', 'gluteo-perfecto')->lockForUpdate()->first();
            if ($product) {
                abort_unless($product->partner_id === $partner->id && $product->name === 'Glúteo Perfecto' && $product->status === 'draft', 409);
            } else {
                $product = DigitalProduct::create(['partner_id' => $partner->id, 'slug' => 'gluteo-perfecto', 'name' => 'Glúteo Perfecto', 'category' => 'fitness', 'status' => 'draft', 'currency' => 'BOB', 'delivery_type' => 'pdf', 'short_description' => 'Guía digital para organizar tu entrenamiento de glúteos en casa, con técnica y progresión a tu ritmo.', 'description' => 'Glúteo Perfecto propone una guía de entrenamiento para fortalecer los glúteos y acompañar hábitos sostenibles. La entrega inicial prevista es un PDF privado. Contenido definitivo pendiente de revisión del archivo. Los resultados varían entre personas.']);
            }
            $landing = LandingPresentation::where('slug', 'gluteo-perfecto')->first();
            if ($landing) {
                abort_unless($landing->subject_type === $product->getMorphClass() && $landing->subject_id === $product->id && $landing->status === 'DRAFT', 409);
            } else {
                $landing = $product->landingPresentations()->create(['name' => 'Glúteo Perfecto · editorial', 'slug' => 'gluteo-perfecto', 'status' => 'DRAFT', 'default_scope' => 'NONE', 'publish_scope' => 'NONE', 'template_key' => 'OFFER_CONVERSION', 'marketing_theme' => 'LIGHT_CONVERSION', 'media_mode' => 'NONE', 'hero_layout' => 'OFFER_FIRST', 'density' => 'STANDARD', 'editorial_version' => 1, 'eyebrow' => 'GLÚTEOS ESCULPIDOS COMPROBADO', 'headline' => 'Glúteo Perfecto', 'subheadline' => $product->short_description, 'final_cta_headline' => 'Tienes 2 caminos ahora', 'editorial_blocks' => json_decode(file_get_contents(resource_path('content/gluteo-perfecto.json')), true, 512, JSON_THROW_ON_ERROR)]);
            }
            return ['product_id' => $product->id, 'landing_id' => $landing->id, 'status' => $product->status, 'edit' => route('admin.digital.edit', $product), 'product_preview' => route('admin.digital.preview', $product), 'landing_edit' => '/admin/landings/'.$landing->slug.'/edit', 'landing_preview' => '/admin/productos-digitales/'.$product->slug.'/landings/'.$landing->slug.'/preview'];
        });
    }
}
