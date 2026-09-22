<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'exists:merchants,id'],
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'benefit_type' => ['nullable', 'string', 'max:255'],
            'estimated_savings' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'redemption_limit_per_member' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
