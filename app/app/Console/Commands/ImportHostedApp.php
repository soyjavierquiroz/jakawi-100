<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\HostedAppCatalogImport;
class ImportHostedApp extends Command
{
    protected $signature = 'hosted-app:import';
    protected $description = 'Importa metadatos técnicos JSON por stdin como producto borrador; nunca publica ni autoriza venta.';
    public function handle(HostedAppCatalogImport $import): int
    {
        try {
            $data = json_decode(stream_get_contents(STDIN, 15000000),true,512,JSON_THROW_ON_ERROR);
            $this->line(json_encode($import->run($data),JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
