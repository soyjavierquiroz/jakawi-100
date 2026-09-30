<?php

namespace App\Discovery;

final readonly class DiscoveryCity
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}

    /** @return array{id: int, name: string, slug: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
    }
}
