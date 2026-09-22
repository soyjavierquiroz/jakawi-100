<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Partner> */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'name' => $name,
            'entity_type' => 'organization',
            'partner_type' => 'business',
            'category' => 'food',
            'status' => 'draft',
            'featured' => false,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()]);
    }
}
