<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class ExperienceRules
{
    public static function experience(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('experiences', 'slug')],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(config('jakawi.categories'))],
            'experience_type' => ['nullable', Rule::in(config('jakawi.experience_types'))],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'regular_price' => ['nullable', 'required_with:member_price', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'member_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'reservation_method' => ['nullable', Rule::in(config('jakawi.reservation_methods'))],
            'reservation_url' => ['nullable', 'url', 'required_if:reservation_method,url,external'],
            'reservation_whatsapp' => ['nullable', 'string', 'regex:/^\+?[1-9]\d{6,14}$/', 'required_if:reservation_method,whatsapp'],
            'reservation_phone' => ['nullable', 'string', 'regex:/^\+?[1-9]\d{6,14}$/', 'required_if:reservation_method,phone'],
            'status' => ['required', Rule::in(config('jakawi.publication_statuses'))],
        ];
    }

    public static function messages(): array
    {
        return [
            'required' => 'Completa :attribute.', 'required_with' => 'Completa :attribute cuando configures un precio preferencial.',
            'numeric' => ':attribute debe ser un número.', 'decimal' => 'Usa como máximo dos decimales en :attribute.',
            'integer' => ':attribute debe ser un número entero.', 'min' => ':attribute debe ser al menos :min.',
            'max' => ':attribute supera el máximo permitido (:max).', 'regex' => 'Revisa el formato de :attribute.',
            'lte' => 'El precio con membresía no debe superar el precio sin membresía.',
            'url' => 'Introduce una dirección web válida (http o https).', 'in' => 'Selecciona una opción válida para :attribute.',
            'unique' => 'Ese identificador de enlace ya está en uso.', 'exists' => 'La opción seleccionada ya no está disponible.',
        ];
    }

    public static function attributes(): array
    {
        return ['title' => 'el título comercial', 'slug' => 'el identificador del enlace', 'regular_price' => 'el precio sin membresía', 'member_price' => 'el precio con membresía', 'currency' => 'la moneda', 'duration_minutes' => 'la duración total', 'reservation_whatsapp' => 'el WhatsApp internacional', 'reservation_phone' => 'el teléfono internacional', 'catalog_visibility' => 'la visibilidad', 'status' => 'el estado editorial'];
    }

    public static function commercial(): array
    {
        return [
            'regular_price' => ['nullable', 'required_with:member_price', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'member_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999.99', 'lte:regular_price'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'catalog_visibility' => ['sometimes', Rule::in(['legacy', 'hidden', 'upcoming', 'active'])],
            'operations_confirmed' => ['sometimes', 'boolean'],
            'reservation_url' => ['nullable', 'url:http,https'],
            'reservation_whatsapp' => ['nullable', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'reservation_phone' => ['nullable', 'regex:/^\+?[1-9]\d{6,14}$/'],
        ];
    }

    public static function session(): array
    {
        return [
            'experience_id' => ['required', 'integer', Rule::exists('experiences', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(config('jakawi.experience_session_statuses'))],
        ];
    }

    public static function partnerAssignment(): array
    {
        return [
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')],
            'role' => ['required', Rule::in(config('jakawi.experience_partner_roles'))],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
