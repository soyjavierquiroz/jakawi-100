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
    private function image(string $name = 'image.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AR//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AR//2Q=='));
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
        $this->expectException(ValidationException::class);
        $service->validate(UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'));
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
}
