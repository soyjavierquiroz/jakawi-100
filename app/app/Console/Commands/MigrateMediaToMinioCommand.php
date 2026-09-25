<?php

namespace App\Console\Commands;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\UserProfile;
use App\Services\MediaUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateMediaToMinioCommand extends Command
{
    protected $signature = 'jakawi:migrate-media-to-minio {--apply : Copy supported local files and update their object-key fields}';
    protected $description = 'Safely migrate legacy local raster media to the private MinIO media disk (dry run by default).';

    public function handle(MediaUploadService $media): int
    {
        $moved = $skipped = $failed = 0;
        foreach ([
            [Partner::class, 'logo_path', 'partners', 'logo'], [Partner::class, 'cover_path', 'partners', 'cover'],
            [Location::class, 'image_path', 'locations', 'cover'], [Benefit::class, 'image_path', 'benefits', 'cover'],
            [Experience::class, 'image_path', 'experiences', 'cover'], [Experience::class, 'cover_path', 'experiences', 'cover'],
            [UserProfile::class, 'avatar_path', 'avatars', 'avatar'],
        ] as [$model, $column, $kind, $slot]) {
            $model::query()->whereNotNull($column)->each(function ($record) use ($column, $kind, $slot, $media, &$moved, &$skipped, &$failed) {
                $old = $record->{$column};
                if ($media->isMediaKey($old)) { $skipped++; return; }
                if (! Storage::disk('public')->exists($old)) { $this->warn("missing: {$old}"); $skipped++; return; }
                $mime = Storage::disk('public')->mimeType($old);
                $extension = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null };
                if (! $extension) { $this->warn("unsupported (kept local): {$old}"); $skipped++; return; }
                $id = $kind === 'avatars' ? $record->user_id : $record->id;
                $new = $kind === 'avatars' ? "avatars/{$id}/".Str::uuid().".{$extension}" : "{$kind}/{$id}/{$slot}/".Str::uuid().".{$extension}";
                $this->line("{$old} -> {$new}");
                if (! $this->option('apply')) { $moved++; return; }
                try {
                    Storage::disk(config('media.disk'))->writeStream($new, Storage::disk('public')->readStream($old), ['visibility' => 'private', 'CacheControl' => 'public, max-age=31536000, immutable']);
                    $record->update([$column => $new]);
                    $moved++;
                } catch (\Throwable $e) { $this->error("failed: {$old}: {$e->getMessage()}"); $failed++; }
            });
        }
        $this->info("moved={$moved} skipped={$skipped} failures={$failed}".($this->option('apply') ? '' : ' (dry run)'));
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
