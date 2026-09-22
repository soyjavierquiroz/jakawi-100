<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $name = fake()->company().' '.fake()->city();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'name' => $name,
            'location_type' => 'branch',
            'status' => 'draft',
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    public function withPartner(?Partner $partner = null): static
    {
        return $this->for($partner ?? Partner::factory());
    }

    public function withoutPartner(): static
    {
        return $this->state(fn () => ['partner_id' => null]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()]);
    }
}
