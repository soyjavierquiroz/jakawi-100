<?php

namespace Database\Factories;

use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Benefit> */
class BenefitFactory extends Factory
{
    protected $model = Benefit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'merchant_id' => Merchant::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'terms' => fake()->sentence(),
            'benefit_type' => fake()->randomElement(['2x1', 'discount', 'free_item', 'experience', 'other']),
            'estimated_savings' => fake()->randomFloat(2, 50, 500),
            'image_path' => null,
            'is_active' => true,
            'is_featured' => false,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
