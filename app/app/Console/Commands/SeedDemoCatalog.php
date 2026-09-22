<?php

namespace App\Console\Commands;

use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Console\Command;

class SeedDemoCatalog extends Command
{
    protected $signature = 'jakawi:seed-demo-catalog';

    protected $description = 'Seed the fictional, idempotent JAKAWI demo catalog.';

    public function handle(DemoCatalogSeeder $seeder): int
    {
        $seeder->run();

        $this->info('Fictional demo catalog seeded: 12 merchants and 30 benefits.');

        return self::SUCCESS;
    }
}
