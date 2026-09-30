<?php

namespace App\Discovery;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Services\MediaUrl;

/**
 * Pure read adapter. The caller owns loading and selecting the optional
 * partner/location context; this adapter never traverses relations.
 */
final readonly class BenefitOpportunityAdapter
{
    public function __construct(private MediaUrl $mediaUrl) {}

    public function adapt(Benefit $benefit, DiscoveryCity $city, Partner $partner, ?Location $location = null): DiscoveryOpportunity
    {
        return new DiscoveryOpportunity(
            type: OpportunityType::BENEFIT,
            sourceId: $benefit->getKey(),
            title: $benefit->title,
            destinationUrl: route('benefits.show', $benefit),
            city: $city,
            subtitle: $benefit->short_description,
            image: $this->mediaUrl->url($benefit->image_path, 'benefit_card'),
            partner: new DiscoveryPartner($partner->getKey(), $partner->name, $partner->slug),
            location: $location ? new DiscoveryLocation($location->getKey(), $location->name, $location->zone) : null,
            categories: filled($benefit->category) ? [$benefit->category] : [],
            availability: new DiscoveryAvailability($this->isListed($benefit), $benefit->status),
            startsAt: $benefit->starts_at,
            endsAt: $benefit->ends_at,
            // A public description may already state the offer. Never turn an
            // estimated saving into an achieved saving in Discovery.
            primaryValue: $benefit->short_description ?: $benefit->benefit_type,
            secondaryValue: $benefit->short_description ? $benefit->benefit_type : null,
            editorialPriority: $this->editorialPriority($benefit->featured, $benefit->sort_order),
            metadata: filled($benefit->benefit_type) ? ['benefit_type' => $benefit->benefit_type] : [],
        );
    }

    private function isListed(Benefit $benefit): bool
    {
        $now = now();

        return $benefit->status === 'published'
            && ($benefit->starts_at === null || $benefit->starts_at->lte($now))
            && ($benefit->ends_at === null || $benefit->ends_at->gte($now));
    }

    /** Featured content always outranks non-featured; lower sort_order wins within a group. */
    private function editorialPriority(bool $featured, ?int $sortOrder): float
    {
        return ($featured ? 1_000_000 : 0) - (float) ($sortOrder ?? 0);
    }
}
