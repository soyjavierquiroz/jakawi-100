<?php

namespace App\Discovery;

use App\Models\Location;
use App\Models\Partner;
use App\Models\Unlock;
use App\Services\MediaUrl;

/** Pure read adapter; progress is supplied by a batched caller. */
final readonly class UnlockOpportunityAdapter
{
    public function __construct(private MediaUrl $mediaUrl) {}

    public function adapt(Unlock $unlock, DiscoveryCity $city, UnlockProgress $progress, ?Partner $partner = null, ?Location $location = null, ?string $destinationUrl = null): DiscoveryOpportunity
    {
        $partnerVisible = ! $unlock->secret_mode || ! $unlock->hide_partner_until_unlock || $unlock->revealable();
        $locationVisible = ! $unlock->secret_mode || ! $unlock->hide_location_until_unlock || $unlock->revealable();
        $remaining = $progress->remaining();

        return new DiscoveryOpportunity(
            type: OpportunityType::UNLOCK,
            sourceId: $unlock->getKey(),
            title: $unlock->title,
            destinationUrl: $destinationUrl ?? route('unlocks.show', $unlock),
            city: $city,
            subtitle: $unlock->short_description,
            image: $this->mediaUrl->url($unlock->hero_path, 'hero'),
            partner: $partner && $partnerVisible ? new DiscoveryPartner($partner->getKey(), $partner->name, $partner->slug) : null,
            location: $location && $locationVisible ? new DiscoveryLocation($location->getKey(), $location->name, $location->zone) : null,
            categories: [],
            availability: new DiscoveryAvailability(in_array($unlock->status, [Unlock::ACTIVE, Unlock::GOAL_REACHED], true), $unlock->status),
            startsAt: $unlock->starts_at,
            endsAt: $unlock->commitment_deadline,
            primaryValue: "Faltan {$remaining}",
            secondaryValue: "{$progress->progress} de {$progress->target}",
            urgency: $unlock->commitment_deadline ? new DiscoveryUrgency('COMMITMENT_DEADLINE', at: $unlock->commitment_deadline) : null,
            editorialPriority: $unlock->featured ? 1_000_000.0 : 0.0,
            metadata: [
                'target' => $progress->target,
                'progress' => $progress->progress,
                'remaining' => $remaining,
                'state' => $unlock->status,
            ],
        );
    }
}
