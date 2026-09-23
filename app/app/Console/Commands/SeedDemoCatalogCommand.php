<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogV2;
use Illuminate\Console\Command;

class SeedDemoCatalogCommand extends Command
{
    protected $signature = 'jakawi:seed-demo-catalog';

    protected $description = 'Seed the deterministic fictional JAKAWI V2 demo catalog and local SVG media';

    public function handle(DemoCatalogV2 $catalog): int
    {
        $counts = $catalog->seed();
        $this->info('Demo catalog seeded: '.json_encode($counts));

        return self::SUCCESS;
    }
}
