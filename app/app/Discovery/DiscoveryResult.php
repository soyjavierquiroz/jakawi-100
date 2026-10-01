<?php

namespace App\Discovery;

/** Section-ready, non-persisted Discovery output. */
final readonly class DiscoveryResult
{
    /** @param list<DiscoveryOpportunity> $forYou @param list<DiscoveryOpportunity> $happeningNow @param list<DiscoveryOpportunity> $discoverMore */
    public function __construct(
        public ?DiscoveryOpportunity $hero,
        public array $forYou,
        public array $happeningNow,
        public array $discoverMore,
    ) {}
}
