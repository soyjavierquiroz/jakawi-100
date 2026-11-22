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
        \App\Models\DigitalProduct::class => ['cover_path', 'gallery_paths'],
        Partner::class => ['logo_path', 'cover_path', 'gallery_paths'],
        Location::class => ['image_path'],
        Benefit::class => ['image_path'],
        Experience::class => ['image_path', 'cover_path'],
        Challenge::class => ['hero_path'],
        Unlock::class => ['hero_path'],
        LandingPresentation::class => ['hero_path', 'social_image_path'],
        UserProfile::class => ['avatar_path'],
    ];

    public function all(): array
    {
        $keys = [config('media.social_fallback') => true];
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

        LandingPresentation::query()->select(['id', 'editorial_blocks'])->chunkById(500, function ($rows) use (&$keys) {
            foreach ($rows as $row) {
                foreach ($this->editorialImages($row->editorial_blocks ?? []) as $key) $keys[$key] = true;
            }
        });

        return $keys;
    }

    public function contains(string $key): bool
    {
        if ($key === config('media.social_fallback')) return true;
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

        foreach (LandingPresentation::query()->select(['id', 'editorial_blocks'])->lazyById(500) as $row) {
            if (in_array($key, $this->editorialImages($row->editorial_blocks ?? []), true)) return true;
        }

        return false;
    }
    private function editorialImages(array $blocks): array
    {
        $keys = [];
        array_walk_recursive($blocks, function ($value, $field) use (&$keys) {
            if ($field === 'image_path' && is_string($value) && $value !== '') $keys[] = $value;
        });

        return $keys;
    }
}
