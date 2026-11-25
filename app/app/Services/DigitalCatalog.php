<?php

namespace App\Services;

use App\Models\DigitalProduct;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;

class DigitalCatalog
{
    public function internalPartner(): Partner
    {
        return Partner::where('slug', 'jakawi-digital')->firstOrFail();
    }

    public function assertInternal(DigitalProduct $product): void
    {
        abort_unless($product->partner_id === $this->internalPartner()->id, 403);
    }

    public function query(string $term = '', string $sort = 'featured'): Builder
    {
        $q = DigitalProduct::published()->with('partner:id,name,slug');
        if ($term !== '') {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $q->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('short_description', 'ilike', $like)->orWhere('description', 'ilike', $like)->orWhere('commercial_content', 'ilike', $like));
        }

        return $sort === 'name' ? $q->orderBy('name')->orderBy('id') : $q->orderByDesc('id');
    }

    public function present(DigitalProduct $p, ?bool $comingSoon = null): array
    {
        return $p->only(['id', 'name', 'slug', 'short_description', 'description', 'commercial_content', 'public_price', 'member_price', 'currency', 'delivery_type', 'support_information', 'access_terms']) + [
            'partner' => $p->partner->only(['id', 'name', 'slug']), 'cover_url' => app(MediaUrl::class)->url($p->cover_path, 'hero'),
            'gallery_urls' => array_map(fn ($path) => app(MediaUrl::class)->url($path, 'hero'), $p->gallery_paths ?? []),
            'category' => $p->category,
            'coming_soon' => $comingSoon ?? $p->isComingSoon(),
            'url' => app(ProductLandingResolver::class)->navigationUrl($p, request()->user() !== null),
        ];
    }

}
