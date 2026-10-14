<?php

namespace Tests\Feature;

use App\Models\{Partner, User};
use App\Services\{MediaReferences, MediaUploadService, MediaUrl};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerEditorialMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media.disk' => 'public', 'media.imgproxy_url' => 'https://image.test', 'media.imgproxy_key' => str_repeat('ab', 32), 'media.imgproxy_salt' => str_repeat('cd', 32)]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('editorial.png', 1080, 1350);
    }

    private function paths(Partner $partner, int $count): array
    {
        return array_map(fn () => app(MediaUploadService::class)->store($this->image(), 'partners', $partner->id, 'gallery'), range(1, $count));
    }

    private function save(Partner $partner, array $fields = [])
    {
        return $this->put('/admin/partners/'.$partner->id, $fields + $partner->only(['name', 'slug', 'entity_type', 'partner_type', 'status']));
    }

    public function test_public_gallery_is_optional_and_preserves_editorial_order_with_signed_responsive_images(): void
    {
        $partner = Partner::factory()->published()->create();
        $this->get('/'.$partner->slug)->assertInertia(fn (Assert $page) => $page->has('partner.gallery', 0));
        $paths = $this->paths($partner, 3);
        foreach ([array_slice($paths, 0, 1), $paths] as $selected) {
            $partner->update(['gallery_paths' => $selected]);
            $this->get('/'.$partner->slug)->assertInertia(function (Assert $page) use ($selected) {
                $page->has('partner.gallery', count($selected))->missing('partner.gallery_paths');
                foreach ($selected as $index => $key) {
                    $page->where('partner.gallery.'.$index.'.image_url', app(MediaUrl::class)->url($key, 'partner_editorial'))
                        ->where('partner.gallery.'.$index.'.image_srcset.0.width', 320);
                }
            });
        }
        $this->assertStringContainsString('/rs:fill:960:1200/', app(MediaUrl::class)->url($paths[0], 'partner_editorial'));
    }

    public function test_model_rejects_four_images_and_external_or_foreign_keys(): void
    {
        $partner = Partner::factory()->create();
        $paths = $this->paths($partner, 4);
        foreach ([$paths, ['https://external.test/image.webp'], ['partners/999/gallery/550e8400-e29b-41d4-a716-446655440000.webp'], ['not-a-key']] as $invalid) {
            try {
                $partner->update(['gallery_paths' => $invalid]);
                $this->fail('Expected gallery validation');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('gallery_paths', $exception->errors());
                $this->assertNull($partner->fresh()->gallery_paths);
            }
        }
    }

    public function test_admin_three_slots_upload_replace_remove_are_atomic_and_keep_logo_cover(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->create(['logo_path' => 'existing-logo.webp', 'cover_path' => 'existing-cover.webp']);
        $tokens = [];
        for ($slot = 1; $slot <= 3; $slot++) {
            $tokens['gallery_'.$slot.'_upload'] = $this->postJson('/media/uploads', ['image' => $this->image()])->assertOk()->json('token');
        }
        $this->save($partner, $tokens)->assertSessionHasNoErrors();
        $old = $partner->fresh()->gallery_paths;
        $this->assertCount(3, $old);
        foreach ($old as $key) {
            $this->assertStringStartsWith('partners/'.$partner->id.'/gallery/', $key);
            Storage::disk('public')->assertExists($key);
            $info = getimagesizefromstring(Storage::disk('public')->get($key));
            $this->assertSame([1080, 1350], array_slice($info, 0, 2));
            $this->assertSame('image/webp', $info['mime']);
            $this->assertSame('private', Storage::disk('public')->getVisibility($key));
        }
        $this->get('/admin/partners/'.$partner->id.'/edit')->assertInertia(fn (Assert $page) => $page->has('item.galleryUrls', 3));
        $this->save($partner, ['gallery_1_upload' => $tokens['gallery_1_upload'], 'gallery_2_upload' => 'invalid', 'name' => 'Must roll back'])->assertSessionHasErrors('gallery_2');
        $this->assertSame($old, $partner->fresh()->gallery_paths);
        $this->assertSame($partner->name, $partner->fresh()->name);
        Storage::disk('public')->assertExists($old);
        $this->save($partner, ['gallery_2' => $this->image()])->assertSessionHasNoErrors();
        $new = $partner->fresh()->gallery_paths;
        $this->assertSame($old[0], $new[0]);
        $this->assertNotSame($old[1], $new[1]);
        $this->assertSame($old[2], $new[2]);
        Storage::disk('public')->assertExists($old[1]);
        $this->save($partner, ['gallery_2_remove' => true])->assertSessionHasNoErrors();
        $this->assertSame([$old[0], $old[2]], $partner->fresh()->gallery_paths);
        $this->assertFalse(app(MediaReferences::class)->contains($new[1]));
        Storage::disk('public')->assertExists($new[1]);
        $this->assertSame('existing-logo.webp', $partner->fresh()->logo_path);
        $this->assertSame('existing-cover.webp', $partner->fresh()->cover_path);
        $this->save($partner, ['gallery_1_remove' => true, 'gallery_2_remove' => true])->assertSessionHasNoErrors();
        $this->assertNull($partner->fresh()->gallery_paths);
    }

    public function test_admin_rejects_raw_paths_fourth_slot_and_non_admin_writes(): void
    {
        $partner = Partner::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->save($partner, ['gallery_1' => $this->image()])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->save($partner, ['gallery_paths' => ['https://external.test/a.webp']])->assertSessionHasErrors('gallery_paths');
        $this->save($partner, ['gallery_4' => $this->image()])->assertSessionHasErrors('gallery_4');
        $this->save($partner, ['gallery_4_upload' => 'token'])->assertSessionHasErrors('gallery_4_upload');
        $this->assertNull($partner->fresh()->gallery_paths);
    }

    public function test_gc_preserves_all_gallery_references_and_collects_removed_assets_only_after_grace(): void
    {
        $partner = Partner::factory()->create(['status' => 'archived']);
        $paths = $this->paths($partner, 3);
        $partner->update(['gallery_paths' => $paths]);
        foreach ($paths as $key) {
            touch(Storage::disk('public')->path($key), now()->subHours(100)->timestamp);
            $this->assertTrue(app(MediaReferences::class)->contains($key));
            $this->assertArrayHasKey($key, app(MediaReferences::class)->all());
        }
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertExists($paths);
        $partner->update(['gallery_paths' => [$paths[0], $paths[2]]]);
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertExists($paths[1]);
        $this->travel(47)->hours();
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertExists($paths[1]);
        $this->travel(2)->hours();
        $this->artisan('media:gc')->assertSuccessful();
        Storage::disk('public')->assertMissing($paths[1]);
        Storage::disk('public')->assertExists([$paths[0], $paths[2]]);
    }
}
