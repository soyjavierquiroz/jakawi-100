<?php

namespace Tests\Feature;

use App\Services\MediaUploadService;
use App\Services\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaInfrastructureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('media.disk', 'public');
    }

    private function image(string $name = 'image.jpg'): UploadedFile
    {
        $images = [
            'jpg' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AR//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AR//2Q==',
            'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbhwAAAABJRU5ErkJggg==',
            'webp' => 'UklGRiIAAABXRUJQVlA4IC4AAAAwAQCdASoBAAEAAUAmJaQAA3AA/vuUAAA=',
        ];
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return UploadedFile::fake()->createWithContent($name, base64_decode($images[$extension]));
    }

    public function test_media_uses_immutable_object_keys_and_replaces_after_store(): void
    {
        Storage::fake('public');
        $service = app(MediaUploadService::class);
        $first = $service->replace($this->image(), 'benefits', 42, 'cover');
        $second = $service->replace($this->image(), 'benefits', 42, 'cover', $first);
        $this->assertMatchesRegularExpression('#^benefits/42/cover/[0-9a-f-]+\.jpg$#', $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_svg_and_absurd_dimensions_are_rejected(): void
    {
        $service = app(MediaUploadService::class);
        foreach ([
            UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            UploadedFile::fake()->create('too-large.jpg', 10 * 1024 + 1, 'image/jpeg'),
            UploadedFile::fake()->createWithContent('too-many-pixels.png', $this->png(10_000, 5_001)),
        ] as $file) {
            try {
                $service->validate($file);
                $this->fail("Expected {$file->getClientOriginalName()} to be rejected.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_signed_webp_urls_use_presets_and_legacy_files_stay_local(): void
    {
        config()->set('media.imgproxy_url', 'https://img.test');
        config()->set('media.imgproxy_key', base64_encode(random_bytes(32)));
        config()->set('media.imgproxy_salt', base64_encode(random_bytes(32)));
        $url = app(MediaUrl::class)->url('benefits/42/cover/550e8400-e29b-41d4-a716-446655440000.jpg', 'benefit_card');
        $this->assertStringStartsWith('https://img.test/', $url);
        $this->assertStringContainsString('/rs:fill:640:480/q:80/f:webp/', $url);
        $this->assertStringNotContainsString('/insecure/', $url);
        $this->assertStringEndsWith('/storage/demo/legacy.png', app(MediaUrl::class)->url('demo/legacy.png'));
    }

    public function test_all_supported_formats_get_immutable_keys_and_all_presets_emit_webp(): void
    {
        Storage::fake('public');
        $service = app(MediaUploadService::class);
        foreach (['image.jpg', 'image.png', 'image.webp'] as $name) {
            $key = $service->replace($this->image($name), 'experiences', 7, 'cover');
            $this->assertMatchesRegularExpression('#^experiences/7/cover/[0-9a-f-]+\\.(jpg|png|webp)$#', $key);
            Storage::disk('public')->assertExists($key);
        }

        config()->set('media.imgproxy_url', 'https://img.test');
        config()->set('media.imgproxy_key', bin2hex(random_bytes(32)));
        config()->set('media.imgproxy_salt', bin2hex(random_bytes(32)));
        $key = 'experiences/7/cover/550e8400-e29b-41d4-a716-446655440000.jpg';
        foreach (['hero', 'benefit_card', 'experience_card', 'avatar'] as $preset) {
            $url = app(MediaUrl::class)->url($key, $preset);
            $this->assertStringContainsString('/f:webp/', $url);
            $this->assertStringStartsWith('https://img.test/', $url);
        }
        $this->assertSame([320], array_column(app(MediaUrl::class)->srcset($key, 'thumbnail'), 'width'));
        $this->assertSame([320, 480, 640], array_column(app(MediaUrl::class)->srcset($key, 'benefit_card'), 'width'));
        $this->assertSame([], app(MediaUrl::class)->srcset('legacy/file.jpg', 'hero'));
    }

    private function png(int $width, int $height): string
    {
        return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', $width, $height)."\x08\x02\x00\x00\x00".pack('N', 0).pack('N', 0).'IEND'.pack('N', 0);
    }
}
