<?php

namespace App\Services;

use App\Models\Challenge;

/** Operational instructions from the same rules used to evaluate the Challenge. */
final class ChallengeDeliveryPresenter
{
    public function present(Challenge $challenge): array
    {
        $metric = ['likes' => 'likes', 'views' => 'vistas', 'comments' => 'comentarios'][$challenge->qualification_metric] ?? 'interacciones';
        $goal = match ($challenge->qualification_type) {
            'METRIC_THRESHOLD' => "Consigue al menos {$challenge->qualification_target} {$metric} con evidencia válida.",
            'MANUAL' => 'Completa la tarea indicada. JAKAWI revisará tu cumplimiento.',
            default => 'Cumple las reglas del reto con evidencia válida.',
        };
        $social = $challenge->evidence_type === 'SOCIAL_POST';
        $steps = [['heading' => $social ? 'PUBLICA TU CONTENIDO' : 'REALIZA EL RETO',
            'body' => $challenge->instructions ?: ($social ? 'Publica tu contenido y conserva su enlace.' : 'Sigue las instrucciones del reto.')]];
        if ($social) {
            $steps[] = ['heading' => 'CUMPLE EL OBJETIVO', 'body' => $goal];
            $steps[] = ['heading' => 'ENVÍA TU EVIDENCIA', 'body' => 'Envía el enlace de tu publicación en esta página.'.($challenge->max_entries_per_user !== null ? " Límite: {$challenge->max_entries_per_user} publicaciones por persona." : '')];
        }
        if ($challenge->review_mode === 'REQUIRED' || $challenge->evidence_type === 'MANUAL' || $challenge->qualification_type === 'MANUAL') {
            $steps[] = ['heading' => 'ESPERA LA REVISIÓN', 'body' => 'JAKAWI revisará el cumplimiento antes de confirmar el resultado. Participar no significa haber ganado.'];
        }

        return [
            'title' => $challenge->title,
            'partner' => $challenge->partner?->name,
            'mission' => $goal,
            'steps' => $steps,
            'deadline' => $challenge->ends_at?->toIso8601String(),
            'platforms' => $social ? ($challenge->allowed_platforms ?? []) : [],
            'hashtags' => $social ? ($challenge->required_hashtags ?? []) : [],
            'mentions' => $social ? ($challenge->required_mentions ?? []) : [],
            'verificationBoundary' => $social ? 'Confirma tu correo antes de enviar evidencia o recibir una recompensa.' : 'Confirma tu correo antes de recibir una recompensa. Puedes comenzar el reto y leer todas las instrucciones ahora.',
        ];
    }
}
