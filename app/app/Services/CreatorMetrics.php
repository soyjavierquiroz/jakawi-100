<?php

namespace App\Services;

use App\Models\AttributionTouch;
use App\Models\Conversion;
use App\Models\ReferralRelationship;
use App\Models\User;
use Illuminate\Support\Collection;

class CreatorMetrics extends AffiliateMetrics
{
    /** @return Collection<int, array{content:string,clicks:int,registrations:int,purchases:int,revenue:string}> */
    public function byContent(User $creator, ?string $campaign = null, ?string $from = null, ?string $to = null): Collection
    {
        $touches = AttributionTouch::query()->where('referrer_user_id', $creator->id)->whereNotNull('referral_code')
            ->when($campaign, fn ($q) => $q->where('utm_campaign', $campaign))
            ->when($from, fn ($q) => $q->whereDate('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('occurred_at', '<=', $to))->get();
        $groups = $touches->groupBy(fn ($touch) => $touch->utm_content ?: 'Sin contenido');
        $relationships = ReferralRelationship::query()->where('referrer_user_id', $creator->id)->whereIn('attribution_touch_id', $touches->pluck('id'))->get()->keyBy('attribution_touch_id');
        $conversionCounts = Conversion::query()->where('type', 'membership_purchased')->where('status', 'confirmed')
            ->whereIn('referral_relationship_id', $relationships->pluck('id'))->get()->groupBy('referral_relationship_id');

        return $groups->map(function (Collection $items, string $content) use ($relationships, $conversionCounts) {
            $relationIds = $items->pluck('id')->map(fn ($id) => $relationships->get($id)?->id)->filter();
            $conversions = $relationIds->flatMap(fn ($id) => $conversionCounts->get($id, collect()));
            return ['content' => $content, 'clicks' => $items->count(), 'registrations' => $relationIds->count(), 'purchases' => $conversions->count(), 'revenue' => (string) $conversions->sum('eligible_amount')];
        })->values()->sortByDesc('clicks')->values();
    }
}
