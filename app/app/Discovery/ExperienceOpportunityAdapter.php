<?php

namespace App\Discovery;

use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Services\ExperiencePublication;
use App\Services\MediaUrl;
use InvalidArgumentException;

/**
 * Pure read adapter. A caller must deliberately select an upcoming session in
 * the requested city before calling this adapter.
 */
final readonly class ExperienceOpportunityAdapter
{
    public function __construct(private MediaUrl $mediaUrl) {}

    public function adapt(Experience $experience, DiscoveryCity $city, ExperienceSession $session, Location $location, ?Partner $partner = null, ?string $destinationUrl = null): DiscoveryOpportunity
    {
        if ($session->location_id !== $location->getKey() || $location->city_id !== $city->id) {
            throw new InvalidArgumentException('The selected experience session location must belong to the discovery city.');
        }

        return new DiscoveryOpportunity(
            type: OpportunityType::EXPERIENCE,
            sourceId: $experience->getKey(),
            title: $experience->title,
            destinationUrl: $destinationUrl ?? route('experiences.show', $experience),
            city: $city,
            subtitle: $experience->short_description,
            image: $this->mediaUrl->url($experience->image_path ?: $experience->cover_path, 'experience_card'),
            partner: $partner ? new DiscoveryPartner($partner->getKey(), $partner->name, $partner->slug) : null,
            location: new DiscoveryLocation($location->getKey(), $location->name, $location->zone),
            categories: filled($experience->category) ? [$experience->category] : [],
            availability: new DiscoveryAvailability(app(ExperiencePublication::class)->canOperate($experience), $session->status),
            startsAt: $session->starts_at,
            endsAt: $session->ends_at,
            // Date/time formatting remains a frontend concern.
            secondaryValue: filled($experience->experience_type) ? $experience->experience_type : null,
            editorialPriority: $this->editorialPriority($experience->featured, $experience->sort_order),
            metadata: array_filter([
                'experience_type' => $experience->experience_type,
                'reservation_method' => $experience->reservation_method,
            ], static fn (?string $value): bool => filled($value)),
        );
    }

    public function proposal(Experience $experience, DiscoveryCity $city, ?Partner $partner, string $destinationUrl): DiscoveryOpportunity
    {
        return new DiscoveryOpportunity(
            type: OpportunityType::EXPERIENCE, sourceId: $experience->getKey(), title: $experience->title,
            destinationUrl: $destinationUrl, city: $city, subtitle: $experience->short_description,
            image: $this->mediaUrl->url($experience->image_path ?: $experience->cover_path, 'experience_card'),
            partner: $partner ? new DiscoveryPartner($partner->getKey(), $partner->name, $partner->slug) : null,
            categories: filled($experience->category) ? [$experience->category] : [],
            availability: new DiscoveryAvailability(false, 'upcoming', 'En preparación · No admite reservas'),
            secondaryValue: 'En preparación', editorialPriority: $this->editorialPriority($experience->featured, $experience->sort_order),
        );
    }

    /** Featured content always outranks non-featured; lower sort_order wins within a group. */
    private function editorialPriority(bool $featured, ?int $sortOrder): float
    {
        return ($featured ? 1_000_000 : 0) - (float) ($sortOrder ?? 0);
    }
}
