<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogV2;
use Illuminate\Console\Command;

class ClearDemoQaCommand extends Command
{
    protected $signature = 'jakawi:clear-demo-qa';

    protected $description = 'Remove only Javier’s demo-linked pre-launch activity and tagged QA membership.';

    public function handle(DemoCatalogV2 $catalog): int
    {
        $counts = $catalog->clearQaActivity();
        $this->info('Demo QA activity cleared: '.json_encode($counts));

        return self::SUCCESS;
    }
}
