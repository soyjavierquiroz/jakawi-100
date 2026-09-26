<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaUploadService
{
    /** Store first, then delete an old immutable media object only after success. */
    public function replace(UploadedFile $file, string $kind, int $id, string $slot, ?string $oldKey = null): string
    {
        $key = $this->store($file, $kind, $id, $slot);
        $this->delete($oldKey);

        return $key;
    }

    /** Store an immutable object without changing a currently referenced object. */
    public function store(UploadedFile $file, string $kind, int $id, string $slot): string
    {
        $this->validate($file);
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['image' => 'Solo se permiten JPEG, PNG o WebP.']),
        };
        $key = $kind === 'avatars'
            ? sprintf('avatars/%d/%s.%s', $id, (string) Str::uuid(), $extension)
            : sprintf('%s/%d/%s/%s.%s', $kind, $id, $slot, (string) Str::uuid(), $extension);
        Storage::disk(config('media.disk'))->putFileAs(dirname($key), $file, basename($key), [
            'visibility' => 'private', 'CacheControl' => 'public, max-age=31536000, immutable',
        ]);
        return $key;
    }

    /** Delete only a known immutable media object. */
    public function delete(?string $key): void
    {
        if ($this->isMediaKey($key)) {
            Storage::disk(config('media.disk'))->delete($key);
        }
    }

    public function validate(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) || $file->getSize() > config('media.max_bytes')) {
            throw ValidationException::withMessages(['image' => 'La imagen debe ser JPEG, PNG o WebP y no superar 10 MB.']);
        }
        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] * $dimensions[1] > config('media.max_pixels')) {
            throw ValidationException::withMessages(['image' => 'Las dimensiones de la imagen no son válidas.']);
        }
    }

    public function isMediaKey(?string $key): bool
    {
        return is_string($key) && preg_match('#^(partners|benefits|experiences|locations)/\d+/(cover|logo|image)/[0-9a-f-]+\.(jpg|png|webp)$#', $key) === 1
            || is_string($key) && preg_match('#^avatars/\d+/[0-9a-f-]+\.(jpg|png|webp)$#', $key) === 1;
    }
}
