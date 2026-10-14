<?php

namespace App\Services;

use App\Models\Experience;

/** One read contract for editorial readiness, authorized discovery and operation. */
class ExperiencePublication
{
    public function mode(Experience $experience): string
    {
        return $experience->catalog_visibility ?? 'legacy';
    }

    public function isPublic(Experience $experience): bool
    {
        if ($this->mode($experience) === 'legacy') {
            return $experience->status !== 'draft';
        }

        return $experience->isPublished() && in_array($this->mode($experience), ['upcoming', 'active'], true)
            && $experience->partners()->published()->exists();
    }

    public function operationalBlockers(Experience $experience): array
    {
        $reasons = [];
        if (! $experience->isPublished()) {
            $reasons[] = 'Prepara editorialmente la experiencia (estado Publicada editorialmente).';
        }
        if (in_array($this->mode($experience), ['hidden', 'upcoming'], true)) {
            $reasons[] = 'La visibilidad seleccionada no permite reservas.';
        }
        if ($this->mode($experience) !== 'legacy' && ! $experience->operations_confirmed) {
            $reasons[] = 'Confirma con el Partner los datos físicos y el canal antes de activar.';
        }
        if (! $experience->upcomingSessions()->exists()) {
            $reasons[] = 'Configura una sesión real con fecha futura y estado Programada.';
        }
        if ($experience->reservation_method === 'jakawi') {
            if (! $experience->upcomingSessions()->whereHas('reservationPartner', fn ($q) => $q->published()->whereHas('experiences', fn ($e) => $e->whereKey($experience->id)))->exists()) {
                $reasons[] = 'Asigna a una sesión futura un Partner de reservas publicado y asociado.';
            }
            if ($this->mode($experience) !== 'legacy' && $experience->upcomingSessions()->whereNotNull('capacity')->exists()) {
                $reasons[] = 'El flujo interno aún no limita cupos automáticamente. Resuelve el control de capacidad antes de activar.';
            }
        } elseif (! $this->validChannel($experience)) {
            $reasons[] = 'Completa un destino válido para el método de reserva seleccionado; WhatsApp y teléfono requieren número internacional.';
        }
        if ($experience->reservation_method !== 'jakawi' && $experience->member_price !== null) {
            $reasons[] = 'El canal externo no verifica membresía ni aplica el precio preferencial. Define su verificación antes de activar; conserva los precios mientras preparas la propuesta.';
        }
        if ($this->mode($experience) !== 'legacy' && ! $experience->partners()->published()->exists()) {
            $reasons[] = 'Asocia al menos un Partner con perfil público.';
        }
        if ($this->mode($experience) !== 'legacy' && $this->copyWarnings($experience) !== []) {
            $reasons[] = 'Revisa las discrepancias del texto comercial antes de activar.';
        }

        return $reasons;
    }

    public function canOperate(Experience $experience): bool
    {
        return $this->operationalBlockers($experience) === [];
    }

    public function validChannel(Experience $experience): bool
    {
        return match ($experience->reservation_method) {
            'whatsapp' => (bool) preg_match('/^\+?[1-9]\d{6,14}$/', $experience->reservation_whatsapp ?? ''),
            'phone' => (bool) preg_match('/^\+?[1-9]\d{6,14}$/', $experience->reservation_phone ?? ''),
            'url', 'external' => (bool) filter_var($experience->reservation_url, FILTER_VALIDATE_URL) && in_array(parse_url($experience->reservation_url, PHP_URL_SCHEME), ['http', 'https'], true),
            default => false,
        };
    }

    public function copyWarnings(Experience $experience): array
    {
        $warnings = [];
        $texts = ['Descripción corta' => $experience->short_description, 'Descripción' => $experience->description, 'Condiciones' => $experience->terms];
        if ($experience->exists) {
            foreach ($experience->landingPresentations()->get() as $landing) {
                foreach (['subheadline', 'reward_display_override', 'final_cta_description', 'acquisition_supporting_copy'] as $field) {
                    $texts['Landing '.$landing->id.' · '.$field] = $landing->$field;
                }
            }
        }
        foreach ($texts as $label => $text) {
            foreach (preg_split('/[\n;|]|\s\/\s/u', $text ?? '') as $line) {
                preg_match_all('/(?:Bs\.?|BOB)\s*(\d+(?:[.,]\d{1,2})?)/iu', $line, $matches);
                $memberContext = preg_match('/(?:para miembros|con membresía|miembro JAKAWI|precio preferencial)/iu', $line) && ! preg_match('/sin membresía/iu', $line);
                $expected = $memberContext ? $experience->member_price : $experience->regular_price;
                foreach ($matches[1] as $amount) {
                    $amount = (float) str_replace(',', '.', $amount);
                    if ($expected === null || abs($amount - (float) $expected) > 0.001) {
                        $warnings[] = $label.': menciona Bs '.$amount.'; revisa si corresponde al precio sin membresía o al preferencial. No se modifica el texto automáticamente.';
                    }
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    public function summary(Experience $experience): array
    {
        $landing = $experience->landingPresentations()->orderBy('id')->first();
        $visible = $experience->isPublished() && ($this->mode($experience) === 'legacy' ? $experience->upcomingSessions()->exists() : in_array($this->mode($experience), ['upcoming', 'active'], true) && $experience->partners()->published()->exists());
        $cityVisible = $visible && ($this->mode($experience) === 'upcoming'
            ? $experience->partners()->published()->whereHas('locations', fn ($q) => $q->published()->whereNotNull('city_id'))->exists()
            : $experience->upcomingSessions()->whereHas('location', fn ($q) => $q->whereNotNull('city_id'))->exists());
        $blockers = $this->operationalBlockers($experience);
        $reasons = [];
        if (! $experience->isPublished()) {
            $reasons[] = 'El estado editorial actual no permite aparecer en el catálogo.';
        }
        if ($this->mode($experience) === 'hidden') {
            $reasons[] = 'Has seleccionado propuesta oculta.';
        }
        if ($this->mode($experience) === 'legacy' && ! $experience->upcomingSessions()->exists()) {
            $reasons[] = 'El modo anterior exige sesiones futuras para catálogo. Selecciona En preparación para autorizar visibilidad sin sesiones.';
        }
        if (! $experience->partners()->published()->exists()) {
            $reasons[] = 'No hay un Partner asociado publicado.';
        }
        if ($visible && ! $cityVisible) {
            $reasons[] = 'Explorar por ciudad requiere una sede publicada del Partner (propuestas) o una sesión en esa ciudad (ofertas).';
        }

        return [
            'editorial' => ['draft' => 'Borrador', 'published' => 'Publicada editorialmente', 'paused' => 'Suspendida', 'archived' => 'Archivada'][$experience->status] ?? $experience->status,
            'landing' => $landing?->status ?? 'Sin landing',
            'catalog' => $visible ? ($this->mode($experience) === 'upcoming' ? 'Visible · En preparación' : 'Visible según ciudad y sesiones') : 'No visible',
            'explore' => $cityVisible ? 'Visible en ciudades con sede / sesión' : 'No visible',
            'partner' => $visible ? 'Visible en perfiles públicos asociados' : 'No visible',
            'publicUrl' => $this->isPublic($experience) ? app(ProductLandingResolver::class)->navigationUrl($experience, true) : null,
            'previewUrl' => route('admin.experiences.preview', $experience),
            'landingPreviewUrl' => $landing ? '/admin/experiencias/'.$experience->slug.'/landings/'.$landing->slug.'/preview' : null,
            'operations' => $blockers === [] ? 'Disponible' : ($experience->status === 'paused' ? 'Temporalmente suspendida' : ($experience->status === 'archived' ? 'Finalizada / archivada' : 'Pendiente de coordinación')),
            'blockers' => $blockers, 'visibilityReasons' => $reasons,
            'warnings' => $this->copyWarnings($experience),
            'landingNote' => $landing?->status === 'DRAFT' ? 'La landing es un borrador privado. Si autorizas visibilidad, se utilizará la ficha informativa de la experiencia.' : 'La landing y el producto tienen publicación independiente.',
        ];
    }
}
