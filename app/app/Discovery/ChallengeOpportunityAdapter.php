<?php
namespace App\Discovery;

use App\Models\Challenge;

final readonly class ChallengeOpportunityAdapter {
    public function adapt(Challenge $challenge, DiscoveryCity $city, ?string $destinationUrl = null): DiscoveryOpportunity {
        $action = $challenge->qualification_type === 'METRIC_THRESHOLD'
            ? 'Consigue '.number_format($challenge->qualification_target,0,',','.').' '.['likes'=>'likes','views'=>'vistas','comments'=>'comentarios'][$challenge->qualification_metric]
            : ($challenge->evidence_type === 'MANUAL' ? 'Completa el reto' : 'Publica y cumple las reglas');
        $reward = match ($challenge->reward_type) {
            'JP' => 'Gana '.$challenge->reward_jp_amount.' JP',
            'MANUAL_PRIZE' => $challenge->manual_prize_description,
            default => $challenge->benefit?->title ?? 'Beneficio',
        };
        if ($challenge->selection_type === 'FIRST_N') {
            $taken = $challenge->participations()->whereIn('selection_status',['candidate','selected'])->count();
            $reward = 'Quedan '.max(0,$challenge->winner_limit-$taken).' de '.$challenge->winner_limit.' premios';
        } elseif ($challenge->selection_type === 'TOP_N') $reward = 'Top '.$challenge->winner_limit.' al cierre · '.$reward;
        return new DiscoveryOpportunity(type: OpportunityType::CHALLENGE,sourceId:$challenge->id,title:$challenge->title,
            destinationUrl:$destinationUrl ?? route('social-challenges.show',$challenge),city:$city,subtitle:$action,
            image:app(\App\Services\MediaUrl::class)->url($challenge->hero_path,'hero'),
            partner:$challenge->partner ? new DiscoveryPartner($challenge->partner->id,$challenge->partner->name,$challenge->partner->slug) : null,
            startsAt:$challenge->starts_at,endsAt:$challenge->ends_at,primaryValue:$action,secondaryValue:$reward,
            metadata:['selection'=>$challenge->selection_type]);
    }
}
