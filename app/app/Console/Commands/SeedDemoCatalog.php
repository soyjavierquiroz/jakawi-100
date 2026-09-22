<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogImageGenerator;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Console\Command;

class SeedDemoCatalog extends Command
{
    protected $signature = 'jakawi:seed-demo-catalog';

    protected $description = 'Seed the fictional, idempotent JAKAWI demo catalog.';

    public function handle(DemoCatalogSeeder $seeder, DemoCatalogImageGenerator $imageGenerator): int
    {
        $seeder->run();
        $imageGenerator->generate();

        $this->info('Fictional demo catalog seeded with images: 12 merchants and 30 benefits.');

        return self::SUCCESS;
    }
}
