<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogV2;
use Illuminate\Console\Command;

class ClearDemoCatalogCommand extends Command
{
    protected $signature = 'jakawi:clear-demo-catalog';

    protected $description = 'Remove only demo-* JAKAWI V2 catalog records and demo media';

    public function handle(DemoCatalogV2 $catalog): int
    {
        $counts = $catalog->clear();
        $this->info('Demo catalog cleared: '.json_encode($counts));

        return self::SUCCESS;
    }
}
