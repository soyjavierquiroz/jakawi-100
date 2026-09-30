<?php

namespace App\Discovery;

final readonly class DiscoveryLocation
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $label = null,
    ) {}

    /** @return array{id: int, name: string, label: ?string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'label' => $this->label];
    }
}
