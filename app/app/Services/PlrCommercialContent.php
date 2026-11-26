<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Plain Spanish copy grounded in the imported package, without commercial permissions. */
class PlrCommercialContent
{
    public function generate(array $data): ?string
    {
        if (filled($data['commercial_content'] ?? null)) return $data['commercial_content'];
        $name = $this->plain($data['name'] ?? '');
        if ($name === '') return null;
        $catalog = json_decode(file_get_contents(resource_path('content/plr-commercial-content.es.json')), true, 512, JSON_THROW_ON_ERROR);
        if (isset($catalog[$data['vendor_id'] ?? ''])) return str_replace('{name}', $name, $catalog[$data['vendor_id']]);

        // Only original learning content: never testimonials, upsells or provider claims.
        $topics = array_values(array_unique(array_filter(array_map(
            fn ($topic) => $this->safe($topic), $data['content_topics'] ?? []
        ))));
        $activities = array_values(array_unique(array_filter(array_map(
            fn ($topic) => $this->safe($topic), $data['activity_topics'] ?? []
        ))));
        $summary = null;
        foreach ([$data['short_description'] ?? '', $data['description'] ?? ''] as $text) {
            $candidate = $this->safe($text, 260);
            if ($candidate && mb_strtolower($candidate) !== mb_strtolower($name)) { $summary = rtrim($candidate, '.!'); break; }
        }
        $intro = $summary ? "$name: $summary." : "$name reúne contenidos para conocer sus temas y llevar el aprendizaje a tu propia práctica.";
        $details = [];
        if ($topics) $details[] = 'Encontrarás contenidos sobre '.$this->join(array_slice($topics, 0, 3)).'.';
        if ($activities) $details[] = 'También podrás explorar propuestas como '.$this->join(array_slice($activities, 0, 2)).'.';
        $benefit = match ($data['category'] ?? null) {
            'cocina-recetas', 'food' => 'Una referencia para comparar ingredientes y técnicas y elegir tu próxima preparación.',
            'manualidades-creatividad', 'fiestas-celebraciones' => 'Puedes organizar materiales e ideas antes de comenzar tu próximo proyecto.',
            'negocios-marketing', 'finanzas-personales' => 'Podrás ordenar preguntas y preparar decisiones con más contexto, sin ingresos ni resultados financieros asegurados.',
            'salud-autocuidado', 'nutricion-habitos', 'wellness', 'beauty' => 'Recursos educativos para conocer opciones de cuidado y preparar tus preguntas, sin sustituir atención profesional ni asegurar resultados.',
            'fitness' => 'Puedes conocer las propuestas y organizar una práctica adaptada a tu experiencia y posibilidades.',
            'familia-crianza' => 'Una referencia para reflexionar sobre situaciones cotidianas y preparar preguntas sobre las necesidades de tu familia.',
            'mascotas-animales' => 'Puedes conocer las propuestas y valorar su adecuación a tu animal con la orientación profesional que necesites.',
            default => 'Puedes recorrer estos contenidos a tu ritmo y elegir los temas que quieras explorar con más atención.',
        };
        $invitations = ['Descubre la aplicación y elige por dónde comenzar.', 'Conoce sus contenidos y encuentra tu punto de partida.', 'Explora la propuesta y elige tu próximo tema de aprendizaje.', 'Acércate al contenido y descubre sus propuestas.'];
        return $intro."\n\n".implode(' ', [...$details, $benefit])."\n\n".$invitations[hexdec(substr(hash('sha256', $name), 0, 2)) % count($invitations)];
    }

    private function plain(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function safe(string $text, int $limit = 160): ?string
    {
        $text = $this->plain($text);
        if ($text === '' || mb_strlen($text) > $limit) return null;
        // Source marketing claims are not evidence for an outcome, even in a heading.
        if (preg_match('/\d|[%$€]|\b(cur\w*|sanar|garant\w*|asegur\w*|elimin\w*|quema\w*|adelgaz\w*|perder|perdida|pérdida|milagr\w*|probados?|comprob\w*|científic\w*|ciencia|rentab\w*|ganar|gana|ingresos|riqueza|detox\w*|desintoxic\w*|rejuven\w*|transform\w*|instant\w*|perfect\w*|infalib\w*|éxito|exito|domina\w*|siempre|nunca)\b/iu', $text)) return null;
        return rtrim($text, '.!');
    }

    private function join(array $items): string
    {
        $last = array_pop($items);
        return $items ? implode(', ', $items).' y '.$last : $last;
    }
}
