<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Partner;
use App\Models\Unlock;
use App\Support\ListingPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLandingCatalog
{
    private const TYPES = ['BENEFIT' => Benefit::class, 'EXPERIENCE' => Experience::class, 'CHALLENGE' => Challenge::class, 'UNLOCK' => Unlock::class];

    public function page(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'partner' => ['nullable', 'integer', Rule::exists('partners', 'id')],
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'status' => ['nullable', Rule::in(['DRAFT', 'PUBLISHED', 'ARCHIVED'])],
            'video' => ['nullable', Rule::in(['yes', 'no'])],
        ]);
        $query = LandingPresentation::query()->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([
            Benefit::class => ['partner'], Experience::class => ['partners'], Challenge::class => ['partner'], Unlock::class => ['partner'],
        ])]);
        if ($filters['type'] ?? null) {
            $query->where('subject_type', (new (self::TYPES[$filters['type']]))->getMorphClass());
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['partner'] ?? null) {
            $query->whereHasMorph('subject', array_values(self::TYPES), function (Builder $product, string $type) use ($filters) {
                $product->whereHas($type === Experience::class ? 'partners' : 'partner', fn (Builder $partner) => $partner->where('partners.id', $filters['partner']));
            });
        }
        if ($term = trim($filters['q'] ?? '')) {
            // Literal substring search: percent and underscore are not wildcards.
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(function (Builder $search) use ($like) {
                $search->where('name', 'ilike', $like)->orWhere('headline', 'ilike', $like)
                    ->orWhereHasMorph('subject', array_values(self::TYPES), function (Builder $product, string $type) use ($like) {
                        $product->where(fn (Builder $match) => $match->where('title', 'ilike', $like)
                            ->orWhereHas($type === Experience::class ? 'partners' : 'partner', fn (Builder $partner) => $partner->where('name', 'ilike', $like)));
                    });
            });
        }
        $videoSql = LandingVideoConfiguration::configuredSql();
        if ($filters['video'] ?? null) {
            $query->whereRaw(($filters['video'] === 'no' ? 'NOT ' : '').'('.$videoSql.')');
        }
        $query->select('landing_presentations.*')->selectRaw('('.$videoSql.') AS has_video')->orderByDesc('updated_at')->orderByDesc('id');
        $page = ListingPage::make($query);
        $page->setCollection($page->getCollection()->map(function (LandingPresentation $landing) {
            $subject = $landing->subject;
            $type = $subject ? (array_search($subject::class, self::TYPES, true) ?: null) : null;
            $partners = $subject instanceof Experience ? $subject->partners->unique('id')->values() : collect($subject?->partner ? [$subject->partner] : []);
            $segment = ['BENEFIT' => 'beneficios', 'EXPERIENCE' => 'experiencias', 'CHALLENGE' => 'retos', 'UNLOCK' => 'desbloqueos'][$type] ?? null;
            $hasVideo = (bool) $landing->has_video;
            $config = $hasVideo ? app(LandingVideoConfiguration::class)->forPresentation($landing) : null;

            return [
                'id' => $landing->id, 'name' => $landing->name, 'headline' => $landing->headline, 'status' => $landing->status,
                'updated_at' => $landing->updated_at?->toIso8601String(),
                'partners' => $partners->map(fn (Partner $p) => $p->only('id', 'name'))->all(),
                'product' => $subject ? ['title' => $subject->title, 'type' => $type] : null,
                'has_video' => $hasVideo,
                'video_note' => $hasVideo ? ($config ? 'Configurado' : 'Acceso restringido') : (! $landing->vsl ? 'Sin video' : (in_array($landing->media_mode?->value, ['IMAGE', 'NONE'], true) && ! ($subject instanceof Unlock) ? 'Fuera de composición' : (blank($landing->vsl['source']['url'] ?? null) ? 'Falta enlace' : 'Enlace inválido'))),
                'video' => $config,
                'edit_url' => $subject ? '/admin/landings/'.$landing->slug.'/edit' : null,
                'preview_url' => ! $subject ? null : ($landing->status === 'PUBLISHED' ? route('landing-presentations.show', $landing, false) : '/admin/'.$segment.'/'.$subject->slug.'/landings/'.$landing->slug.'/preview'),
            ];
        }));
        $partners = Partner::orderBy('name')->orderBy('id')->limit(20)->get(['id', 'name']);
        $selected = ! empty($filters['partner']) ? Partner::find($filters['partner'], ['id', 'name']) : null;
        if ($selected) {
            $partners = $partners->prepend($selected)->unique('id')->values();
        }

        return ['landings' => $page->items(), 'pagination' => array_diff_key($page->toArray(), ['data' => true]), 'filters' => $filters,
            'partnerChoices' => $partners, 'selectedPartner' => $selected];
    }
}
