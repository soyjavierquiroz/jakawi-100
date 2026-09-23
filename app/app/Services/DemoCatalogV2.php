<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Deterministic fictional catalog for local and QA environments only. */
class DemoCatalogV2
{
    public const PIN = '123456'; // Demo-only; never reuse as a production secret.

    /** @return array<string, int> */
    public function seed(): array
    {
        return DB::transaction(function (): array {
            $partners = [];
            foreach ($this->partners() as $order => $data) {
                $slug = $data['slug'];
                $paths = ['logo_path' => "demo/partners/{$slug}-logo.svg", 'cover_path' => "demo/partners/{$slug}-cover.svg"];
                $partners[$slug] = Partner::updateOrCreate(['slug' => $slug], $data + $paths + ['sort_order' => $order + 1, 'status' => 'published', 'published_at' => now()]);
                $this->image($paths['logo_path'], $data['name'], $data['category'], true);
                $this->image($paths['cover_path'], $data['name'], $data['category']);
            }

            $locations = [];
            foreach ($this->locations() as $order => $data) {
                $slug = $data['slug'];
                $partner = ($data['partner_slug'] ?? null) ? $partners[$data['partner_slug']] : null;
                unset($data['partner_slug']);
                $data += ['partner_id' => $partner?->id, 'image_path' => "demo/locations/{$slug}.svg", 'sort_order' => $order + 1, 'status' => 'published', 'published_at' => now(), 'country_code' => 'BO', 'region' => 'Cochabamba', 'city' => 'Cochabamba', 'timezone' => 'America/La_Paz', 'google_place_id' => null];
                $locations[$slug] = Location::updateOrCreate(['slug' => $slug], $data);
                if ($partner !== null) {
                    $locations[$slug]->setRedemptionPin(self::PIN);
                    $locations[$slug]->save();
                }
                $this->image($data['image_path'], $data['name'], $data['location_type']);
            }

            foreach ($this->benefits() as $order => $data) {
                $partner = $partners[$data['partner_slug']];
                $locationSlugs = $data['location_slugs'];
                unset($data['partner_slug'], $data['location_slugs']);
                $slug = $data['slug'];
                $benefit = Benefit::updateOrCreate(['slug' => $slug], $data + ['partner_id' => $partner->id, 'image_path' => "demo/benefits/{$slug}.svg", 'sort_order' => $order + 1, 'status' => 'published', 'published_at' => now(), 'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonths(6)]);
                $benefit->syncLocations(array_map(fn ($locationSlug) => $locations[$locationSlug], $locationSlugs));
                $this->image($benefit->image_path, $benefit->title, $benefit->benefit_type);
            }

            $experiences = [];
            foreach ($this->experiences() as $order => $data) {
                $assignments = $data['partners'];
                unset($data['partners']);
                $slug = $data['slug'];
                $experience = $experiences[$slug] = Experience::updateOrCreate(['slug' => $slug], $data + ['image_path' => "demo/experiences/{$slug}.svg", 'cover_path' => "demo/experiences/{$slug}-cover.svg", 'sort_order' => $order + 1, 'status' => 'published', 'published_at' => now()]);
                $experience->syncPartnersWithRoles(array_map(fn ($item) => ['partner_id' => $partners[$item[0]]->id, 'role' => $item[1], 'sort_order' => $item[2]], $assignments));
                $this->image($experience->image_path, $experience->title, $experience->experience_type);
                $this->image($experience->cover_path, $experience->title, 'experiencia');
            }

            foreach ($this->sessions() as $data) {
                $experience = $experiences[$data['experience_slug']];
                $location = isset($data['location_slug']) ? $locations[$data['location_slug']] : null;
                $reservationPartner = isset($data['reservation_partner_slug']) ? $partners[$data['reservation_partner_slug']] : null;
                unset($data['experience_slug'], $data['location_slug'], $data['reservation_partner_slug']);
                ExperienceSession::updateOrCreate(['experience_id' => $experience->id, 'reference_key' => $data['reference_key']], $data + ['location_id' => $location?->id, 'reservation_partner_id' => $reservationPartner?->id, 'status' => 'scheduled']);
            }

            return $this->counts();
        });
    }

    /** @return array<string, int> */
    public function clear(): array
    {
        return DB::transaction(function (): array {
            $experiences = Experience::where('slug', 'like', 'demo-%')->get();
            $benefits = Benefit::where('slug', 'like', 'demo-%')->get();
            $locations = Location::where('slug', 'like', 'demo-%')->get();
            $partners = Partner::where('slug', 'like', 'demo-%')->get();
            $paths = $partners->flatMap(fn ($x) => [$x->logo_path, $x->cover_path])
                ->merge($locations->pluck('image_path'))
                ->merge($benefits->pluck('image_path'))
                ->merge($experiences->flatMap(fn ($x) => [$x->image_path, $x->cover_path]))
                ->filter(fn ($path) => is_string($path) && str_starts_with($path, 'demo/'));
            ExperienceSession::whereIn('experience_id', $experiences->pluck('id'))->delete();
            foreach ($benefits as $benefit) {
                $benefit->locations()->detach();
            }
            foreach ($experiences as $experience) {
                $experience->partners()->detach();
            }
            $benefits->each->delete();
            $experiences->each->delete();
            $locations->each->delete();
            $partners->each->delete();
            Storage::disk('public')->delete($paths->all());

            return $this->counts();
        });
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $experiences = Experience::where('slug', 'like', 'demo-%');

        return ['partners' => Partner::where('slug', 'like', 'demo-%')->count(), 'locations' => Location::where('slug', 'like', 'demo-%')->count(), 'benefits' => Benefit::where('slug', 'like', 'demo-%')->count(), 'experiences' => $experiences->count(), 'sessions' => ExperienceSession::whereIn('experience_id', $experiences->pluck('id'))->count()];
    }

    private function image(string $path, string $title, string $category, bool $square = false): void
    {
        $seed = hexdec(substr(md5($path), 0, 6));
        $a = sprintf('#%06X', $seed & 0xFFFFFF);
        $b = sprintf('#%06X', ($seed * 13) & 0xFFFFFF);
        $width = $square ? 600 : 1200;
        $height = 600;
        $safeTitle = htmlspecialchars(mb_strtoupper(mb_substr($title, 0, 34)), ENT_XML1);
        $safeCategory = htmlspecialchars($category, ENT_XML1);
        Storage::disk('public')->put($path, "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\"><defs><linearGradient id=\"g\" x2=\"1\" y2=\"1\"><stop stop-color=\"{$a}\"/><stop offset=\"1\" stop-color=\"{$b}\"/></linearGradient></defs><rect width=\"100%\" height=\"100%\" fill=\"url(#g)\"/><circle cx=\"85%\" cy=\"18%\" r=\"150\" fill=\"white\" opacity=\".18\"/><path d=\"M0 500 Q300 320 600 500 T1200 430 V600 H0Z\" fill=\"white\" opacity=\".16\"/><text x=\"64\" y=\"260\" fill=\"white\" font-family=\"sans-serif\" font-size=\"42\" font-weight=\"700\">{$safeTitle}</text><text x=\"66\" y=\"310\" fill=\"white\" font-family=\"sans-serif\" font-size=\"21\" letter-spacing=\"4\">DEMO · {$safeCategory}</text></svg>");
    }

    /** @return list<array<string, mixed>> */
    private function partners(): array
    {
        return [
            ['slug' => 'demo-altura-nube', 'name' => 'Altura Nube Café', 'entity_type' => 'organization', 'partner_type' => 'business', 'category' => 'cafe', 'description' => 'Café de altura, pan recién hecho y conversaciones lentas.', 'website' => 'https://demo.invalid/altura', 'instagram' => '@alturanube.demo', 'whatsapp' => '+59170010001', 'featured' => true],
            ['slug' => 'demo-brasa-prisma', 'name' => 'Brasa Prisma', 'entity_type' => 'organization', 'partner_type' => 'business', 'category' => 'food', 'description' => 'Cocina a las brasas con ingredientes de temporada.', 'website' => 'https://demo.invalid/brasa', 'phone' => '+59144010002', 'featured' => true],
            ['slug' => 'demo-nomada-bocado', 'name' => 'Nómada Bocado', 'entity_type' => 'organization', 'partner_type' => 'business', 'category' => 'food', 'description' => 'Sabores viajeros servidos cerca de casa.', 'whatsapp' => '+59170010003', 'featured' => false],
            ['slug' => 'demo-verde-paramo', 'name' => 'Verde Páramo', 'entity_type' => 'organization', 'partner_type' => 'business', 'category' => 'wellness', 'description' => 'Platos de estación y pausa consciente.', 'instagram' => '@verdeparamo.demo', 'featured' => true],
            ['slug' => 'demo-casa-azafran', 'name' => 'Casa Azafrán', 'entity_type' => 'organization', 'partner_type' => 'business', 'category' => 'food', 'description' => 'Mesa íntima, especias y sobremesa.', 'phone' => '+59144010005', 'featured' => false],
            ['slug' => 'demo-kintu-calma', 'name' => 'Kintu Calma Spa', 'entity_type' => 'organization', 'partner_type' => 'business', 'category' => 'wellness', 'description' => 'Rituales de bienestar inspirados en los Andes.', 'website' => 'https://demo.invalid/kintu', 'featured' => true],
            ['slug' => 'demo-distrito-pulso', 'name' => 'Distrito Pulso', 'entity_type' => 'organization', 'partner_type' => 'business', 'category' => 'fitness', 'description' => 'Movimiento, fuerza y comunidad.', 'whatsapp' => '+59170010007', 'featured' => false],
            ['slug' => 'demo-luna-lateral', 'name' => 'Luna Lateral', 'entity_type' => 'individual', 'partner_type' => 'creator', 'category' => 'beauty', 'description' => 'Estudio de imagen y belleza con mirada personal.', 'instagram' => '@lunalateral.demo', 'featured' => false],
            ['slug' => 'demo-miga-cometa', 'name' => 'Miga Cometa', 'entity_type' => 'organization', 'partner_type' => 'business', 'category' => 'cafe', 'description' => 'Panadería de masa madre para todos los días.', 'featured' => false],
            ['slug' => 'demo-andes-umbral', 'name' => 'Andes Umbral', 'entity_type' => 'individual', 'partner_type' => 'professional', 'category' => 'services', 'description' => 'Guías locales para caminar distinto la ciudad y la montaña.', 'whatsapp' => '+59170010010', 'featured' => false],
            ['slug' => 'demo-pixel-pinos', 'name' => 'Pixel Pinos', 'entity_type' => 'individual', 'partner_type' => 'creator', 'category' => 'experiences', 'description' => 'Fotografía, collage y talleres de creación.', 'instagram' => '@pixelpinos.demo', 'featured' => false],
            ['slug' => 'demo-gelato-bruma', 'name' => 'Gelato Bruma', 'entity_type' => 'organization', 'partner_type' => 'brand', 'category' => 'food', 'description' => 'Helados pequeños, sabores inesperados.', 'featured' => false],
            ['slug' => 'demo-trama-viva', 'name' => 'Trama Viva', 'entity_type' => 'organization', 'partner_type' => 'organizer', 'category' => 'entertainment', 'description' => 'Encuentros culturales que conectan personas y oficios.', 'website' => 'https://demo.invalid/trama', 'featured' => true],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function locations(): array
    {
        $hours = ['mon' => ['08:00-18:00'], 'tue' => ['08:00-18:00'], 'wed' => ['08:00-18:00'], 'thu' => ['08:00-18:00'], 'fri' => ['08:00-19:00'], 'sat' => ['09:00-15:00']];
        $make = function ($slug, $name, $partner, $type, $zone, $i, $extra = []) use ($hours) {
            return [
                'slug' => $slug, 'name' => $name, 'partner_slug' => $partner, 'location_type' => $type,
                'zone' => $zone, 'address' => 'Calle Demo '.($i + 10).', '.$zone,
                'address_reference' => 'A pasos de la plaza de la zona', 'latitude' => -17.39 + ($i * .0021),
                'longitude' => -66.16 + ($i * .0017),
                'maps_url' => 'https://www.openstreetmap.org/?mlat='.(-17.39 + ($i * .0021)).'&mlon='.(-66.16 + ($i * .0017)),
                'opening_hours' => $i % 4 === 0 ? null : $hours,
                'phone' => $i % 3 === 0 ? '+5914402'.str_pad((string) $i, 4, '0', STR_PAD_LEFT) : null,
                'whatsapp' => $i % 2 === 0 ? '+5917002'.str_pad((string) $i, 4, '0', STR_PAD_LEFT) : null,
            ] + $extra;
        };

        return [
            $make('demo-altura-nube-cala-cala', 'Altura Nube Cala Cala', 'demo-altura-nube', 'branch', 'Cala Cala', 1),
            $make('demo-altura-nube-recoleta', 'Altura Nube Recoleta', 'demo-altura-nube', 'branch', 'Recoleta', 2),
            $make('demo-altura-nube-tiquipaya', 'Altura Nube Tiquipaya', 'demo-altura-nube', 'branch', 'Tiquipaya', 3),
            $make('demo-brasa-prisma-norte', 'Brasa Prisma Norte', 'demo-brasa-prisma', 'branch', 'Queru Queru', 4),
            $make('demo-brasa-prisma-sur', 'Brasa Prisma Sur', 'demo-brasa-prisma', 'branch', 'La Chimba', 5),
            $make('demo-nomada-bocado-centro', 'Nomada Bocado Centro', 'demo-nomada-bocado', 'branch', 'Centro', 6),
            $make('demo-nomada-bocado-oeste', 'Nomada Bocado Oeste', 'demo-nomada-bocado', 'branch', 'Hipodromo', 7),
            $make('demo-verde-paramo-recoleta', 'Verde Paramo Recoleta', 'demo-verde-paramo', 'branch', 'Recoleta', 8),
            $make('demo-verde-paramo-sacaba', 'Verde Paramo Sacaba', 'demo-verde-paramo', 'branch', 'Sacaba', 9),
            $make('demo-casa-azafran', 'Casa Azafran', 'demo-casa-azafran', 'venue', 'Cala Cala', 10),
            $make('demo-kintu-calma', 'Kintu Calma Spa', 'demo-kintu-calma', 'venue', 'Queru Queru', 11),
            $make('demo-distrito-pulso', 'Distrito Pulso', 'demo-distrito-pulso', 'venue', 'Muyurina', 12),
            $make('demo-luna-lateral-estudio', 'Estudio Luna Lateral', 'demo-luna-lateral', 'venue', 'Cala Cala', 13),
            $make('demo-miga-cometa', 'Miga Cometa Panaderia', 'demo-miga-cometa', 'branch', 'Centro', 14),
            $make('demo-andes-umbral-punto', 'Andes Umbral Punto de encuentro', 'demo-andes-umbral', 'meeting_point', 'Tunari', 15),
            $make('demo-gelato-bruma', 'Gelato Bruma', 'demo-gelato-bruma', 'branch', 'Recoleta', 16),
            $make('demo-mirador-cobalto', 'Mirador Cobalto', null, 'venue', 'Alalay', 17),
            $make('demo-patio-orbita', 'Patio Orbita', null, 'venue', 'Centro', 18),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function benefits(): array
    {
        $titles = ['Café de cortesía con tu brunch', '2x1 en espresso de la tarde', 'Upgrade a leche vegetal', 'Bs 20 de ahorro en cena de brasas', 'Entrada para compartir al atardecer', 'Postre de temporada de cortesía', 'Menú de mediodía con 20%', 'Bebida fresca con tu plato principal', 'Upgrade de acompañamiento', 'Acceso a sesión de respiración', 'Masaje de cuello de cortesía', '20% en ritual de calma', 'Clase de prueba sin costo', 'Invita a alguien a entrenar', 'Upgrade a clase de movilidad', 'Diagnóstico de piel express', 'Toque final de cortesía', '20% en peinado de evento', 'Pan dulce de cortesía', '2x1 en focaccia de fin de día', 'Upgrade a café filtrado', 'Caminata urbana para acompañante', 'Bs 25 de ahorro en salida de montaña', 'Acceso preferente a ruta de amanecer', 'Impresión fine art de cortesía', 'Upgrade a revisión de portafolio', 'Entrada doble a noche cultural', 'Acceso anticipado a programación', 'Helado mini de cortesía', 'Sabores de temporada para compartir'];
        $partners = ['demo-altura-nube', 'demo-altura-nube', 'demo-altura-nube', 'demo-brasa-prisma', 'demo-brasa-prisma', 'demo-nomada-bocado', 'demo-verde-paramo', 'demo-verde-paramo', 'demo-casa-azafran', 'demo-kintu-calma', 'demo-kintu-calma', 'demo-kintu-calma', 'demo-distrito-pulso', 'demo-distrito-pulso', 'demo-distrito-pulso', 'demo-luna-lateral', 'demo-luna-lateral', 'demo-luna-lateral', 'demo-miga-cometa', 'demo-miga-cometa', 'demo-miga-cometa', 'demo-andes-umbral', 'demo-andes-umbral', 'demo-andes-umbral', 'demo-luna-lateral', 'demo-luna-lateral', 'demo-casa-azafran', 'demo-casa-azafran', 'demo-gelato-bruma', 'demo-gelato-bruma'];
        $scopes = ['demo-altura-nube-cala-cala', 'demo-altura-nube-recoleta', 'demo-altura-nube-tiquipaya', 'demo-brasa-prisma-norte', 'demo-brasa-prisma-sur', 'demo-nomada-bocado-centro', 'demo-verde-paramo-recoleta', 'demo-verde-paramo-sacaba', 'demo-casa-azafran', 'demo-kintu-calma', 'demo-kintu-calma', 'demo-kintu-calma', 'demo-distrito-pulso', 'demo-distrito-pulso', 'demo-distrito-pulso', 'demo-luna-lateral-estudio', 'demo-luna-lateral-estudio', 'demo-luna-lateral-estudio', 'demo-miga-cometa', 'demo-miga-cometa', 'demo-miga-cometa', 'demo-andes-umbral-punto', 'demo-andes-umbral-punto', 'demo-andes-umbral-punto', 'demo-luna-lateral-estudio', 'demo-luna-lateral-estudio', 'demo-casa-azafran', 'demo-casa-azafran', 'demo-gelato-bruma', 'demo-gelato-bruma'];
        $types = ['free_item', 'two_for_one', 'upgrade', 'fixed_amount', 'two_for_one', 'free_item', 'percentage', 'free_item', 'upgrade', 'exclusive_access', 'free_item', 'percentage', 'free_item', 'two_for_one', 'upgrade', 'other', 'free_item', 'percentage', 'free_item', 'two_for_one', 'upgrade', 'two_for_one', 'fixed_amount', 'exclusive_access', 'free_item', 'upgrade', 'two_for_one', 'exclusive_access', 'free_item', 'two_for_one'];

        return array_map(function ($i) use ($titles, $partners, $scopes, $types) {
            $all = in_array($i, [0, 3, 6, 9, 12, 18, 21, 28], true);

            return ['slug' => 'demo-beneficio-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'partner_slug' => $partners[$i], 'title' => $titles[$i], 'short_description' => 'Un beneficio para miembros JAKAWI en tu próxima visita.', 'description' => 'Disfruta este detalle pensado para descubrir el espacio con más calma y valor.', 'terms' => 'Válido para membresías activas. Sujeto a disponibilidad del lugar; no acumulable con otras promociones.', 'category' => in_array($partners[$i], ['demo-altura-nube', 'demo-miga-cometa'], 'true') ? 'cafe' : 'experiences', 'benefit_type' => $types[$i], 'estimated_savings' => [15, 20, 23, 25, 30, 35, 40, 50][$i % 8], 'redemption_limit_per_member' => $i % 9 === 0 ? null : ($i % 5 === 0 ? 2 : 1), 'featured' => in_array($i, [0, 3, 6, 12, 21, 26], true), 'applies_to_all_locations' => $all, 'location_slugs' => $all ? [] : [$scopes[$i]]];
        }, range(0, 29));
    }

    /** @return list<array<string, mixed>> */
    private function experiences(): array
    {
        return [
            ['slug' => 'demo-sunset-jakawi', 'title' => 'Sunset JAKAWI', 'short_description' => 'Música suave y un atardecer compartido.', 'description' => 'Una tarde para encontrarnos en altura con vista a la ciudad.', 'terms' => 'Cupo limitado; reserva previa requerida.', 'category' => 'entertainment', 'experience_type' => 'event', 'duration_minutes' => 180, 'regular_price' => 80, 'member_price' => 60, 'currency' => 'BOB', 'reservation_method' => 'whatsapp', 'reservation_whatsapp' => '+59170030001', 'featured' => true, 'partners' => [['demo-trama-viva', 'organizer', 1], ['demo-brasa-prisma', 'provider', 2], ['demo-andes-umbral', 'host', 3]]],
            ['slug' => 'demo-cata-cafe-boliviano', 'title' => 'Cata de café boliviano', 'short_description' => 'Origen, aroma y conversación en una mesa.', 'description' => 'Recorre perfiles de café boliviano junto a personas que lo preparan cada día.', 'terms' => 'Incluye degustación guiada.', 'category' => 'cafe', 'experience_type' => 'tasting', 'duration_minutes' => 120, 'regular_price' => 65, 'member_price' => 50, 'currency' => 'BOB', 'reservation_method' => 'jakawi', 'featured' => true, 'partners' => [['demo-altura-nube', 'host', 1], ['demo-miga-cometa', 'participant', 2]]],
            ['slug' => 'demo-arcilla-y-manos', 'title' => 'Arcilla y manos', 'short_description' => 'Taller de cerámica para empezar sin prisa.', 'description' => 'Modela una pieza propia en una tarde guiada de formas simples.', 'terms' => 'Materiales incluidos.', 'category' => 'experiences', 'experience_type' => 'workshop', 'duration_minutes' => 150, 'regular_price' => 90, 'member_price' => 70, 'currency' => 'BOB', 'reservation_method' => 'phone', 'reservation_phone' => '+59144030003', 'featured' => true, 'partners' => [['demo-pixel-pinos', 'creator', 1]]],
            ['slug' => 'demo-trekking-primera-luz', 'title' => 'Trekking Primera Luz', 'short_description' => 'Caminar temprano para mirar distinto el valle.', 'description' => 'Salida de mañana con guía local y una pausa de café al final.', 'terms' => 'Requiere calzado cómodo y confirmación por clima.', 'category' => 'experiences', 'experience_type' => 'outdoor', 'duration_minutes' => 240, 'regular_price' => 110, 'member_price' => 85, 'currency' => 'BOB', 'reservation_method' => 'whatsapp', 'reservation_whatsapp' => '+59170030004', 'featured' => false, 'partners' => [['demo-andes-umbral', 'host', 1], ['demo-altura-nube', 'provider', 2]]],
            ['slug' => 'demo-pausa-en-movimiento', 'title' => 'Pausa en movimiento', 'short_description' => 'Movilidad, respiración y energía de mitad de semana.', 'description' => 'Una clase accesible para volver al cuerpo en comunidad.', 'terms' => 'Trae ropa cómoda.', 'category' => 'wellness', 'experience_type' => 'wellness', 'duration_minutes' => 75, 'regular_price' => 55, 'member_price' => 40, 'currency' => 'BOB', 'reservation_method' => 'jakawi', 'featured' => false, 'partners' => [['demo-distrito-pulso', 'provider', 1], ['demo-kintu-calma', 'participant', 2]]],
            ['slug' => 'demo-mesa-azafran', 'title' => 'Mesa Azafrán', 'short_description' => 'Cena de estación para una mesa pequeña.', 'description' => 'Una noche de platos compartidos, especias y conversación larga.', 'terms' => 'Incluye menú de cuatro tiempos.', 'category' => 'food', 'experience_type' => 'social', 'duration_minutes' => 150, 'regular_price' => 150, 'member_price' => 120, 'currency' => 'BOB', 'reservation_method' => 'phone', 'reservation_phone' => '+59144030006', 'featured' => false, 'partners' => [['demo-casa-azafran', 'host', 1], ['demo-gelato-bruma', 'participant', 2]]],
            ['slug' => 'demo-collage-de-barrio', 'title' => 'Collage de barrio', 'short_description' => 'Recortes, fotos y una historia común.', 'description' => 'Workshop creativo inspirado en los recorridos cotidianos de Cochabamba.', 'terms' => 'No se necesita experiencia previa.', 'category' => 'experiences', 'experience_type' => 'workshop', 'duration_minutes' => 120, 'regular_price' => 60, 'member_price' => 45, 'currency' => 'BOB', 'reservation_method' => 'none', 'featured' => false, 'partners' => [['demo-pixel-pinos', 'creator', 1], ['demo-trama-viva', 'organizer', 2]]],
            ['slug' => 'demo-ruta-de-patios', 'title' => 'Ruta de patios', 'short_description' => 'Una experiencia cultural entre patios y relatos.', 'description' => 'Visita espacios independientes con una guía de historias locales.', 'terms' => 'El punto de encuentro se confirma al reservar.', 'category' => 'experiences', 'experience_type' => 'cultural', 'duration_minutes' => 180, 'regular_price' => 70, 'member_price' => 55, 'currency' => 'BOB', 'reservation_method' => 'external', 'reservation_url' => 'https://demo.invalid/ruta', 'featured' => false, 'partners' => []],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sessions(): array
    {
        $at = fn ($days, $hour) => now()->startOfDay()->addDays($days)->setTime($hour, 0);

        return [
            ['experience_slug' => 'demo-sunset-jakawi', 'reference_key' => 'demo-sunset-01', 'location_slug' => 'demo-mirador-cobalto', 'starts_at' => $at(2, 17), 'ends_at' => $at(2, 20), 'capacity' => 45, 'venue_label' => 'Mirador Cobalto'], ['experience_slug' => 'demo-sunset-jakawi', 'reference_key' => 'demo-sunset-02', 'location_slug' => 'demo-mirador-cobalto', 'starts_at' => $at(23, 17), 'ends_at' => $at(23, 20), 'capacity' => 45, 'venue_label' => 'Mirador Cobalto'],
            ['experience_slug' => 'demo-cata-cafe-boliviano', 'reference_key' => 'demo-cata-01', 'location_slug' => 'demo-altura-nube-cala-cala', 'reservation_partner_slug' => 'demo-altura-nube', 'starts_at' => $at(5, 18), 'ends_at' => $at(5, 20), 'capacity' => 18, 'venue_label' => 'Altura Nube · Cala Cala'], ['experience_slug' => 'demo-cata-cafe-boliviano', 'reference_key' => 'demo-cata-02', 'location_slug' => 'demo-miga-cometa', 'reservation_partner_slug' => 'demo-altura-nube', 'starts_at' => $at(19, 10), 'ends_at' => $at(19, 12), 'capacity' => null, 'venue_label' => 'Miga Cometa'],
            ['experience_slug' => 'demo-arcilla-y-manos', 'reference_key' => 'demo-arcilla-01', 'location_slug' => 'demo-patio-orbita', 'starts_at' => $at(8, 15), 'ends_at' => $at(8, 18), 'capacity' => 14, 'venue_label' => 'Patio Órbita'], ['experience_slug' => 'demo-trekking-primera-luz', 'reference_key' => 'demo-trekking-01', 'location_slug' => 'demo-andes-umbral-punto', 'starts_at' => $at(7, 6), 'ends_at' => $at(7, 10), 'capacity' => 12, 'venue_label' => 'Punto Andes Umbral'], ['experience_slug' => 'demo-trekking-primera-luz', 'reference_key' => 'demo-trekking-02', 'starts_at' => $at(28, 6), 'ends_at' => $at(28, 10), 'capacity' => null, 'venue_label' => 'Punto por confirmar'],
            ['experience_slug' => 'demo-pausa-en-movimiento', 'reference_key' => 'demo-pausa-01', 'location_slug' => 'demo-distrito-pulso', 'reservation_partner_slug' => 'demo-distrito-pulso', 'starts_at' => $at(3, 19), 'ends_at' => $at(3, 20), 'capacity' => 20, 'venue_label' => 'Distrito Pulso'], ['experience_slug' => 'demo-pausa-en-movimiento', 'reference_key' => 'demo-pausa-02', 'location_slug' => 'demo-kintu-calma', 'reservation_partner_slug' => 'demo-distrito-pulso', 'starts_at' => $at(14, 9), 'ends_at' => $at(14, 10), 'capacity' => 16, 'venue_label' => 'Kintu Calma Spa'],
            ['experience_slug' => 'demo-mesa-azafran', 'reference_key' => 'demo-mesa-01', 'location_slug' => 'demo-casa-azafran', 'starts_at' => $at(10, 20), 'ends_at' => $at(10, 23), 'capacity' => 16, 'venue_label' => 'Casa Azafrán'], ['experience_slug' => 'demo-collage-de-barrio', 'reference_key' => 'demo-collage-01', 'location_slug' => 'demo-patio-orbita', 'starts_at' => $at(12, 16), 'ends_at' => $at(12, 18), 'capacity' => 20, 'venue_label' => 'Patio Órbita'], ['experience_slug' => 'demo-ruta-de-patios', 'reference_key' => 'demo-ruta-01', 'starts_at' => $at(16, 10), 'ends_at' => $at(16, 13), 'capacity' => null, 'venue_label' => 'Centro de Cochabamba'], ['experience_slug' => 'demo-ruta-de-patios', 'reference_key' => 'demo-ruta-02', 'location_slug' => 'demo-patio-orbita', 'starts_at' => $at(35,10), 'ends_at' => $at(35,13), 'capacity' => 25, 'venue_label' => 'Patio Órbita'],
        ];
    }
}
