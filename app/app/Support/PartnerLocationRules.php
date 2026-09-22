<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class PartnerLocationRules
{
    public static function partner(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'entity_type' => ['required', Rule::in(config('jakawi.partner_entity_types'))],
            'partner_type' => ['required', Rule::in(config('jakawi.partner_types'))],
            'category' => ['nullable', Rule::in(config('jakawi.categories'))],
            'status' => ['required', Rule::in(config('jakawi.publication_statuses'))],
        ];
    }

    public static function location(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'location_type' => ['required', Rule::in(config('jakawi.location_types'))],
            'status' => ['required', Rule::in(config('jakawi.publication_statuses'))],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'redemption_pin' => ['nullable', 'regex:/^\\d{6}$/'],
        ];
    }
}
