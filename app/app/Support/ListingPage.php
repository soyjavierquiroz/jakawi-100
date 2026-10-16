<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

/** Shared bounds only; each domain owns its query, permissions and filters. */
final class ListingPage
{
    public static function make($query, int $size = 25, string $name = 'page'): LengthAwarePaginator
    {
        request()->validate([$name => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100', 'q' => 'nullable|string|max:200']);
        // Fixed sizes are intentional: client parameters cannot enlarge payloads.
        if ($query instanceof \Illuminate\Database\Eloquent\Builder) {
            $query->orderBy($query->getModel()->qualifyColumn('id'));
        }
        if ($query instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
            $query->orderBy($query->getRelated()->qualifyColumn('id'));
        }
        $page = $query->paginate($size, ['*'], $name)->withQueryString()->onEachSide(2);
        if ($page->currentPage() > $page->lastPage()) {
            $page = $query->paginate($size, ['*'], $name, $page->lastPage(), total: $page->total())->withQueryString()->onEachSide(2);
        }

        return $page;
    }
}
