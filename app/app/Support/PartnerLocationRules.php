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

    public static function benefit(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('benefits', 'slug')],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(config('jakawi.categories'))],
            'benefit_type' => ['nullable', Rule::in(config('jakawi.benefit_types'))],
            'estimated_savings' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'redemption_limit_per_member' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(config('jakawi.publication_statuses'))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'applies_to_all_locations' => ['required', 'boolean'],
        ];
    }
}
