<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Partner;
use App\Models\User;
use App\Services\MediaReferences;
use App\Services\MediaUploadService;
use App\Services\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.disk' => 'public', 'media.imgproxy_url' => 'https://image.test', 'media.imgproxy_key' => str_repeat('ab', 32), 'media.imgproxy_salt' => str_repeat('cd', 32)]);
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function image(string $format = 'jpeg', int $width = 2000, int $height = 1000): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image), 'png' => imagepng($image), 'webp' => imagewebp($image)
        };
        $content = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('client-name.'.$format, $content);
    }

    private function upload(): string
    {
        return $this->postJson('/media/uploads', ['image' => $this->image()])->assertOk()->json('token');
    }

    private function fields(Partner $partner, array $extra = []): array
    {
        return $extra + $partner->only(['name', 'slug', 'entity_type', 'partner_type', 'status']);
    }

    public function test_jpeg_png_webp_are_decoded_normalized_and_keys_are_unique(): void
    {
        $service = app(MediaUploadService::class);
        $keys = [];
        foreach (['jpeg', 'png', 'webp'] as $format) {
            $key = $service->store($this->image($format), 'partners', 1, 'cover');
            $keys[] = $key;
            $this->assertMatchesRegularExpression('#^partners/1/cover/[0-9a-f-]+\.webp$#', $key);
            $info = getimagesizefromstring(Storage::disk('public')->get($key));
            $this->assertSame('image/webp', $info['mime']);
            $this->assertSame([1600, 800], array_slice($info, 0, 2));
        }
        $this->assertCount(3, array_unique($keys));
        $small = $service->store($this->image('png', 80, 40), 'partners', 1, 'logo');
        $this->assertSame([80, 40], array_slice(getimagesizefromstring(Storage::disk('public')->get($small)), 0, 2));
    }

    public function test_non_images_svg_oversized_and_truncated_content_are_rejected(): void
    {
        $source = $this->image();
        foreach ([UploadedFile::fake()->createWithContent('bad.jpg', 'not an image'), UploadedFile::fake()->createWithContent('bad.svg', '<svg/>'), UploadedFile::fake()->create('large.jpg', 10241, 'image/jpeg'), UploadedFile::fake()->createWithContent('truncated.jpg', substr(file_get_contents($source->getRealPath()), 0, 50))] as $file) {
            $this->postJson('/media/uploads', ['image' => $file])->assertUnprocessable();
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_exif_orientation_is_applied_and_original_metadata_is_discarded(): void
    {
        $source = $this->image('jpeg', 90, 40);
        $jpeg = file_get_contents($source->getRealPath());
        $exif = "Exif\0\0II".pack('vVv', 42, 8, 1).pack('vvVvvV', 0x0112, 3, 1, 6, 0, 0);
        $oriented = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        $file = UploadedFile::fake()->createWithContent('camera.jpg', $oriented);
        $this->assertSame(6, exif_read_data($file->getRealPath())['Orientation']);
        $key = app(MediaUploadService::class)->store($file, 'partners', 1, 'cover');
        $output = Storage::disk('public')->get($key);
        $this->assertSame([40, 90], array_slice(getimagesizefromstring($output), 0, 2));
        $this->assertStringNotContainsString('Exif', $output);
    }

    public function test_dimension_limit_rejected_before_decode(): void
    {
        config(['media.max_pixels' => 10]);
        $this->postJson('/media/uploads', ['image' => $this->image('png', 10, 10)])->assertUnprocessable();
    }

    public function test_runtime_variants_are_signed_and_mobile_has_small_candidate(): void
    {
        $key = app(MediaUploadService::class)->store($this->image(), 'benefits', 1, 'cover');
        $sources = app(MediaUrl::class)->srcset($key, 'hero');
        $this->assertSame([320, 480, 640, 960, 1600], array_column($sources, 'width'));
        $this->assertStringContainsString('/rs:fill:480:300/', $sources[1]['src']);
        $this->assertStringNotContainsString('/insecure/', $sources[1]['src']);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_partner_temporary_upload_replacement_and_removal_use_shared_pipeline(): void
    {
        $partner = Partner::factory()->create();
        $url = '/admin/partners/'.$partner->id;
        $this->put($url, $this->fields($partner, ['cover_upload' => $this->upload()]))->assertSessionHasNoErrors()->assertRedirect();
        $first = $partner->fresh()->cover_path;
        Storage::disk('public')->assertExists($first);
        $this->put($url, $this->fields($partner, ['cover_upload' => $this->upload()]))->assertSessionHasNoErrors();
        $second = $partner->fresh()->cover_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($first);
        Storage::disk('public')->assertExists($second);
        $this->put($url, $this->fields($partner, ['cover_remove' => true]))->assertSessionHasNoErrors();
        $this->assertNull($partner->fresh()->cover_path);
        $this->assertFalse(app(MediaReferences::class)->contains($second));
        Storage::disk('public')->assertExists($second);
    }

    public function test_failed_replacement_keeps_old_reference_and_form_is_atomic(): void
    {
        $old = app(MediaUploadService::class)->store($this->image(), 'partners', 1, 'cover');
        $partner = Partner::factory()->create(['cover_path' => $old]);
        $this->put('/admin/partners/'.$partner->id, $this->fields($partner, ['name' => 'Must roll back', 'cover_upload' => 'invalid-token']))->assertSessionHasErrors('cover');
        $this->assertSame($old, $partner->fresh()->cover_path);
        $this->assertSame($partner->name, $partner->fresh()->name);
        Storage::disk('public')->assertExists($old);
    }

    public function test_failed_storage_write_and_failed_db_save_keep_old_media(): void
    {
        $partner = Partner::factory()->create();
        $old = app(MediaUploadService::class)->store($this->image(), 'partners', $partner->id, 'cover');
        $partner->update(['cover_path' => $old]);
        $token = $this->upload();
        Partner::updating(function ($model) use ($old) {
            if ($model->cover_path !== $old) {
                throw new \RuntimeException('Simulated DB failure');
            }
        });
        try {
            $this->withoutExceptionHandling()->put('/admin/partners/'.$partner->id, $this->fields($partner, ['cover_upload' => $token]));
            $this->fail('Expected DB failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated DB failure', $exception->getMessage());
        } finally {
            Partner::flushEventListeners();
        }
        $this->assertSame($old, $partner->fresh()->cover_path);
        Storage::disk('public')->assertExists($old);
        $disk = Storage::disk('public');
        Storage::shouldReceive('disk')->andReturn($disk)->once();
        // Missing temporary object simulates unavailable/new upload storage.
        $disk->deleteDirectory('tmp');
        $request = Request::create('/', 'POST', ['cover_upload' => $token]);
        $request->setUserResolver(fn () => auth()->user());
        try {
            app(MediaUploadService::class)->fromRequest($request, 'cover', 'partners', $partner->id, 'cover', $old);
            $this->fail();
        } catch (ValidationException) {
            $this->assertSame($old, $partner->fresh()->cover_path);
        }
    }

    public function test_upload_token_cannot_be_used_by_another_user_or_as_client_path(): void
    {
        $token = $this->upload();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->create();
        $this->put('/admin/partners/'.$partner->id, $this->fields($partner, ['cover_upload' => $token]))->assertSessionHasErrors('cover');
        $this->assertNull($partner->fresh()->cover_path);
    }

    public function test_landing_local_upload_uses_pipeline_and_arbitrary_sources_are_rejected(): void
    {
        $benefit = Benefit::factory()->create();
        $url = '/admin/beneficios/'.$benefit->slug.'/landings';
        $fields = ['name' => 'Media QA', 'slug' => 'media-qa', 'media_mode' => 'IMAGE', 'hero_alt' => 'Photo'];
        $this->post($url, $fields + ['hero_upload' => $this->upload()])->assertSessionHasNoErrors()->assertRedirect();
        $landing = LandingPresentation::where('slug', 'media-qa')->sole();
        $this->assertStringStartsWith('landings/'.$landing->id.'/cover/', $landing->hero_path);
        Storage::disk('public')->assertExists($landing->hero_path);
        $this->put($url.'/'.$landing->slug, $fields + ['hero_path' => 'https://external.test/photo.jpg'])->assertSessionHasErrors('hero_path');
        $old = $landing->hero_path;
        $this->put($url.'/'.$landing->slug, $fields + ['hero_upload' => 'bad'])->assertSessionHasErrors('hero');
        $this->assertSame($old, $landing->fresh()->hero_path);
    }

    public function test_existing_catalog_image_forms_use_the_same_temporary_upload(): void
    {
        $benefit = Benefit::factory()->create();
        $this->put('/admin/benefits/'.$benefit->slug, ['slug' => $benefit->slug, 'title' => $benefit->title, 'status' => 'draft', 'partner_id' => $benefit->partner_id, 'location_scope' => 'all', 'image_upload' => $this->upload()])->assertSessionHasNoErrors();
        $this->assertStringStartsWith('benefits/', $benefit->fresh()->image_path);
        $experience = Experience::factory()->create();
        $this->put('/admin/experiences/'.$experience->slug, ['slug' => $experience->slug, 'title' => $experience->title, 'status' => 'draft', 'reservation_method' => 'none', 'image_upload' => $this->upload()])->assertSessionHasNoErrors();
        $this->assertStringStartsWith('experiences/', $experience->fresh()->image_path);
        $challenge = Challenge::create(['slug' => 'media-challenge', 'title' => 'Media challenge', 'status' => 'draft', 'reward_type' => 'JP', 'reward_jp_amount' => 10]);
        $this->post('/admin/retos/'.$challenge->slug.'/imagen', ['image_upload' => $this->upload()])->assertSessionHasNoErrors();
        $this->assertStringStartsWith('challenges/', $challenge->fresh()->hero_path);
    }

    public function test_gc_rechecks_references_and_fails_closed_on_query_errors(): void
    {
        $key = 'partners/1/cover/550e8400-e29b-41d4-a716-446655440000.webp';
        $this->object($key, 100);
        Storage::disk('public')->put('media-gc/'.hash('sha256', $key).'.json', json_encode(['key' => $key, 'since' => now()->subHours(49)->timestamp]));
        $refs = \Mockery::mock(MediaReferences::class);
        $refs->shouldReceive('all')->andReturn([]);
        $refs->shouldReceive('contains')->with($key)->andReturn(true)->once();
        $this->app->instance(MediaReferences::class, $refs);
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertExists($key);
        Storage::disk('public')->put('media-gc/'.hash('sha256', $key).'.json', json_encode(['key' => $key, 'since' => now()->subHours(49)->timestamp]));
        $refs->shouldReceive('contains')->with($key)->andThrow(new \RuntimeException('Query unavailable'))->once();
        $this->artisan('media:gc')->assertFailed();
        Storage::disk('public')->assertExists($key);
    }

    private function object(string $key, int $ageHours): void
    {
        $disk = Storage::disk('public');
        $disk->put($key, 'media');
        touch($disk->path($key), now()->subHours($ageHours)->timestamp);
    }

    public function test_gc_graces_shared_references_health_uncertain_keys_dry_run_and_idempotence(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $old = 'partners/1/cover/'.$uuid.'.webp';
        $young = 'benefits/2/cover/'.$uuid.'.webp';
        $shared = 'partners/2/logo/'.$uuid.'.webp';
        $tmp = 'tmp/'.$uuid.'/master.webp';
        $tmpYoung = 'tmp/550e8400-e29b-41d4-a716-446655440001/master.webp';
        foreach ([$old => 49, $young => 47, $shared => 100, $tmp => 25, $tmpYoung => 23, 'health/imgproxy-auth-test.txt' => 100, 'partners/unknown.jpg' => 100, 'backups/a' => 100, 'v2proof/a' => 100] as $key => $hours) {
            $this->object($key, $hours);
        }
        Partner::factory()->create(['logo_path' => $shared]);
        $landing = Benefit::factory()->create()->landingPresentations()->create(['name' => 'Shared', 'slug' => 'shared', 'hero_path' => $shared]);
        $this->artisan('media:gc --dry-run')->assertSuccessful();
        $this->assertCount(9, Storage::disk('public')->allFiles());
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertExists($old);
        Storage::disk('public')->assertMissing($tmp);
        $this->travel(49)->hours();
        $this->artisan('media:gc --dry-run')->assertSuccessful();
        Storage::disk('public')->assertExists($old);
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertMissing([$old, $tmp, $young]);
        Storage::disk('public')->assertExists([$shared, 'health/imgproxy-auth-test.txt', 'partners/unknown.jpg', 'backups/a', 'v2proof/a']);
        Partner::query()->update(['logo_path' => null]);
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertExists($shared);
        $landing->update(['hero_path' => null]);
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertExists($shared);
        $this->travel(49)->hours();
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertMissing($shared);
        $before = Storage::disk('public')->allFiles();
        $this->artisan('media:gc')->assertSuccessful();
        $this->assertSame($before, Storage::disk('public')->allFiles());
    }
}
