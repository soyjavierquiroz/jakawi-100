<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrepareSocialImage extends Command
{
    protected $signature = 'media:prepare-social-image';
    protected $description = 'Ensure the corporate landing social fallback exists in the current MinIO bucket';

    public function handle(): int
    {
        $disk = Storage::disk(config('media.disk'));
        $key = config('media.social_fallback');
        $bytes = file_get_contents(resource_path('media/jakawi-social.webp'));
        if (! $disk->exists($key)) {
            if (! $disk->put($key, $bytes, ['visibility' => 'private', 'ContentType' => 'image/webp', 'CacheControl' => 'public, max-age=31536000, immutable'])) {
                $this->error('Corporate image upload failed.');
                return self::FAILURE;
            }
        }
        if (hash('sha256', $disk->get($key)) !== hash('sha256', $bytes)) {
            $this->error('Corporate image verification failed.');
            return self::FAILURE;
        }
        $this->info('Corporate social image ready.');
        return self::SUCCESS;
    }
}
