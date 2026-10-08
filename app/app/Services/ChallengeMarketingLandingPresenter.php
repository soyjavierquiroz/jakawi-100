<?php
namespace App\Services;

use App\Models\Challenge;
use App\Models\LandingPresentation;

class ChallengeMarketingLandingPresenter
{
    public function present(Challenge $challenge, LandingPresentation $landing): array
    {
        $metric = ['likes' => 'likes', 'views' => 'vistas', 'comments' => 'comentarios'];
        $target = $challenge->qualification_target;
        $unit = $metric[$challenge->qualification_metric] ?? 'interacciones';
        $reward = match ($challenge->reward_type) {
            'JP' => $challenge->reward_jp_amount.' JP',
            'MANUAL_PRIZE' => $challenge->manual_prize_description,
            default => $challenge->benefit?->title ?? 'beneficio JAKAWI',
        };
        $qualification = match ($challenge->qualification_type) {
            'METRIC_THRESHOLD' => "Consigue al menos {$target} {$unit} en una publicación válida.",
            'MANUAL' => 'JAKAWI revisará el cumplimiento de las instrucciones del reto.',
            default => 'Cumple las reglas del reto con una publicación válida.',
        };
        $selection = match ($challenge->selection_type) {
            'FIRST_N' => "Los primeros {$challenge->winner_limit} participantes verificados reciben el premio.",
            'TOP_N' => "Las {$challenge->winner_limit} mejores participaciones según {$metric[$challenge->selection_metric]}. El ranking es provisional hasta el cierre.",
            'MANUAL' => 'JAKAWI seleccionará a los ganadores mediante revisión.',
            default => 'Todas las personas que califiquen reciben el premio.',
        };
        $headline = match (true) {
            $challenge->selection_type === 'TOP_N' && $target => "SUPERA {$target} {$unit}. ENTRA AL TOP {$challenge->winner_limit}.",
            $challenge->selection_type === 'FIRST_N' => "COMPLETA EL RETO. SÉ DE LOS PRIMEROS {$challenge->winner_limit}.",
            $challenge->qualification_type === 'METRIC_THRESHOLD' => "SUPERA {$target} {$unit} Y GANA {$reward}.",
            $challenge->selection_type === 'ALL_QUALIFIED' => "CUMPLE EL RETO Y DESBLOQUEA {$reward}.",
            default => $challenge->title,
        };
        $action = $challenge->evidence_type === 'SOCIAL_POST' ? 'Publica según las instrucciones del reto.' : ($challenge->instructions ?: 'Sigue las instrucciones del reto.');
        $steps = $challenge->evidence_type === 'SOCIAL_POST'
            ? [['title'=>'Publica','description'=>$action],['title'=>'Envía tu enlace','description'=>'Comparte la URL de tu publicación para verificarla.'],['title'=>'Cumple la meta y recibe tu resultado','description'=>$qualification.' '.$selection]]
            : [['title'=>'Participa','description'=>'Ingresa con tu cuenta JAKAWI y participa.'],['title'=>'Cumple el reto','description'=>$action],['title'=>'Recibe tu resultado','description'=>$qualification.' '.$selection]];
        $membership = $challenge->participation_eligibility === 'ACTIVE_MEMBERS' ? 'Necesitas membresía activa para participar.' : ($challenge->reward_eligibility === 'ACTIVE_MEMBERS' ? 'Puedes participar gratis; necesitas membresía activa para recibir el premio.' : 'No necesitas membresía activa para participar ni para recibir el premio.');
        $rules = [
            ['title'=>'Qué hacer','description'=>$action], ['title'=>'Cómo calificas','description'=>$qualification],
            ['title'=>'Cómo se eligen ganadores','description'=>$selection],
            ['title'=>'Cierre','description'=>$challenge->ends_at?->toIso8601String() ?? 'Sin fecha de cierre anunciada'],
            ['title'=>'Quién recibe el premio','description'=>$membership],
        ];
        if ($challenge->max_entries_per_user) $rules[] = ['title'=>'Entradas máximas','description'=>(string)$challenge->max_entries_per_user];
        if ($challenge->review_mode === 'REQUIRED') $rules[] = ['title'=>'Revisión','description'=>'JAKAWI revisa el resultado antes de confirmar ganadores.'];
        $faq = [
            ['question'=>'¿Quién puede participar?','answer'=>$challenge->participation_eligibility === 'ACTIVE_MEMBERS' ? 'Personas con cuenta y membresía JAKAWI activa.' : 'Personas con una cuenta JAKAWI.'],
            ['question'=>'¿Cómo se eligen ganadores?','answer'=>$selection],
            ['question'=>'¿Necesito membresía?','answer'=>$membership],
            ['question'=>'¿Cuándo recibo el premio?','answer'=>'Después de verificar el cumplimiento y confirmar el resultado del reto.'],
        ];
        if ($challenge->evidence_type === 'SOCIAL_POST') array_splice($faq, 1, 0, [['question'=>'¿Cuándo se verifica mi publicación?','answer'=>'Se verifica después de enviar el enlace. Puedes seguir el estado de cada publicación aquí.']]);
        $editorialSubheadline = $this->editorial($landing->subheadline) ?: ($this->editorial($challenge->marketing_subheadline) ?: ($this->editorial($challenge->marketing_hook) ?: $challenge->description));
        return [
            'eyebrow'=>$this->editorial($landing->eyebrow) ?: 'RETO JAKAWI', 'headline'=>$this->editorial($landing->headline) ?: ($this->editorial($challenge->marketing_headline) ?: $headline),
            'subheadline'=>trim(($editorialSubheadline ? $editorialSubheadline.' ' : '').$qualification.' '.$selection),
            'reward'=>$reward, 'rewardType'=>$challenge->reward_type, 'rewardNote'=>$this->editorial($landing->reward_display_override) ?: $this->editorial($challenge->marketing_reward_copy),
            'rewardDetail'=>$challenge->reward_type === 'BENEFIT' ? ($challenge->benefit?->short_description ?: $challenge->benefit?->description) : null,
            'deadline'=>$challenge->ends_at?->toIso8601String(), 'qualification'=>$qualification, 'selection'=>$selection,
            'membership'=>$membership, 'review'=>$challenge->review_mode === 'REQUIRED' ? 'JAKAWI revisa los resultados.' : 'Verificación según las reglas del reto.',
            'benefits'=>array_slice([['title'=>'Haz el reto','description'=>$action],['title'=>'Sigue tu avance','description'=>$qualification],['title'=>'Conoce el resultado','description'=>$selection]],0,3),
            'steps'=>$steps, 'rules'=>$rules, 'faq'=>$faq,
            'finalHeadline'=>$this->editorial($landing->final_cta_headline) ?: 'TU PRÓXIMO RETO EMPIEZA AQUÍ.',
        ];
    }

    private function editorial(?string $copy): ?string
    {
        if (!$copy || preg_match('/\d|\b(?:cero|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|veinte|treinta|cien|ciento|mil|doble)\b/iu', $copy)) return null;
        return $copy;
    }
}
