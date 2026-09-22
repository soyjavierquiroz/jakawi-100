<?php

namespace App\Console\Commands;

use App\Services\DemoCatalogImageGenerator;
use Illuminate\Console\Command;

class GenerateDemoImages extends Command
{
    protected $signature = 'jakawi:generate-demo-images {--force : Regenerate valid SVG files that already exist}';

    protected $description = 'Generate deterministic local SVG images for fictional JAKAWI demo catalog records.';

    public function handle(DemoCatalogImageGenerator $generator): int
    {
        $counts = $generator->generate((bool) $this->option('force'));

        $this->info("Demo images ready: {$counts['merchant_logos']} merchant logos, {$counts['merchant_covers']} merchant covers, {$counts['benefit_images']} benefit images generated; {$counts['skipped']} existing files skipped.");

        return self::SUCCESS;
    }
}
