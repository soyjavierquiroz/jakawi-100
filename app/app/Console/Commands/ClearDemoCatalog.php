<?php

namespace App\Console\Commands;

use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearDemoCatalog extends Command
{
    protected $signature = 'jakawi:clear-demo-catalog';

    protected $description = 'Remove only fictional JAKAWI demo catalog records with demo- slugs.';

    public function handle(): int
    {
        $benefits = Benefit::query()->where('slug', 'like', 'demo-%')->delete();
        $merchants = Merchant::query()->where('slug', 'like', 'demo-%')->delete();
        $disk = Storage::disk(config('jakawi.demo_catalog.images.disk'));
        $disk->deleteDirectory('demo/merchants');
        $disk->deleteDirectory('demo/benefits');

        $this->info("Deleted {$benefits} demo benefits and {$merchants} demo merchants, plus demo images.");

        return self::SUCCESS;
    }
}
