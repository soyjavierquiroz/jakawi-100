<?php

namespace App\Console\Commands;

use App\Services\MediaReferences;
use App\Services\MediaUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaGcCommand extends Command
{
    protected $signature = 'media:gc {--dry-run : Report candidates without deleting}';

    protected $description = 'Collect unreferenced JAKAWI media after 48h and temporary uploads after 24h';

    public function handle(MediaReferences $references, MediaUploadService $media): int
    {
        $stats = array_fill_keys(['scanned', 'referenced', 'recent_orphans_skipped', 'temp_expired', 'orphan_candidates_recorded', 'candidates', 'deleted', 'bytes_reclaimed', 'errors'], 0);
        $disk = Storage::disk(config('media.disk'));
        try {
            $referenceSet = $references->all();
            foreach (['partners', 'locations', 'benefits', 'experiences', 'challenges', 'landings', 'avatars', 'unlocks', 'tmp'] as $prefix) {
                foreach ($disk->allFiles($prefix) as $key) {
                    if (! $media->isMediaKey($key) && ! $media->isTemporaryKey($key)) {
                        continue;
                    }
                    $stats['scanned']++;
                    $marker = 'media-gc/'.hash('sha256', $key).'.json';
                    if (isset($referenceSet[$key])) {
                        $stats['referenced']++;
                        if (! $this->option('dry-run') && $disk->exists($marker)) {
                            $disk->delete($marker);
                        }

                        continue;
                    }
                    try {
                        $temp = $media->isTemporaryKey($key);
                        // Grace begins when GC observes the missing reference, not when an old photo was uploaded.
                        // Small private markers avoid a DB media model and survive scheduler restarts.
                        if (! $temp) {
                            if (! $disk->exists($marker)) {
                                if (! $this->option('dry-run')) {
                                    if (! $disk->put($marker, json_encode(['key' => $key, 'since' => now()->timestamp], JSON_THROW_ON_ERROR), ['visibility' => 'private'])) {
                                        throw new \RuntimeException('Candidate write failed');
                                    }
                                }
                                $stats['orphan_candidates_recorded']++;
                                $stats['recent_orphans_skipped']++;

                                continue;
                            }
                            $candidate = json_decode($disk->get($marker), true, 512, JSON_THROW_ON_ERROR);
                            if (($candidate['key'] ?? null) !== $key || ! is_int($candidate['since'] ?? null)) {
                                throw new \RuntimeException('Invalid candidate marker');
                            }
                            if ($candidate['since'] >= now()->subHours(48)->timestamp) {
                                $stats['recent_orphans_skipped']++;

                                continue;
                            }
                        }
                        if ($disk->lastModified($key) >= now()->subHours($temp ? 24 : 48)->timestamp) {
                            $stats['recent_orphans_skipped']++;

                            continue;
                        }
                        $bytes = $this->option('dry-run') ? 0 : $disk->size($key);
                        // Last external check before deletion: fail closed on any reference-query error.
                        if ($references->contains($key)) {
                            $stats['referenced']++;
                            if (! $this->option('dry-run') && $disk->exists($marker)) {
                                $disk->delete($marker);
                            }

                            continue;
                        }
                        if ($temp) {
                            $stats['temp_expired']++;
                        }
                        $stats['candidates']++;
                        if (! $this->option('dry-run')) {
                            if (! $disk->delete($key)) {
                                throw new \RuntimeException('Delete failed');
                            }
                            if (! $temp) {
                                $disk->delete($marker);
                            }
                            $stats['deleted']++;
                            $stats['bytes_reclaimed'] += $bytes;
                        }
                    } catch (\Throwable $exception) {
                        $stats['errors']++;
                        $this->error($key.': '.$exception->getMessage());
                    }
                }
            }
        } catch (\Throwable $exception) {
            $stats['errors']++;
            $this->error($exception->getMessage());
        }
        $this->line(json_encode($stats, JSON_THROW_ON_ERROR));

        return $stats['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
