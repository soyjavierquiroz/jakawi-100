<?php

namespace App\Discovery;

use DateTimeInterface;
use JsonSerializable;

/**
 * Non-persisted public Discovery read model. Adapters may only use metadata for
 * small public, adapter-owned scalar values; never domain models, economic
 * state, private identifiers, contact data, or attribution internals.
 */
final readonly class DiscoveryOpportunity implements JsonSerializable
{
    /**
     * @param list<string> $categories Canonical configured category strings.
     * @param array<string, bool|float|int|string|null> $metadata Public adapter-owned scalar values only.
     */
    public function __construct(
        public OpportunityType $type,
        public int|string $sourceId,
        public string $title,
        public string $destinationUrl,
        public DiscoveryCity $city,
        public ?string $subtitle = null,
        public ?string $image = null,
        public ?DiscoveryPartner $partner = null,
        public ?DiscoveryLocation $location = null,
        public array $categories = [],
        public ?DiscoveryAvailability $availability = null,
        public ?DateTimeInterface $startsAt = null,
        public ?DateTimeInterface $endsAt = null,
        public ?string $primaryValue = null,
        public ?string $secondaryValue = null,
        public ?DiscoveryUrgency $urgency = null,
        public ?float $editorialPriority = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array{
     *     type: string, source_id: int|string, title: string, destination_url: string,
     *     city: array{id: int, name: string, slug: string}, subtitle: ?string, image: ?string,
     *     partner: ?array{id: int, name: string, slug: string}, location: ?array{id: int, name: string, label: ?string},
     *     categories: list<string>, availability: ?array{is_available: bool, state: ?string, reason: ?string},
     *     starts_at: ?string, ends_at: ?string, primary_value: ?string, secondary_value: ?string,
     *     urgency: ?array{code: string, label: ?string, at: ?string}, editorial_priority: ?float,
     *     metadata: array<string, bool|float|int|string|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'source_id' => $this->sourceId,
            'title' => $this->title,
            'destination_url' => $this->destinationUrl,
            'city' => $this->city->toArray(),
            'subtitle' => $this->subtitle,
            'image' => $this->image,
            'partner' => $this->partner?->toArray(),
            'location' => $this->location?->toArray(),
            'categories' => $this->categories,
            'availability' => $this->availability?->toArray(),
            'starts_at' => $this->startsAt?->format(DATE_ATOM),
            'ends_at' => $this->endsAt?->format(DATE_ATOM),
            'primary_value' => $this->primaryValue,
            'secondary_value' => $this->secondaryValue,
            'urgency' => $this->urgency?->toArray(),
            'editorial_priority' => $this->editorialPriority,
            'metadata' => $this->metadata,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
