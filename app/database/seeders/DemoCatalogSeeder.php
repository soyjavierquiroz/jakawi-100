<?php

namespace Database\Seeders;

use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    /** @var array<int, int|null> */
    private const LIMITS = [
        1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
        1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
        2, 2, 2, 2, 2,
        null, null, null, null, null,
    ];

    public function run(): void
    {
        $merchants = [
            ['slug' => 'demo-altura-nube-cafe', 'name' => 'Altura Nube Café', 'category' => 'coffee', 'address' => 'Pasaje Nube 120'],
            ['slug' => 'demo-brasa-prisma', 'name' => 'Brasa Prisma', 'category' => 'food', 'address' => 'Avenida Prisma 245'],
            ['slug' => 'demo-nomada-bocado', 'name' => 'Nómada Bocado', 'category' => 'food', 'address' => 'Calle Sendero 88'],
            ['slug' => 'demo-verde-paramo', 'name' => 'Verde Páramo', 'category' => 'food', 'address' => 'Plaza Páramo 16'],
            ['slug' => 'demo-casa-azafran', 'name' => 'Casa Azafrán', 'category' => 'food', 'address' => 'Calle Azafrán 310'],
            ['slug' => 'demo-kintu-calma-spa', 'name' => 'Kintu Calma Spa', 'category' => 'wellness', 'address' => 'Avenida Calma 54'],
            ['slug' => 'demo-distrito-pulso', 'name' => 'Distrito Pulso', 'category' => 'sport', 'address' => 'Calle Pulso 73'],
            ['slug' => 'demo-luna-lateral', 'name' => 'Luna Lateral', 'category' => 'experience', 'address' => 'Mirador Lateral 9'],
            ['slug' => 'demo-miga-cometa', 'name' => 'Miga Cometa', 'category' => 'bakery', 'address' => 'Pasaje Cometa 41'],
            ['slug' => 'demo-andes-umbral', 'name' => 'Andes Umbral', 'category' => 'experience', 'address' => 'Ruta Umbral 18'],
            ['slug' => 'demo-pixel-pinos-bowling', 'name' => 'Pixel Pinos Bowling', 'category' => 'experience', 'address' => 'Avenida Pinos 222'],
            ['slug' => 'demo-gelato-bruma', 'name' => 'Gelato Bruma', 'category' => 'dessert', 'address' => 'Calle Bruma 61'],
        ];

        foreach ($merchants as $position => $merchant) {
            Merchant::query()->updateOrCreate(
                ['slug' => $merchant['slug']],
                [
                    ...$merchant,
                    'short_description' => "Comercio ficticio de prueba en Cochabamba: {$merchant['name']}.",
                    'description' => "{$merchant['name']} es un comercio completamente ficticio creado para probar el catálogo de JAKAWI.",
                    'city' => 'Cochabamba',
                    'instagram' => null,
                    'whatsapp' => null,
                    'logo_path' => null,
                    'cover_path' => null,
                    'is_active' => true,
                    'is_featured' => false,
                    'sort_order' => $position + 1,
                ],
            );
        }

        foreach ($this->benefits() as $position => $benefit) {
            $merchant = Merchant::query()->where('slug', $benefit['merchant_slug'])->firstOrFail();

            Benefit::query()->updateOrCreate(
                ['slug' => $benefit['slug']],
                [
                    'merchant_id' => $merchant->id,
                    'title' => $benefit['title'],
                    'short_description' => $benefit['short_description'],
                    'description' => "Beneficio ficticio para navegación y QA en {$merchant->name}. No representa una oferta comercial real.",
                    'terms' => 'Dato ficticio de QA. No válido para canjes ni asociado a comercios reales.',
                    'benefit_type' => $benefit['benefit_type'],
                    'estimated_savings' => $benefit['estimated_savings'],
                    'image_path' => null,
                    'is_active' => true,
                    'is_featured' => in_array($position, [0, 3, 7, 14, 22], true),
                    'redemption_limit_per_member' => self::LIMITS[$position],
                    'starts_at' => '2026-09-01 00:00:00',
                    'ends_at' => '2027-03-31 23:59:59',
                    'sort_order' => $position + 1,
                ],
            );
        }
    }

    /** @return array<int, array<string, string|int>> */
    private function benefits(): array
    {
        return [
            ['merchant_slug' => 'demo-altura-nube-cafe', 'slug' => 'demo-altura-nube-cafe-cafe-frio-2x1', 'title' => 'Café frío 2x1', 'short_description' => 'Dos cafés fríos por el precio de uno.', 'benefit_type' => '2x1', 'estimated_savings' => 20],
            ['merchant_slug' => 'demo-altura-nube-cafe', 'slug' => 'demo-altura-nube-cafe-pasteleria-15', 'title' => '15% en pastelería', 'short_description' => 'Descuento en una selección de pastelería.', 'benefit_type' => 'discount', 'estimated_savings' => 15],
            ['merchant_slug' => 'demo-altura-nube-cafe', 'slug' => 'demo-altura-nube-cafe-bebida-incluida', 'title' => 'Bebida incluida con brunch', 'short_description' => 'Una bebida de cortesía al pedir brunch.', 'benefit_type' => 'free_item', 'estimated_savings' => 10],
            ['merchant_slug' => 'demo-brasa-prisma', 'slug' => 'demo-brasa-prisma-parrilla-20', 'title' => '20% en parrilla para dos', 'short_description' => 'Ahorro en un plato ficticio para compartir.', 'benefit_type' => 'discount', 'estimated_savings' => 40],
            ['merchant_slug' => 'demo-brasa-prisma', 'slug' => 'demo-brasa-prisma-entrada-regalo', 'title' => 'Entrada incluida', 'short_description' => 'Entrada de cortesía con plato principal.', 'benefit_type' => 'free_item', 'estimated_savings' => 15],
            ['merchant_slug' => 'demo-brasa-prisma', 'slug' => 'demo-brasa-prisma-menu-ejecutivo-15', 'title' => 'Bs 15 de ahorro en menú ejecutivo', 'short_description' => 'Ahorro fijo en un menú ficticio.', 'benefit_type' => 'discount', 'estimated_savings' => 15],
            ['merchant_slug' => 'demo-nomada-bocado', 'slug' => 'demo-nomada-bocado-burger-2x1', 'title' => 'Burger clásica 2x1', 'short_description' => 'Dos hamburguesas ficticias por el precio de una.', 'benefit_type' => '2x1', 'estimated_savings' => 30],
            ['merchant_slug' => 'demo-nomada-bocado', 'slug' => 'demo-nomada-bocado-combo-25', 'title' => '25% en combo nómada', 'short_description' => 'Descuento en un combo de prueba.', 'benefit_type' => 'discount', 'estimated_savings' => 35],
            ['merchant_slug' => 'demo-nomada-bocado', 'slug' => 'demo-nomada-bocado-postre-incluido', 'title' => 'Postre incluido', 'short_description' => 'Postre de cortesía con combo.', 'benefit_type' => 'free_item', 'estimated_savings' => 10],
            ['merchant_slug' => 'demo-verde-paramo', 'slug' => 'demo-verde-paramo-bowl-20', 'title' => '20% en bowl de temporada', 'short_description' => 'Descuento en bowl ficticio.', 'benefit_type' => 'discount', 'estimated_savings' => 25],
            ['merchant_slug' => 'demo-verde-paramo', 'slug' => 'demo-verde-paramo-jugo-regalo', 'title' => 'Jugo incluido', 'short_description' => 'Jugo de cortesía con ensalada.', 'benefit_type' => 'free_item', 'estimated_savings' => 15],
            ['merchant_slug' => 'demo-casa-azafran', 'slug' => 'demo-casa-azafran-cena-dos', 'title' => 'Experiencia para dos', 'short_description' => 'Menú ficticio para compartir.', 'benefit_type' => 'experience', 'estimated_savings' => 50],
            ['merchant_slug' => 'demo-casa-azafran', 'slug' => 'demo-casa-azafran-curry-15', 'title' => '15% en curry de la casa', 'short_description' => 'Descuento en especialidad ficticia.', 'benefit_type' => 'discount', 'estimated_savings' => 20],
            ['merchant_slug' => 'demo-kintu-calma-spa', 'slug' => 'demo-kintu-calma-spa-masaje-20', 'title' => '20% en masaje de pausa', 'short_description' => 'Descuento en sesión ficticia.', 'benefit_type' => 'discount', 'estimated_savings' => 40],
            ['merchant_slug' => 'demo-kintu-calma-spa', 'slug' => 'demo-kintu-calma-spa-facial-regalo', 'title' => 'Facial express incluido', 'short_description' => 'Complemento de cortesía en reserva.', 'benefit_type' => 'free_item', 'estimated_savings' => 30],
            ['merchant_slug' => 'demo-distrito-pulso', 'slug' => 'demo-distrito-pulso-clase-prueba', 'title' => 'Clase de prueba', 'short_description' => 'Clase ficticia para conocer el estudio.', 'benefit_type' => 'experience', 'estimated_savings' => 20],
            ['merchant_slug' => 'demo-distrito-pulso', 'slug' => 'demo-distrito-pulso-plan-25', 'title' => '25% en plan mensual', 'short_description' => 'Descuento en plan ficticio.', 'benefit_type' => 'discount', 'estimated_savings' => 50],
            ['merchant_slug' => 'demo-distrito-pulso', 'slug' => 'demo-distrito-pulso-agua-incluida', 'title' => 'Bebida incluida', 'short_description' => 'Bebida de cortesía después de clase.', 'benefit_type' => 'free_item', 'estimated_savings' => 10],
            ['merchant_slug' => 'demo-luna-lateral', 'slug' => 'demo-luna-lateral-cocteles-2x1', 'title' => 'Cócteles 2x1', 'short_description' => 'Dos cócteles ficticios por el precio de uno.', 'benefit_type' => '2x1', 'estimated_savings' => 30],
            ['merchant_slug' => 'demo-luna-lateral', 'slug' => 'demo-luna-lateral-reserva-20', 'title' => 'Bs 20 de ahorro en reserva', 'short_description' => 'Ahorro fijo en experiencia nocturna.', 'benefit_type' => 'discount', 'estimated_savings' => 20],
            ['merchant_slug' => 'demo-miga-cometa', 'slug' => 'demo-miga-cometa-medialunas-2x1', 'title' => 'Medialunas 2x1', 'short_description' => 'Dos porciones por el precio de una.', 'benefit_type' => '2x1', 'estimated_savings' => 15],
            ['merchant_slug' => 'demo-miga-cometa', 'slug' => 'demo-miga-cometa-caja-15', 'title' => '15% en caja dulce', 'short_description' => 'Descuento en una caja ficticia.', 'benefit_type' => 'discount', 'estimated_savings' => 25],
            ['merchant_slug' => 'demo-andes-umbral', 'slug' => 'demo-andes-umbral-ruta-dos', 'title' => 'Experiencia de ruta para dos', 'short_description' => 'Salida ficticia para compartir.', 'benefit_type' => 'experience', 'estimated_savings' => 50],
            ['merchant_slug' => 'demo-andes-umbral', 'slug' => 'demo-andes-umbral-pase-20', 'title' => '20% en pase de día', 'short_description' => 'Descuento en pase ficticio.', 'benefit_type' => 'discount', 'estimated_savings' => 30],
            ['merchant_slug' => 'demo-pixel-pinos-bowling', 'slug' => 'demo-pixel-pinos-bowling-linea-2x1', 'title' => 'Línea de bowling 2x1', 'short_description' => 'Dos líneas ficticias por el precio de una.', 'benefit_type' => '2x1', 'estimated_savings' => 35],
            ['merchant_slug' => 'demo-pixel-pinos-bowling', 'slug' => 'demo-pixel-pinos-bowling-snack-incluido', 'title' => 'Snack incluido', 'short_description' => 'Snack de cortesía en grupo.', 'benefit_type' => 'free_item', 'estimated_savings' => 15],
            ['merchant_slug' => 'demo-gelato-bruma', 'slug' => 'demo-gelato-bruma-helado-20', 'title' => '20% en helado doble', 'short_description' => 'Descuento en helado ficticio.', 'benefit_type' => 'discount', 'estimated_savings' => 20],
            ['merchant_slug' => 'demo-gelato-bruma', 'slug' => 'demo-gelato-bruma-postre-dos', 'title' => 'Postre para dos', 'short_description' => 'Experiencia dulce ficticia para compartir.', 'benefit_type' => 'experience', 'estimated_savings' => 40],
            ['merchant_slug' => 'demo-gelato-bruma', 'slug' => 'demo-gelato-bruma-sabor-regalo', 'title' => 'Sabor adicional incluido', 'short_description' => 'Complemento de cortesía en copa.', 'benefit_type' => 'free_item', 'estimated_savings' => 10],
            ['merchant_slug' => 'demo-gelato-bruma', 'slug' => 'demo-gelato-bruma-copa-15', 'title' => '15% en copa compartida', 'short_description' => 'Descuento en copa ficticia para dos.', 'benefit_type' => 'discount', 'estimated_savings' => 25],
        ];
    }
}
