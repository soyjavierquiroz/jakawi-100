<?php

namespace App\Console\Commands;

use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Console\Command;

class ClearDemoCatalog extends Command
{
    protected $signature = 'jakawi:clear-demo-catalog';

    protected $description = 'Remove only fictional JAKAWI demo catalog records with demo- slugs.';

    public function handle(): int
    {
        $benefits = Benefit::query()->where('slug', 'like', 'demo-%')->delete();
        $merchants = Merchant::query()->where('slug', 'like', 'demo-%')->delete();

        $this->info("Deleted {$benefits} demo benefits and {$merchants} demo merchants.");

        return self::SUCCESS;
    }
}
