<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogV2;
use Illuminate\Console\Command;

class SeedDemoQaCommand extends Command
{
    protected $signature = 'jakawi:seed-demo-qa';

    protected $description = 'Seed reversible pre-launch QA activity for Javier and the demo partner account.';

    public function handle(DemoCatalogV2 $catalog): int
    {
        try {
            $result = $catalog->seedQa(env('JAKAWI_QA_PARTNER_PASSWORD'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo QA data seeded: '.json_encode($result));

        return self::SUCCESS;
    }
}
