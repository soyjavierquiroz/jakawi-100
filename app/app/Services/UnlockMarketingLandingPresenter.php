<?php
namespace App\Services;

use App\Http\Controllers\UnlockController;
use App\Models\LandingPresentation;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use Illuminate\Http\Request;

/** Editorial projection only. Participation and fulfillment stay in the native journey. */
class UnlockMarketingLandingPresenter
{
    public function present(Unlock $unlock, LandingPresentation $landing, Request $request): array
    {
        $unlock->load(['partner', 'locations']);
        // Reuse the native projection, including secret-mode disclosure and real counts.
        $data = app(UnlockController::class)->data($unlock, true);
        $native = route('unlocks.show', $unlock);
        $user = $request->user();
        $member = $user?->hasActiveMembership() ?? false;
        $participation = $user?->unlockParticipations()->where('unlock_id', $unlock->id)->first();
        $eligible = $member ? $data['member_eligible'] : $data['free_user_eligible'];
        $membershipRequired = !$data['free_user_eligible'] && $data['member_eligible'];
        $success = in_array($unlock->status, [Unlock::GOAL_REACHED, Unlock::UNLOCKED, Unlock::FULFILLMENT_ACTIVE, Unlock::COMPLETED], true);
        $failed = in_array($unlock->status, [Unlock::GOAL_NOT_REACHED, Unlock::CANCELLED], true);
        $expired = $unlock->status === Unlock::ACTIVE && $unlock->commitment_deadline?->isPast();
        $jpEnabled = !$unlock->jp_deposit || config('unlocks.jp_commitments_enabled');
        $available = $unlock->status === Unlock::ACTIVE && !$expired && !$data['is_full'] && $jpEnabled;
        $hiddenOffer = $unlock->secret_mode && $unlock->hide_exact_offer_until_unlock && !$data['revealable'];
        $editorial = fn (?string $text) => $hiddenOffer ? null : $this->editorial($text);
        $stateLabel = match (true) {
            $unlock->status === Unlock::COMPLETED => 'COMPLETADO',
            $success => 'YA ESTÁ DESBLOQUEADO',
            $unlock->status === Unlock::GOAL_NOT_REACHED => 'NO SE ALCANZÓ LA META',
            $unlock->status === Unlock::CANCELLED => 'DESBLOQUEO CANCELADO',
            $expired => 'PLAZO DE COMPROMISOS FINALIZADO',
            $data['is_full'] => 'CAPACIDAD COMPLETA',
            !$jpEnabled => 'COMPROMISOS CON JP NO DISPONIBLES',
            $unlock->status !== Unlock::ACTIVE => 'NO DISPONIBLE',
            default => 'SUMA AL PROGRESO',
        };
        $action = ['label'=>'SUMARME', 'href'=>$native, 'kind'=>'native'];
        $reason = null;
        if ($failed || (!$success && $unlock->status !== Unlock::ACTIVE)) {
            $action = ['label'=>$stateLabel, 'href'=>'#condiciones', 'kind'=>'unavailable'];
        } elseif ($participation && !in_array($participation->status, [UnlockParticipation::INTERESTED, UnlockParticipation::WAITLISTED], true)) {
            $action['label'] = 'VER MI PARTICIPACIÓN';
            if (UnlockParticipation::terminal($participation->status)) $reason = 'Tu participación ya terminó. Consulta su estado en la ficha.';
        } elseif ($success) {
            $action['label'] = 'VER LO DESBLOQUEADO';
        } elseif (!$available) {
            $action = ['label'=>$stateLabel, 'href'=>'#condiciones', 'kind'=>'unavailable'];
        } elseif (!$data['free_user_eligible'] && !$data['member_eligible']) {
            $reason = 'Este desbloqueo no habilita compromisos para usuarios sin membresía ni para miembros.';
            $action = ['label'=>'NO ELEGIBLE', 'href'=>'#condiciones', 'kind'=>'unavailable'];
        } elseif (!$user) {
            $action = ['label'=>'QUIERO SUMARME', 'href'=>route('unlocks.commit', $unlock), 'kind'=>'guest'];
        } elseif (!$member && $membershipRequired) {
            $action = ['label'=>'ACTIVAR MEMBRESÍA', 'href'=>'/membresia?'.http_build_query(['journey'=>'UNLOCK', 'action'=>'COMMIT', 'resource_id'=>$unlock->id]), 'kind'=>'membership'];
        } elseif (!$eligible) {
            $reason = 'Tu estado actual no es elegible para este desbloqueo.';
            $action = ['label'=>'NO ELEGIBLE', 'href'=>'#condiciones', 'kind'=>'unavailable'];
        } elseif ($unlock->jp_deposit > 0 && app(JpBalanceService::class)->for($user)['available_balance'] < $unlock->jp_deposit) {
            $reason = 'No tienes JP disponibles suficientes para la garantía.';
            $action = ['label'=>'JP INSUFICIENTES', 'href'=>'#condiciones', 'kind'=>'unavailable'];
        }
        $membership = match (true) {
            $membershipRequired => 'Necesitas una cuenta y una membresía activa para comprometerte.',
            $data['free_user_eligible'] && $data['member_eligible'] => 'Puedes comprometerte con una cuenta, con o sin membresía activa.',
            $data['free_user_eligible'] => 'Pueden comprometerse usuarios sin membresía activa. Los miembros no son elegibles.',
            default => 'Este desbloqueo no habilita compromisos para usuarios sin membresía ni para miembros.',
        };
        $date = fn ($value) => $value?->timezone('America/La_Paz')->format('d/m/Y H:i').' (hora de Bolivia)';
        $deadline = $unlock->commitment_deadline ? $date($unlock->commitment_deadline) : null;
        $confirmation = $unlock->confirmation_deadline ? ' Confirma hasta '.$date($unlock->confirmation_deadline).'.' : '';
        $result = 'Al alcanzar la meta se abre la confirmación de las participaciones. Debes confirmar y cumplir las condiciones de la propuesta.'.$confirmation;
        $failure = 'Si el desbloqueo termina sin alcanzar la meta o se cancela, no se activa la propuesta.';
        $guarantee = null;
        if ($unlock->jp_deposit > 0) {
            $guarantee = $unlock->jp_deposit.' JP quedan reservados como garantía al comprometerte y no están disponibles para otros usos. Alcanzar la meta no libera la reserva. Se libera al cumplir, al cancelar dentro del plazo permitido, si vence tu confirmación sin confirmar, o si el desbloqueo termina sin alcanzar la meta o se cancela. Si confirmas y no cumples al terminar la ventana de cumplimiento, la garantía se ejecuta y esos JP se descuentan.';
            if ($unlock->cancellation_deadline) $guarantee .= ' Puedes cancelar hasta '.$date($unlock->cancellation_deadline).'.';
            $failure .= ' Los JP reservados se liberan.';
        }
        $conditions = [$membership, $result, $failure];
        if ($deadline) $conditions[] = 'Compromisos hasta '.$deadline.'.';
        if ($unlock->maximum_capacity !== null) $conditions[] = 'Capacidad máxima: '.$unlock->maximum_capacity.' compromisos.';
        if ($guarantee) $conditions[] = $guarantee;
        if ($unlock->fulfillment_starts_at) $conditions[] = 'Cumplimiento desde '.$date($unlock->fulfillment_starts_at).'.';
        if ($unlock->fulfillment_ends_at) $conditions[] = 'Cumplimiento hasta '.$date($unlock->fulfillment_ends_at).'.';
        if ($unlock->jp_completion_bonus > 0) $conditions[] = 'Al validar el cumplimiento se acreditan '.$unlock->jp_completion_bonus.' JP de bonificación.';
        $reward = $data['description'] ?: $data['short_description'] ?: $data['title'];
        $faq = [
            ['question'=>'¿Qué estamos desbloqueando?', 'answer'=>$hiddenOffer ? 'La propuesta exacta se revela al alcanzar la meta. '.$data['short_description'] : $reward],
            ['question'=>'¿Cómo me sumo?', 'answer'=>'Continúa en la ficha del desbloqueo para revisar las condiciones y confirmar tu compromiso. '.$membership],
            ['question'=>'¿Necesito membresía?', 'answer'=>$membership],
            ['question'=>'¿Necesito JP?', 'answer'=>$unlock->jp_deposit > 0 ? 'Sí. Necesitas '.$unlock->jp_deposit.' JP disponibles para la garantía.' : 'Este desbloqueo no requiere una garantía JP.'],
            ['question'=>'¿Qué pasa si no llegamos a la meta?', 'answer'=>$failure],
            ['question'=>'¿Qué pasa si se desbloquea?', 'answer'=>$result],
        ];
        if ($guarantee) $faq[] = ['question'=>'¿Qué pasa con mis JP?', 'answer'=>$guarantee];
        if ($deadline) $faq[] = ['question'=>'¿Cuándo termina?', 'answer'=>'El plazo de compromisos termina el '.$deadline.'.'];
        $benefits = [['title'=>'UNA PROPUESTA REAL', 'description'=>$hiddenOffer ? $data['short_description'] ?: 'La propuesta exacta se revela al alcanzar la meta.' : $reward]];
        if (!$success && !$failed && !$expired) $benefits[] = ['title'=>'ENTRE TODOS', 'description'=>'Cada compromiso suma a la meta de '.$data['minimum_commitments'].' compromisos.'];
        if ($guarantee) $benefits[] = ['title'=>'GARANTÍA CLARA', 'description'=>'Los JP se reservan al comprometerte. Revisa cuándo se liberan y cuándo se ejecuta la garantía antes de sumarte.'];
        return [
            'title'=>$data['title'], 'slug'=>$data['slug'], 'description'=>$data['description'], 'reward'=>$reward,
            'offers'=>array_values(array_filter([
                $data['free_user_offer'] ? ['label'=>'Usuarios sin membresía', 'value'=>$data['free_user_offer']] : null,
                $data['member_offer'] ? ['label'=>'Miembros', 'value'=>$data['member_offer']] : null,
            ])),
            'eyebrow'=>$editorial($landing->eyebrow) ?: 'DESBLOQUEO JAKAWI',
            'headline'=>$success ? $data['title'] : ($failed || $expired ? $stateLabel : ($editorial($landing->headline) ?: $data['title'])),
            'subheadline'=>$failed ? $failure : ($expired ? 'El plazo terminó. Consulta el estado del desbloqueo en sus condiciones.' : ($success ? $result : ($editorial($landing->subheadline) ?: $data['short_description'] ?: $reward))),
            'valueNote'=>$editorial($landing->reward_display_override),
            'heroUrl'=>app(MediaUrl::class)->url($hiddenOffer ? null : ($landing->hero_path ?: $unlock->hero_path), 'hero'),
            'heroAlt'=>$hiddenOffer ? $data['title'] : ($landing->hero_alt ?: $data['title']),
            'progress'=>['current'=>$data['committed_count'], 'target'=>$data['minimum_commitments'], 'remaining'=>$data['remaining'], 'percent'=>$data['minimum_commitments'] > 0 ? round($data['committed_count'] / $data['minimum_commitments'] * 100, 1) : null],
            'status'=>$data['status'], 'stateLabel'=>$stateLabel, 'success'=>$success, 'failed'=>$failed, 'expired'=>(bool) $expired,
            'deadline'=>$deadline, 'partner'=>$data['partner'], 'locations'=>$data['locations'],
            'membership'=>$membership, 'jpRequired'=>$unlock->jp_deposit, 'guarantee'=>$guarantee, 'conditions'=>$conditions, 'terms'=>$data['terms'],
            'viewer'=>['authenticated'=>(bool) $user, 'hasActiveMembership'=>$member, 'eligible'=>(bool) $user && $eligible, 'participationStatus'=>$participation?->status, 'reason'=>$reason],
            'action'=>$action, 'benefits'=>$benefits, 'faq'=>$faq,
            'steps'=>[
                ['title'=>'REVISA LA PROPUESTA', 'description'=>$membership],
                ['title'=>$success ? 'REVISA TU PARTICIPACIÓN' : ($failed || $expired ? 'REVISA EL RESULTADO' : 'SUMA TU COMPROMISO'), 'description'=>$success ? $stateLabel : ($failed || $expired ? $failure : 'Tu compromiso cuenta para alcanzar la meta. Continúa en la ficha para confirmarlo.'.($unlock->jp_deposit > 0 ? ' Requiere una reserva de '.$unlock->jp_deposit.' JP como garantía.' : ''))],
                ['title'=>$failed || $expired ? 'CONOCE LAS CONDICIONES' : 'CONFIRMA Y PARTICIPA', 'description'=>$failed || $expired ? $failure : $result],
            ],
            'finalHeadline'=>$success ? 'LO LOGRAMOS.' : ($failed || $expired ? $stateLabel : ($editorial($landing->final_cta_headline) ?: 'ESTAMOS MÁS CERCA SI TE SUMAS.')),
        ];
    }

    private function editorial(?string $text): ?string
    {
        // Editorial tone must not claim quantities, offers, eligibility or guarantees.
        if (!$text || preg_match('/\d|%|\b(?:JP|meta|faltan|compromisos|garantía|reserva|liberan|devuelven|gastas|gratis|gratuito|precio|recompensa|premio|oferta|beneficio|incluye|asegurado|garantizado|desbloqueado|cancelado|plazo|vence|hasta|hoy|mañana|últim[oa]s?|horas|membresía|miembros|elegible|sin|cero|uno|dos|tres|cuatro|cinco|diez|cien|mil)\b/iu', $text)) return null;
        return $text;
    }
}
