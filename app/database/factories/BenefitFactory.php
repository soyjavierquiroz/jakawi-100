<?php

namespace Database\Factories;

use App\Models\Benefit;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Benefit> */
class BenefitFactory extends Factory
{
    protected $model = Benefit::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'partner_id' => Partner::factory(),
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'title' => $title,
            'category' => 'food',
            'benefit_type' => 'percentage',
            'estimated_savings' => '10.00',
            'redemption_limit_per_member' => 1,
            'status' => 'draft',
            'featured' => false,
            'applies_to_all_locations' => false,
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['featured' => true]);
    }

    public function future(): static
    {
        return $this->published()->state(fn () => ['starts_at' => now()->addDay()]);
    }

    public function expired(): static
    {
        return $this->published()->state(fn () => ['ends_at' => now()->subDay()]);
    }

    public function unlimited(): static
    {
        return $this->state(fn () => ['redemption_limit_per_member' => null]);
    }

    public function forPartner(Partner $partner): static
    {
        return $this->for($partner);
    }
}
