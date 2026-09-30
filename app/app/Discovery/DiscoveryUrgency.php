<?php

namespace App\Discovery;

use DateTimeInterface;

final readonly class DiscoveryUrgency
{
    public function __construct(
        public string $code,
        public ?string $label = null,
        public ?DateTimeInterface $at = null,
    ) {}

    /** @return array{code: string, label: ?string, at: ?string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'at' => $this->at?->format(DATE_ATOM),
        ];
    }
}
