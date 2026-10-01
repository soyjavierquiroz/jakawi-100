<?php

namespace App\Discovery;

use App\Models\City;
use App\Models\User;

/** Minimal, caller-owned read context. City selection is resolved upstream. */
final readonly class DiscoveryContext
{
    public function __construct(
        public City $city,
        public ?User $user = null,
        public string $surface = 'discovery',
        public int $candidateLimitPerDomain = 24,
        public int $forYouLimit = 8,
        public int $happeningNowLimit = 8,
        public int $discoverMoreLimit = 12,
    ) {}
}
