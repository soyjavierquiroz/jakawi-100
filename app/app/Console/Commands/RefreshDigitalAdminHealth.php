<?php
namespace App\Console\Commands;

use App\Models\DigitalProduct;
use App\Services\{DigitalCatalog, HostedApps};
use Illuminate\Console\Command;

/** Refreshes read-only catalog indicators outside HTTP requests. Never authorizes sales. */
class RefreshDigitalAdminHealth extends Command
{
    protected $signature = 'digital:refresh-admin-health';
    protected $description = 'Actualizar verificaciones informativas del catálogo digital sin modificar productos';

    public function handle(DigitalCatalog $catalog, HostedApps $apps): int
    {
        DigitalProduct::where('partner_id',$catalog->internalPartner()->id)->where('delivery_type','hosted_app')
            ->select(['id','app_key'])->chunkById(100,function ($products) use ($apps) {
                $apps->adminHealth($products->pluck('app_key')->all(),true);
            });
        $this->info('Verificaciones de catálogo actualizadas; productos y autorizaciones conservados.');
        return self::SUCCESS;
    }
}
