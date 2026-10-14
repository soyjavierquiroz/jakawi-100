<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Unlock;
use App\Models\UserProfile;

class MediaReferences
{
    // Include drafts, archived records and shared references, not just public content.
    public const FIELDS = [
        Partner::class => ['logo_path', 'cover_path', 'gallery_paths'],
        Location::class => ['image_path'],
        Benefit::class => ['image_path'],
        Experience::class => ['image_path', 'cover_path'],
        Challenge::class => ['hero_path'],
        Unlock::class => ['hero_path'],
        LandingPresentation::class => ['hero_path'],
        UserProfile::class => ['avatar_path'],
    ];

    public function all(): array
    {
        $keys = [];
        foreach (self::FIELDS as $model => $fields) {
            $model::query()->select(['id', ...$fields])->chunkById(500, function ($rows) use ($fields, &$keys) {
                foreach ($rows as $row) {
                    foreach ($fields as $field) {
                        foreach (is_array($row->$field) ? $row->$field : [$row->$field] as $key) {
                            if (filled($key)) {
                                $keys[$key] = true;
                            }
                        }
                    }
                }
            });
        }

        return $keys;
    }

    public function contains(string $key): bool
    {
        foreach (self::FIELDS as $model => $fields) {
            if ($model::query()->where(function ($query) use ($fields, $key) {
                foreach ($fields as $field) {
                    if ($field === 'gallery_paths') {
                        $query->orWhereJsonContains($field, $key);
                    } else {
                        $query->orWhere($field, $key);
                    }
                }
            })->exists()) {
                return true;
            }
        }

        return false;
    }
}
