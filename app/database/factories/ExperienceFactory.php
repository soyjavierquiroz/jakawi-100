<?php

namespace Database\Factories;

use App\Models\Experience;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Experience> */
class ExperienceFactory extends Factory
{
    protected $model = Experience::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'title' => $title,
            'category' => 'experiences',
            'experience_type' => 'workshop',
            'currency' => 'BOB',
            'status' => 'draft',
            'featured' => false,
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

    public function withExternalReservation(): static
    {
        return $this->state(fn () => ['reservation_method' => 'external', 'reservation_url' => 'https://example.test/reserve']);
    }

    public function withWhatsAppReservation(): static
    {
        return $this->state(fn () => ['reservation_method' => 'whatsapp', 'reservation_whatsapp' => '+59170000000']);
    }
}
