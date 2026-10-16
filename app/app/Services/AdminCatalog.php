<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\Partner;
use Illuminate\Http\Request;

class AdminCatalog
{
    public function listing(Request $request, string $resource): array
    {
        [$model, $title, $relation, $base, $create] = match ($resource) {
            'benefits' => [Benefit::class, 'Beneficios', 'partner', '/admin/benefits', '/admin/benefits/create'],
            'experiences' => [Experience::class, 'Experiencias', 'partners', '/admin/experiences', '/admin/experiences/create'],
            'challenges' => [Challenge::class, 'Retos', 'partner', '/admin/retos', '/admin/retos/crear'],
        };
        $filters = $request->validate([
            'sort' => 'nullable|in:updated,newest,name', 'review_filter' => 'nullable|in:SUBMITTED', 'q' => 'nullable|string|max:200', 'status_filter' => 'nullable|string|max:30',
            'partner_filter' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1',
        ]);
        $query = $model::query()->with([$relation, 'landingPresentations']);
        if ($term = trim($filters['q'] ?? '')) {
            // Literal search: %, _ and backslash should not act as wildcards.
            $like = '%'.addcslashes($term, '\\%_').'%';
            $query->where(fn ($q) => $q->where('title', 'ilike', $like)->orWhereHas($relation, fn ($p) => $p->where('name', 'ilike', $like)));
        }
        if ($resource === 'challenges' && ($filters['review_filter'] ?? null)) {
            $query->where('review_status', $filters['review_filter']);
        }
        if ($filters['status_filter'] ?? null) {
            $query->where('status', $filters['status_filter']);
        }
        if ($filters['partner_filter'] ?? null) {
            $query->whereHas($relation, fn ($p) => $p->where('partners.id', $filters['partner_filter']));
        }
        $items = \App\Support\ListingPage::make($query->orderBy(($filters['sort'] ?? '') === 'name' ? 'title' : (($filters['sort'] ?? '') === 'newest' ? 'created_at' : 'updated_at'), ($filters['sort'] ?? '') === 'name' ? 'asc' : 'desc')->orderByDesc('id'), 25);
        $items->through(fn ($item) => $this->row($item, $resource));

        return [
            'title' => $title, 'resource' => $resource, 'baseUrl' => $base, 'createUrl' => $create,
            'items' => $items, 'filters' => $filters,
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
            'statuses' => $model::query()->distinct()->orderBy('status')->pluck('status'),
            'prelaunch' => (bool) config('jakawi.prelaunch_mode'),
        ];
    }

    private function row($item, string $resource): array
    {
        $partners = $item instanceof Experience ? $item->partners->unique('id')->values() : collect($item->partner ? [$item->partner] : []);
        $landing = $item->landingPresentations->sortByDesc('id')->first();
        $prefix = match ($resource) {
            'experiences' => 'experiencias', 'benefits' => 'beneficios', default => 'retos'
        };
        $landingBase = '/admin/'.$prefix.'/'.$item->slug.'/landings';
        $value = null;
        $money = fn ($amount, $currency = 'BOB') => ($currency === 'BOB' ? 'Bs ' : $currency.' ').number_format((float) $amount, 2, '.', ',');
        if ($item instanceof Experience) {
            $values = [];
            if ($item->regular_price !== null) {
                $values[] = $money($item->regular_price, $item->currency).' · sin membresía';
            }
            if ($item->member_price !== null) {
                $values[] = $money($item->member_price, $item->currency).' · miembros';
            }
            $value = implode(' / ', $values) ?: null;
            $public = app(ExperiencePublication::class)->isPublic($item);
        } elseif ($item instanceof Benefit) {
            $types = ['percentage' => 'Descuento porcentual', 'fixed_amount' => 'Descuento fijo', 'two_for_one' => '2 por 1', 'free_item' => 'Producto de cortesía', 'upgrade' => 'Upgrade', 'exclusive_access' => 'Acceso exclusivo', 'other' => 'Otro beneficio'];
            $value = $types[$item->benefit_type] ?? null;
            if ($item->estimated_savings !== null) {
                $value = ($value ? $value.' · ' : '').'Ahorro estimado '.$money($item->estimated_savings);
            }
            $public = $item->isAvailable() && $item->access_mode !== Benefit::ACCESS_CHALLENGE_GRANT;
        } else {
            $value = match ($item->reward_type) {
                'JP' => $item->reward_jp_amount.' JP', 'MANUAL_PRIZE' => $item->manual_prize_description, 'BENEFIT' => 'Beneficio como premio', default => null
            };
            $public = $item->isPublic();
        }
        // Use the same viewer scope as the Admin's public navigation, without extra queries.
        $publicLanding = $item->landingPresentations->where('status', 'PUBLISHED')->where('default_scope', 'ALL')->sortBy('id')->first();
        $native = '/'.$prefix.'/'.$item->slug;

        return [
            'id' => $item->id, 'title' => $item->title, 'slug' => $item->slug,
            'partners' => $partners->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'url' => route('admin.partners.edit', $p->id)])->all(),
            'imageUrl' => app(MediaUrl::class)->url(($item instanceof Experience ? ($item->cover_path ?: $item->image_path) : ($item instanceof Benefit ? $item->image_path : $item->hero_path)) ?: $partners->first()?->logo_path, 'thumbnail'),
            'value' => $value, 'status' => $item->status, 'reviewStatus' => $item instanceof Challenge ? $item->review_status : null,
            'updatedAt' => $item->updated_at?->toIso8601String(),
            'startsAt' => $item->starts_at?->toIso8601String(), 'endsAt' => $item->ends_at?->toIso8601String(),
            'landingStatus' => $landing?->status,
            'editUrl' => $item instanceof Challenge ? route('admin.social-challenges.edit', $item) : route('admin.'.$resource.'.edit', $item),
            'previewUrl' => $landing ? $landingBase.'/'.$landing->slug.'/preview' : ($item instanceof Experience ? route('admin.experiences.preview', $item) : null),
            'publicUrl' => $public ? ($publicLanding ? '/l/'.$publicLanding->slug : $native) : null,
            'reviewUrl' => $item instanceof Challenge ? route('admin.social-challenges.show', $item) : null,
        ];
    }
}
