<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\LandingPresentation;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** A projection of existing public offers; no independent catalog or economic state. */
class PublicOfferCatalog
{
    private function eligible(string $model): Builder
    {
        $query = $model::query()->whereIn('status', ['published', 'paused', 'archived'])
            ->where(fn ($q) => $q->where('status', 'published')->orWhereNotNull('published_at'))
            ->whereHas('landingPresentations', fn ($q) => $q->where('status', 'PUBLISHED'));

        return $model === Benefit::class
            ? $query->publicAccess()->whereHas('partner', fn ($q) => $q->published())
            : $query->whereIn('catalog_visibility', ['legacy', 'upcoming', 'active'])
                ->whereHas('partners', fn ($q) => $q->published());
    }

    public function page(int $perPage, int $page): array
    {
        $benefits = $this->eligible(Benefit::class)->select('id')->selectRaw("'benefit' AS type");
        $experiences = $this->eligible(Experience::class)->select('id')->selectRaw("'experience' AS type");
        $pagination = DB::query()->fromSub($benefits->unionAll($experiences), 'offers')
            ->orderBy('type')->orderBy('id')->paginate($perPage, ['*'], 'page', $page);
        $pagination->withPath(rtrim(config('app.url'), '/').'/api/catalog/v1/offers');
        $keys = $pagination->getCollection();
        $models = collect();

        foreach (['benefit' => Benefit::class, 'experience' => Experience::class] as $type => $class) {
            $ids = $keys->where('type', $type)->pluck('id');
            if ($ids->isEmpty()) continue;
            $table = (new $class)->getTable();
            // One real published landing per offer. Prefer the guest destination already
            // used by COM, then ALL, then a standalone published campaign landing.
            $landing = LandingPresentation::query()->select('id')
                ->where('subject_type', (new $class)->getMorphClass())
                ->whereColumn('subject_id', $table.'.id')->where('status', 'PUBLISHED')
                ->orderByRaw("CASE default_scope WHEN 'GUESTS' THEN 0 WHEN 'ALL' THEN 1 ELSE 2 END")
                ->orderBy('id')->limit(1);
            $query = $this->eligible($class)->whereKey($ids)
                ->select(['id', 'title', 'short_description', 'category', 'image_path', 'status', 'updated_at'])
                ->addSelect(['catalog_landing_id' => $landing]);
            if ($type === 'benefit') {
                $query->addSelect(['partner_id', 'starts_at', 'ends_at', 'applies_to_all_locations'])
                    ->with('partner:id,name,status');
            } else {
                $query->addSelect(['cover_path', 'catalog_visibility'])
                    ->with(['partners' => fn ($q) => $q->published()->select('partners.id', 'name')])
                    ->withExists(['sessions as has_upcoming_session' => fn ($q) => $q->upcoming()])
                    ->withExists(['sessions as has_unknown_session_end' => fn ($q) => $q->where('status', 'scheduled')->whereNull('ends_at')])
                    ->addSelect(['last_session_end' => ExperienceSession::query()
                        ->selectRaw('MAX(ends_at)')->whereColumn('experience_id', 'experiences.id')
                        ->where('status', 'scheduled')]);
            }
            foreach ($query->get() as $model) $models->put($type.':'.$model->id, $model);
        }

        $landings = LandingPresentation::query()->whereKey($models->pluck('catalog_landing_id'))
            ->get(['id', 'slug', 'status', 'hero_path', 'social_image_path', 'media_mode'])->keyBy('id');
        $cities = $this->cities($models);
        $data = [];
        foreach ($keys as $key) {
            $model = $models->get($key->type.':'.$key->id);
            $landing = $model ? $landings->get($model->catalog_landing_id) : null;
            // Fail closed if publication changed during this read.
            if (! $landing || $landing->status !== 'PUBLISHED') continue;
            $image = in_array($landing->media_mode?->value, ['NONE', 'VSL'], true) ? null
                : ($landing->hero_path ?: ($model instanceof Experience ? ($model->cover_path ?: $model->image_path) : $model->image_path));
            $row = [
                'id' => $key->type.':'.$model->id,
                'com_id' => $model->id,
                'type' => $key->type,
                'name' => $model->title,
                'short_description' => $model->short_description,
                'image_url' => $this->publicImage($image) ?: app(MediaUrl::class)->url(app(LandingSeo::class)->imagePath($landing, $model), 'social'),
                'partners' => ($model instanceof Benefit ? collect([$model->partner]) : $model->partners)
                    ->unique('id')->sortBy('id')->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
                'cities' => $cities[$key->type.':'.$model->id] ?? [],
                'category' => $model->category,
                'landing_url' => rtrim(config('app.url'), '/').route('landing-presentations.show', $landing->slug, false),
                'publication_status' => $model->status,
                'landing_status' => $landing->status,
                'availability_status' => $this->availability($model),
                'updated_at' => $model->updated_at?->toISOString(),
            ];
            if ($model instanceof Benefit) {
                $row['starts_at'] = $model->starts_at?->toISOString();
                $row['ends_at'] = $model->ends_at?->toISOString();
            }
            $data[] = $row;
        }

        return [
            'data' => $data,
            'meta' => ['current_page' => $pagination->currentPage(), 'per_page' => $pagination->perPage(),
                'total' => $pagination->total(), 'last_page' => $pagination->lastPage()],
            'links' => ['next' => $pagination->appends(['per_page' => $perPage])->nextPageUrl(),
                'prev' => $pagination->previousPageUrl()],
        ];
    }

    private function availability(Model $offer): string
    {
        if (in_array($offer->status, ['paused', 'archived'], true)) return $offer->status;
        if ($offer instanceof Benefit) {
            if ($offer->ends_at?->lt(now())) return 'expired';
            if ($offer->starts_at?->gt(now())) return 'upcoming';
            return $offer->isAvailable() ? 'available' : 'unavailable';
        }
        if ($offer->catalog_visibility === 'upcoming') return 'upcoming';
        if ($offer->has_upcoming_session) return 'scheduled';
        return ! $offer->has_unknown_session_end && $offer->last_session_end && now()->gt($offer->last_session_end) ? 'ended' : 'unavailable';
    }

    private function publicImage(?string $path): ?string
    {
        $url = app(MediaUrl::class)->url($path, 'hero');
        if ($url && str_starts_with($url, '/')) $url = rtrim(config('app.url'), '/').$url;
        return $url && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }

    private function cities($models): array
    {
        $result = [];
        foreach (['benefit', 'experience'] as $type) {
            $offers = $models->filter(fn ($m) => ($m instanceof Benefit) === ($type === 'benefit'));
            if ($offers->isEmpty()) continue;
            $query = Location::query()->where('locations.status', 'published')->leftJoin('cities', 'cities.id', '=', 'locations.city_id');
            if ($type === 'benefit') {
                $query->join('benefits', 'benefits.partner_id', '=', 'locations.partner_id')
                    ->whereIn('benefits.id', $offers->pluck('id'))
                    ->where(fn ($q) => $q->where('benefits.applies_to_all_locations', true)->orWhereExists(fn ($q) => $q->selectRaw('1')
                        ->from('benefit_location')->whereColumn('benefit_id', 'benefits.id')->whereColumn('location_id', 'locations.id')));
                $id = 'benefits.id';
            } else {
                $query->join('experience_sessions', 'experience_sessions.location_id', '=', 'locations.id')
                    ->whereIn('experience_sessions.experience_id', $offers->pluck('id'))->where('experience_sessions.status', 'scheduled');
                $id = 'experience_sessions.experience_id';
            }
            foreach ($query->selectRaw($id." AS offer_id, COALESCE(cities.name, locations.city) AS catalog_city")->distinct()->get() as $row) {
                if (filled($row->catalog_city)) $result[$type.':'.$row->offer_id][] = $row->catalog_city;
            }
        }
        foreach ($result as &$names) { $names = array_values(array_unique($names)); sort($names, SORT_STRING); }
        return $result;
    }
}
