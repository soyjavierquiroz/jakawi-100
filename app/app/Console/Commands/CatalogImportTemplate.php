<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CatalogImportTemplate extends Command
{
    protected $signature = 'jakawi:catalog-import-template';

    protected $description = 'Create a fictional CSV template for the JAKAWI catalog importer.';

    public function handle(): int
    {
        $path = storage_path('app/imports/jakawi-catalog-template.csv');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = fopen($path, 'w');
        if ($file === false) {
            $this->error('Could not create the catalog import template.');

            return self::FAILURE;
        }

        fputcsv($file, CatalogImport::HEADERS);
        fputcsv($file, ['fictional-luna-bakery', 'Fictional Luna Bakery', 'coffee', 'A completely fictional neighborhood bakery.', 'Merida', '123 Fictional Street', '@fictionallunabakery', '+529990000001', 'fictional-luna-bakery-2x1-pastry', '2 for 1 pastry', 'A fictional pastry promotion.', 'One per visit. Fictional example only.', '2x1', '75.00', '1', 'true', '2026-10-01', '2026-12-31']);
        fputcsv($file, ['fictional-luna-bakery', 'Fictional Luna Bakery', 'coffee', 'A completely fictional neighborhood bakery.', 'Merida', '123 Fictional Street', '@fictionallunabakery', '+529990000001', 'fictional-luna-bakery-coffee-discount', '20% coffee discount', 'A fictional coffee discount.', 'Valid for one drink.', 'discount', '40.00', '2', 'false', '', '']);
        fputcsv($file, ['fictional-orbit-yoga', 'Fictional Orbit Yoga', 'sport', 'A completely fictional yoga studio.', 'Merida', '456 Imaginary Avenue', '@fictionalorbityoga', '+529990000002', 'fictional-orbit-yoga-free-class', 'Free first class', 'A fictional introductory class.', 'Reservation required.', 'free_item', '', '', 'false', '', '']);
        fclose($file);

        $this->info("Template created: {$path}");

        return self::SUCCESS;
    }
}
