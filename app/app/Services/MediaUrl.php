<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    public function url(?string $key, string $preset = 'thumbnail', ?int $width = null): ?string
    {
        if (! $key) return null;
        if (! (new MediaUploadService)->isMediaKey($key)) return Storage::disk('public')->url($key);
        $presetConfig = config('media.presets.'.$preset);
        if (! $presetConfig || ! config('media.imgproxy_url') || ! config('media.imgproxy_key') || ! config('media.imgproxy_salt')) return null;
        $width ??= $presetConfig['width'];
        $height = (int) round($presetConfig['height'] * ($width / $presetConfig['width']));
        $path = sprintf('/rs:%s:%d:%d/q:%d/f:webp/plain/s3://%s/%s', $presetConfig['fit'], $width, $height, $presetConfig['quality'], config('media.bucket'), $key);

        return config('media.imgproxy_url').'/'.$this->signature($path).$path;
    }

    /** @return array<int, array{src: string, width: int}> */
    public function srcset(?string $key, string $preset): array
    {
        if (! $key || ! (new MediaUploadService)->isMediaKey($key)) return [];
        return collect([320, 480, 640, 960, 1280])->filter(fn ($width) => $width <= config('media.presets.'.$preset.'.width', 0))->map(fn ($width) => ['src' => $this->url($key, $preset, $width), 'width' => $width])->filter(fn ($item) => $item['src'])->values()->all();
    }

    private function signature(string $path): string
    {
        $key = $this->decode((string) config('media.imgproxy_key'));
        $salt = $this->decode((string) config('media.imgproxy_salt'));
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $salt.$path, $key, true)), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        if (ctype_xdigit($value) && strlen($value) % 2 === 0) {
            return hex2bin($value) ?: $value;
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: $value;
    }
}
