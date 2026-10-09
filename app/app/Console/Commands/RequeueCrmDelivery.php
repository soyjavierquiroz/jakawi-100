<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RequeueCrmDelivery extends Command
{
    protected $signature = 'crm:requeue {deliveryId}';

    protected $description = 'Requeue one DEAD or RETRY CRM delivery without changing its event or payload';

    public function handle(): int
    {
        $id = (string) $this->argument('deliveryId');
        if (! preg_match('/^[1-9][0-9]*$/D', $id)) {
            $this->error('Invalid delivery ID.');

            return self::FAILURE;
        }
        $changed = DB::transaction(function () use ($id): bool {
            DB::statement("SET LOCAL lock_timeout = '2s'");
            $row = DB::table('crm_deliveries')->where('id', $id)->lockForUpdate()->first(['id', 'status']);
            if (! $row || ! in_array($row->status, ['DEAD', 'RETRY'], true)) {
                return false;
            }
            DB::table('crm_deliveries')->where('id', $id)->update([
                'status' => 'RETRY', 'next_attempt_at' => now(),
            ]);

            return true;
        });
        if (! $changed) {
            $this->error('Delivery missing or not DEAD/RETRY.');

            return self::FAILURE;
        }
        $this->info("CRM delivery {$id} requeued; attempts and event preserved.");

        return self::SUCCESS;
    }
}
