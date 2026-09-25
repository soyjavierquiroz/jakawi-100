<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogV2;
use Illuminate\Console\Command;

class ClearDemoCatalogCommand extends Command
{
    protected $signature = 'jakawi:clear-demo-catalog';

    protected $description = 'Remove only demo-* catalog, media, and Javier demo-linked QA activity';

    public function handle(DemoCatalogV2 $catalog): int
    {
        $counts = $catalog->clear();
        $this->info('Demo catalog cleared: '.json_encode($counts));

        return self::SUCCESS;
    }
}
