<?php

namespace App\Discovery;

final readonly class DiscoveryPartner
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $logoUrl = null,
        public array $logoSrcset = [],
    ) {}

    /** @return array{id: int, name: string, slug: string, logo_url: ?string, logo_srcset: array} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'logo_url' => $this->logoUrl, 'logo_srcset' => $this->logoSrcset];
    }
}
