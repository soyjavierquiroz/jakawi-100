<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CatalogV2TemplateCommand extends Command
{
    protected $signature = 'jakawi:catalog-v2-template {directory?}';
    protected $description = 'Create an empty JAKAWI Catalog Importer V2 CSV package';

    /** @var array<string, list<string>> */
    public const HEADERS = [
        'partners.csv' => ['slug','name','entity_type','partner_type','legal_name','tax_id','description','category','website','instagram','facebook','tiktok','phone','whatsapp','email','contact_name','contact_phone','contact_email','status','featured','internal_notes','sort_order','published_at'],
        'locations.csv' => ['slug','partner_slug','name','location_type','status','is_primary','country_code','region','city','zone','address','address_reference','latitude','longitude','maps_url','google_place_id','phone','whatsapp','email','website','instagram','facebook','tiktok','timezone','opening_hours','sort_order','published_at'],
        'benefits.csv' => ['slug','partner_slug','title','short_description','description','terms','category','benefit_type','estimated_savings','redemption_limit_per_member','status','featured','starts_at','ends_at','applies_to_all_locations','location_slugs','sort_order','published_at'],
        'experiences.csv' => ['slug','title','short_description','description','terms','category','experience_type','duration_minutes','regular_price','member_price','currency','reservation_method','reservation_url','reservation_whatsapp','reservation_phone','status','featured','sort_order','published_at'],
        'experience_partners.csv' => ['experience_slug','partner_slug','role','sort_order'],
        'experience_sessions.csv' => ['experience_slug','reference_key','location_slug','starts_at','ends_at','capacity','status','venue_label'],
    ];

    public function handle(): int
    {
        $directory = $this->argument('directory') ?: base_path('catalog-v2-template');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Unable to create {$directory}"); return self::FAILURE;
        }
        foreach (self::HEADERS as $file => $headers) file_put_contents($directory.'/'.$file, implode(',', $headers)."\n");
        file_put_contents($directory.'/README.txt', "JAKAWI Catalog Importer V2\n\nCSV files are UTF-8, comma separated, and require the exact header supplied. Run php artisan jakawi:import-catalog-v2 DIRECTORY for a dry run; add --apply to write to the configured database. Sessions require reference_key. No PINs, images, members, memberships, redemptions, or demo-* slugs are accepted.\n");
        $this->info("Catalog V2 template created in {$directory}"); return self::SUCCESS;
    }
}
