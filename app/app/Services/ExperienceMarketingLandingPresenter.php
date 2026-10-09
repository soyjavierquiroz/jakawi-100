<?php
namespace App\Services;

use App\Models\Experience;
use App\Models\LandingPresentation;
use Illuminate\Http\Request;

class ExperienceMarketingLandingPresenter
{
    public function present(Experience $experience, LandingPresentation $landing, Request $request): array
    {
        $experience->load('partners');
        $sessions = $experience->upcomingSessions()->with(['location.cityEntity', 'reservationPartner'])->get();
        $dates = $sessions->map(fn ($session) => [
            'id'=>$session->id,
            'date'=>$session->starts_at->timezone('America/La_Paz')->format('d/m/Y'),
            'time'=>$session->starts_at->timezone('America/La_Paz')->format('H:i'),
            'place'=>$session->location?->name ?: $session->venue_label,
            'address'=>$session->location?->address,
            'city'=>$session->location?->cityEntity?->name ?: $session->location?->city,
        ]);
        $next = $dates->first();
        $internal = $experience->reservation_method === 'jakawi';
        $member = $request->user()?->hasActiveMembership() ?? false;
        $organizers = $experience->partners->where('pivot.role', 'organizer')->map->only(['name', 'slug'])->values()->all();
        $partners = $experience->partners->map(fn ($partner) => ['name'=>$partner->name,'slug'=>$partner->slug,'role'=>$partner->pivot->role])->unique(fn ($p)=>$p['slug'].'|'.$p['role'])->values()->all();
        $prices = [];
        foreach (['regular_price'=>'Precio normal', 'member_price'=>'Miembro JAKAWI'] as $field=>$label) {
            if ($experience->$field !== null) $prices[] = ['label'=>$label,'value'=>(float) $experience->$field === 0.0 ? 'GRATIS' : $experience->currency.' '.$experience->$field];
        }
        $native = route('experiences.show', $experience);
        $bookable = $internal
            ? $sessions->contains(fn ($s) => $s->reservationPartner?->isPublished() && $experience->partners->contains('id', $s->reservation_partner_id))
            : filled($experience->reservation_url) || filled($experience->reservation_whatsapp) || (filled($experience->reservation_phone) && preg_match('/^\+?[1-9]\d{6,14}$/', $experience->reservation_phone));
        $reservation = $request->user() && $sessions->count() === 1 ? $request->user()->experienceReservations()->where('experience_session_id', $next['id'])->whereIn('status', ['pending', 'confirmed'])->first() : null;
        $past = !$next && $experience->sessions()->where('status', 'scheduled')->where('starts_at', '<', now())->exists();
        $action = ['label'=>'RESERVAR','href'=>$native,'kind'=>'native','sessionId'=>null];
        if (!$experience->isPublished() || !$next || !$bookable) {
            $action = ['label'=>!$experience->isPublished() ? 'NO DISPONIBLE' : ($past ? 'FINALIZADA' : (!$next ? 'NO HAY PRÓXIMAS FECHAS' : 'RESERVA NO DISPONIBLE')), 'href'=>'#condiciones','kind'=>'unavailable','sessionId'=>null];
        } elseif ($reservation) {
            $action['label'] = 'VER MI RESERVA';
        } elseif ($internal && $sessions->count() === 1 && !$request->user()) {
            $action = ['label'=>'QUIERO IR','href'=>route('experiences.reservations.store', $experience),'kind'=>'guest','sessionId'=>$next['id']];
        } elseif ($internal && !$member && $request->user()) {
            // Multiple sessions must be selected in Product Detail before entering membership.
            $action = ['label'=>$sessions->count() === 1 ? 'ACTIVAR MEMBRESÍA' : 'ELEGIR FECHA Y ACTIVAR MEMBRESÍA',
                'href'=>$sessions->count() === 1 ? '/membresia?'.http_build_query(['journey'=>'EXPERIENCE','action'=>'RESERVE','resource_id'=>$experience->id,'experience_session_id'=>$next['id']]) : $native,
                'kind'=>$sessions->count() === 1 ? 'membership' : 'native','sessionId'=>null];
        } elseif (!$request->user()) $action['label'] = 'QUIERO IR';
        $membership = $internal ? 'La reserva en JAKAWI requiere una cuenta y una membresía activa.' : 'La reserva se gestiona directamente con el partner desde la ficha de la experiencia.';
        $reserve = $sessions->count() > 1 ? 'Elige una fecha en la ficha de la experiencia y continúa con la reserva.' : 'Continúa desde la ficha de la experiencia para reservar tu lugar.';
        $faq = [['question'=>'¿Cómo reservo?','answer'=>$reserve.' '.$membership]];
        if ($internal) $faq[] = ['question'=>'¿Necesito membresía?','answer'=>$membership];
        if ($next) {
            $faq[] = ['question'=>'¿Qué día y a qué hora?','answer'=>$next['date'].' a las '.$next['time'].'.'];
            if ($next['place']) $faq[] = ['question'=>'¿Dónde es?','answer'=>implode(' · ', array_filter([$next['place'],$next['address'],$next['city']]))];
        }
        if ($sessions->count() > 1) $faq[] = ['question'=>'¿Puedo elegir otro horario?','answer'=>'Sí. Revisa las próximas sesiones y selecciona tu fecha en la ficha de la experiencia.'];
        return [
            'title'=>$experience->title,'slug'=>$experience->slug,'experienceType'=>$experience->experience_type,
            'eyebrow'=>$this->editorial($landing->eyebrow) ?: 'EXPERIENCIA JAKAWI',
            'headline'=>$this->editorial($landing->headline) ?: $experience->title,
            'subheadline'=>$this->editorial($landing->subheadline) ?: ($experience->short_description ?: $experience->description ?: $experience->title),
            'valueNote'=>$this->editorial($landing->reward_display_override),
            'description'=>$experience->description,'terms'=>$experience->terms,
            'organizers'=>$organizers,'partners'=>$partners,'prices'=>$prices,'next'=>$next,
            'sessions'=>$dates->take(6)->values()->all(),'sessionCount'=>$sessions->count(),
            'membership'=>$membership,'viewer'=>['authenticated'=>(bool) $request->user(),'hasActiveMembership'=>$member,'reservationStatus'=>$reservation?->status], 'action'=>$action,'faq'=>$faq,
            'steps'=>[
                ['title'=>$sessions->count()>1 ? 'ELIGE TU FECHA' : 'CONOCE TU EXPERIENCIA','description'=>$reserve],
                ['title'=>$internal && !$member ? 'CUENTA Y MEMBRESÍA' : 'RESERVA TU LUGAR','description'=>$membership],
                ['title'=>'VIVE LA EXPERIENCIA','description'=>$next ? implode(' · ',array_filter([$next['date'],$next['time'],$next['place']])) : 'Revisa las próximas fechas antes de planificar tu visita.'],
            ],
            'finalHeadline'=>$this->editorial($landing->final_cta_headline) ?: 'TU PRÓXIMO PLAN EMPIEZA AQUÍ.',
        ];
    }

    private function editorial(?string $copy): ?string
    {
        // Editorial tone cannot supply dates, prices, venues, availability or access promises.
        if (!$copy || preg_match('/\d|%|\b(?:gratis|gratuito|precio|cuesta|bolivianos|cupos|agotado|membresía|incluye|dirección|calle|avenida|viernes|lunes|martes|miércoles|jueves|sábado|domingo|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre|hoy|mañana|aquí|en|sin|cero|uno|dos|tres|cuatro|cinco|diez|cien|mil)\b/iu', $copy)) return null;
        return $copy;
    }
}
