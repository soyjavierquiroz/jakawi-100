<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;

/** Copy immutable objects without changing database references or deleting originals. */
class DigitalStorageMigration
{
    public function sync(FilesystemAdapter $source, FilesystemAdapter $destination, bool $apply): array
    {
        $manifest = [];
        foreach ($source->allFiles() as $path) {
            if (! preg_match('#^(products|qr|receipts|statements)/[a-f0-9-]+\\.(pdf|png|jpg)$#D', $path)) {
                throw new RuntimeException('Unexpected private object key; migration stopped.');
            }
            $bytes = $source->get($path);
            $size = strlen($bytes);
            $hash = hash('sha256', $bytes);
            if ($source->size($path) !== $size) throw new RuntimeException('Source size changed.');
            $exists = $destination->exists($path);
            if (! $exists && $apply) {
                $destination->put($path, $bytes, ['visibility' => 'private', 'CacheControl' => 'private, no-store']);
            }
            if ($exists || $apply) {
                if ($destination->size($path) !== $size || ! hash_equals($hash, hash('sha256', $destination->get($path)))) {
                    throw new RuntimeException('Object conflict or integrity mismatch: '.$path);
                }
            }
            $manifest[] = ['path' => $path, 'size' => $size, 'sha256' => $hash, 'verified' => $exists || $apply];
        }

        return $manifest;
    }
}
