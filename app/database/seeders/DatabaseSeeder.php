<?php

namespace Database\Seeders;

use App\Models\Benefit;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $merchants = collect([
            ['name' => 'Demo Burger', 'category' => 'food'],
            ['name' => 'Demo Padel', 'category' => 'sport'],
            ['name' => 'Demo Cafe', 'category' => 'coffee'],
        ])->map(fn (array $data, int $index) => Merchant::factory()->create([
            ...$data,
            'slug' => null,
            'is_featured' => $index === 0,
            'sort_order' => $index,
        ]));

        $merchants->each(function (Merchant $merchant, int $index) {
            Benefit::factory()
                ->count($index === 0 ? 3 : 2)
                ->create([
                    'merchant_id' => $merchant->id,
                    'slug' => null,
                    'is_featured' => $index === 0,
                    'sort_order' => $index,
                ]);
        });
    }
}
