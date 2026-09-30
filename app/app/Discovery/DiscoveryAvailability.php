<?php

namespace App\Discovery;

final readonly class DiscoveryAvailability
{
    public function __construct(
        public bool $isAvailable,
        public ?string $state = null,
        public ?string $reason = null,
    ) {}

    /** @return array{is_available: bool, state: ?string, reason: ?string} */
    public function toArray(): array
    {
        return [
            'is_available' => $this->isAvailable,
            'state' => $this->state,
            'reason' => $this->reason,
        ];
    }
}
