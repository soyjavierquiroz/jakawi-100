<?php

namespace Database\Factories;

use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExperienceSession> */
class ExperienceSessionFactory extends Factory
{
    protected $model = ExperienceSession::class;

    public function definition(): array
    {
        return [
            'experience_id' => Experience::factory(),
            'starts_at' => now()->addWeek(),
            'status' => 'scheduled',
        ];
    }

    public function scheduled(): static { return $this->state(fn () => ['status' => 'scheduled']); }
    public function cancelled(): static { return $this->state(fn () => ['status' => 'cancelled']); }
    public function upcoming(): static { return $this->scheduled()->state(fn () => ['starts_at' => now()->addDay()]); }
    public function past(): static { return $this->scheduled()->state(fn () => ['starts_at' => now()->subDay()]); }
    public function withLocation(?Location $location = null): static { return $this->for($location ?? Location::factory(), 'location'); }
    public function withoutLocation(): static { return $this->state(fn () => ['location_id' => null]); }
}
