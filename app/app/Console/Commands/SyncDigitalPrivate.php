<?php

namespace App\Console\Commands;

use App\Services\DigitalStorageMigration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncDigitalPrivate extends Command
{
    protected $signature = 'digital:sync-private {--apply : Copy and verify; otherwise inventory only} {--restore-local : Copy MinIO objects back before rolling back to the legacy disk}';
    protected $description = 'Safely mirror immutable digital files; retain originals and all database references';

    public function handle(DigitalStorageMigration $migration): int
    {
        $local = Storage::build(['driver' => 'local', 'root' => storage_path('app/digital-private'), 'throw' => true]);
        $minio = Storage::build(array_replace(config('filesystems.disks.minio-media'), [
            'root' => 'digital-private', 'http' => ['connect_timeout' => 5, 'timeout' => 30],
        ]));
        $restore = $this->option('restore-local');
        $manifest = $migration->sync($restore ? $minio : $local, $restore ? $local : $minio, (bool) $this->option('apply'));
        $this->line(json_encode(['direction' => $restore ? 'minio-to-local' : 'local-to-minio', 'applied' => (bool) $this->option('apply'), 'objects' => $manifest], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
