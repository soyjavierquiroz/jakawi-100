<?php
namespace Tests\Feature;

use App\Services\DigitalStorageMigration;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitalStorageMigrationTest extends TestCase
{
    public function test_mirror_retains_originals_verifies_integrity_and_supports_rollback(): void
    {
        $local = Storage::fake('legacy-test'); $minio = Storage::fake('minio-test');
        $key = 'products/550e8400-e29b-41d4-a716-446655440000.pdf';
        $local->put($key, '%PDF-1.4 QA ORIGINAL');
        $service = app(DigitalStorageMigration::class);
        $this->assertFalse($service->sync($local, $minio, false)[0]['verified']);
        $minio->assertMissing($key);
        $manifest = $service->sync($local, $minio, true);
        $this->assertTrue($manifest[0]['verified']);
        $this->assertSame(hash('sha256', $local->get($key)), $manifest[0]['sha256']);
        $this->assertSame($manifest, $service->sync($local, $minio, true));
        $local->assertExists($key);
        $later = 'qr/550e8400-e29b-41d4-a716-446655440001.png';
        $minio->put($later, 'QA LATER UPLOAD');
        $service->sync($minio, $local, true);
        $this->assertSame('QA LATER UPLOAD', $local->get($later));
        $minio->assertExists($later);
    }

    public function test_conflicting_destination_is_never_overwritten(): void
    {
        $local = Storage::fake('legacy-test'); $minio = Storage::fake('minio-test');
        $key = 'products/550e8400-e29b-41d4-a716-446655440000.pdf';
        $local->put($key, 'ORIGINAL'); $minio->put($key, 'DIFFERENT');
        try { app(DigitalStorageMigration::class)->sync($local, $minio, true); $this->fail('Conflict accepted'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('integrity mismatch', $e->getMessage()); }
        $this->assertSame('ORIGINAL', $local->get($key));
        $this->assertSame('DIFFERENT', $minio->get($key));
    }
}
