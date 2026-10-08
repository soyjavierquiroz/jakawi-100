<?php
namespace App\Services;

use App\Models\Benefit;
use App\Models\LandingPresentation;
use Illuminate\Http\Request;

class BenefitMarketingLandingPresenter
{
    public function present(Benefit $benefit, LandingPresentation $landing, Request $request, array $props): array
    {
        // Benefits have no contractual numeric offer field. Never interpret estimated_savings as an offer.
        $value = $benefit->title;
        $partner = $benefit->partner->name;
        $places = $benefit->availableLocations()->with('cityEntity')->get()->map(fn ($location) => [
            ...$location->only(['id','name','address','zone']), 'city'=>$location->cityEntity?->name ?: $location->city,
        ])->all();
        $placeSummary = implode(' · ', array_column($places, 'name'));
        $grantOnly = $benefit->access_mode === 'social_challenge_grant';
        $membership = $grantOnly ? 'Exclusivo para quienes tienen un premio vigente de un reto. No admite adquisición pública.' : 'Necesitas una membresía JAKAWI activa para canjear este beneficio.';
        $limit = $benefit->redemption_limit_per_member !== null ? "Límite: {$benefit->redemption_limit_per_member} canjes por miembro." : null;
        $deadline = $benefit->ends_at?->timezone('America/La_Paz')->format('d/m/Y H:i');
        $rules = [$membership];
        if ($placeSummary) $rules[] = 'Úsalo en: '.$placeSummary.'.';
        if ($deadline) $rules[] = 'Válido hasta el '.$deadline.'.';
        if ($benefit->starts_at) $rules[] = 'Disponible desde el '.$benefit->starts_at->timezone('America/La_Paz')->format('d/m/Y H:i').'.';
        if ($limit) $rules[] = $limit;
        if ($benefit->terms) $rules[] = $benefit->terms;
        $redemptionInstructions = $grantOnly ? 'Abre tu premio en el reto, elige una sucursal aplicable y activa el beneficio cuando estés listo para usarlo. El partner valida tu código.' : 'Visita una sucursal aplicable y activa el beneficio desde su ficha cuando estés listo para usarlo. El partner valida tu código.';
        $faq = [['question'=>'¿Cómo uso el beneficio?','answer'=>$redemptionInstructions],
            ['question'=>$grantOnly ? '¿Quién puede usarlo?' : '¿Necesito membresía?', 'answer'=>$membership]];
        if ($places) $faq[] = ['question'=>'¿Dónde puedo canjearlo?','answer'=>$placeSummary];
        if ($deadline) $faq[] = ['question'=>'¿Hasta cuándo es válido?','answer'=>'Hasta el '.$deadline.'.'];
        if ($limit) $faq[] = ['question'=>'¿Cuántas veces puedo usarlo?','answer'=>$limit];
        $native = route('benefits.show', $benefit);
        $available = $props['availability']['available'];
        $action = match (true) {
            !$available => ['label'=>$props['availability']['reason'], 'href'=>'#condiciones', 'kind'=>'unavailable'],
            $grantOnly => ['label'=>'VER MI PREMIO', 'href'=>$props['rewardUrl'] ?? $native, 'kind'=>'native'],
            !$request->user() => ['label'=>'QUIERO ESTE BENEFICIO','href'=>route('redemptions.start',$benefit), 'kind'=>'guest'],
            !$props['hasActiveMembership'] => ['label'=>'ACTIVAR MEMBRESÍA','href'=>'/membresia?'.http_build_query(['journey'=>'BENEFIT','action'=>'REDEEM','resource_id'=>$benefit->id]), 'kind'=>'membership'],
            default => ['label'=>'USAR BENEFICIO','href'=>$native,'kind'=>'native'],
        };
        return [
            'eyebrow'=>$this->editorial($landing->eyebrow) ?: 'BENEFICIO JAKAWI',
            'headline'=>$this->editorial($landing->headline) ?: $value,
            'subheadline'=>$this->editorial($landing->subheadline) ?: ($benefit->short_description ?: $benefit->description ?: "Aprovecha este beneficio en {$partner}."),
            'value'=>$value, 'description'=>$benefit->description, 'valueNote'=>$this->editorial($landing->reward_display_override),
            'partner'=>$partner, 'places'=>$places, 'deadline'=>$deadline, 'membership'=>$membership, 'rules'=>$rules,
            'benefits'=>array_slice(array_filter([
                ['title'=>$value,'description'=>$benefit->short_description ?: "Ofrecido por {$partner}."],
                ['title'=>"En {$partner}",'description'=>$placeSummary ?: 'Consulta la disponibilidad antes de visitarlo.'],
                $deadline ? ['title'=>'Vigencia clara','description'=>'Válido hasta el '.$deadline.'.'] : null,
            ]),0,3),
            'steps'=>[
                ['title'=>$grantOnly ? 'REVISA TU PREMIO' : ($request->user() ? ($props['hasActiveMembership'] ? 'PREPARA TU VISITA' : 'ACTIVA TU MEMBRESÍA') : 'CREA TU CUENTA'), 'description'=>$grantOnly ? $membership : ($request->user() && $props['hasActiveMembership'] ? 'Revisa las condiciones y elige una sucursal aplicable.' : 'Continúa con tu cuenta JAKAWI. '.$membership)],
                ['title'=>'VISITA EL PARTNER','description'=>'Acércate a una de las sucursales aplicables de '.$partner.'.'],
                ['title'=>'CANJEA','description'=>$redemptionInstructions],
            ],
            'faq'=>$faq,'action'=>$action, 'finalHeadline'=>$this->editorial($landing->final_cta_headline) ?: 'TU PRÓXIMO PLAN EMPIEZA AQUÍ.',
        ];
    }

    private function editorial(?string $copy): ?string
    {
        // Same editorial boundary as the existing Challenge presenter: facts stay on the subject.
        if (!$copy || preg_match('/\d|%|\b(?:gratis|gratuito|sin membresía|sin límites|cero|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|veinte|treinta|cien|ciento|mil|doble)\b/iu', $copy)) return null;
        return $copy;
    }
}
