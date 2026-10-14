<?php

namespace Tests\Feature;

use App\Models\{Partner, User};
use App\Services\MediaUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerIdentityRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function square(): UploadedFile
    {
        $image = imagecreatetruecolor(1024, 1024);
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        return UploadedFile::fake()->createWithContent('cropped-logo.png', $bytes);
    }

    public function test_existing_social_fields_are_projected_without_new_domain_data(): void
    {
        $partner = Partner::factory()->published()->create(['slug' => 'social-brand', 'instagram' => 'https://www.instagram.com/stored/', 'facebook' => null, 'tiktok' => null, 'website' => null, 'whatsapp' => null]);
        $this->get('/social-brand')->assertInertia(fn (Assert $page) => $page
            ->where('partner.instagram', $partner->instagram)->where('partner.facebook', null)
            ->where('partner.tiktok', null)->where('partner.website', null)->where('partner.whatsapp', null));
        $partner->update(['tiktok' => 'https://www.tiktok.com/@stored', 'facebook' => 'https://www.facebook.com/stored', 'website' => 'https://stored.example.test', 'whatsapp' => '+59171234567']);
        $this->get('/social-brand')->assertInertia(fn (Assert $page) => $page
            ->where('partner.tiktok', $partner->tiktok)->where('partner.facebook', $partner->facebook)
            ->where('partner.website', $partner->website)->where('partner.whatsapp', $partner->whatsapp));
    }

    public function test_square_logo_uses_immutable_shared_media_pipeline_and_keeps_cover(): void
    {
        Storage::fake('public'); config(['media.disk' => 'public']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->create(['cover_path' => 'unchanged-cover.webp']);
        $old = app(MediaUploadService::class)->store($this->square(), 'partners', $partner->id, 'logo');
        $partner->update(['logo_path' => $old]);
        $token = $this->postJson('/media/uploads', ['image' => $this->square()])->assertOk()->json('token');
        $this->put('/admin/partners/'.$partner->id, $partner->only(['name', 'slug', 'entity_type', 'partner_type', 'status']) + ['logo_upload' => $token])->assertSessionHasNoErrors();
        $new = $partner->fresh()->logo_path;
        $this->assertNotSame($old, $new); Storage::disk('public')->assertExists($old);
        $info = getimagesizefromstring(Storage::disk('public')->get($new));
        $this->assertSame([1024, 1024], array_slice($info, 0, 2)); $this->assertSame('image/webp', $info['mime']);
        $this->assertSame('unchanged-cover.webp', $partner->fresh()->cover_path);
        $this->put('/admin/partners/'.$partner->id, $partner->only(['name', 'slug', 'entity_type', 'partner_type', 'status']) + ['logo_upload' => 'invalid-token', 'name' => 'Must roll back'])->assertSessionHasErrors('logo');
        $this->assertSame($new, $partner->fresh()->logo_path); Storage::disk('public')->assertExists($new);
    }

    public function test_only_admin_can_reopen_existing_master_without_overwriting_it(): void
    {
        Storage::fake('public'); config(['media.disk' => 'public']);
        $partner = Partner::factory()->create();
        $key = app(MediaUploadService::class)->store($this->square(), 'partners', $partner->id, 'logo');
        $partner->update(['logo_path' => $key]);
        $url = '/admin/partners/'.$partner->id.'/logo-source';
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertSame($key, $partner->fresh()->logo_path); Storage::disk('public')->assertExists($key);
        $this->get('/admin/partners/'.$partner->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('item.logoCropUrl', $url));
        $partner->update(['logo_path' => null]); $this->get($url)->assertNotFound();
    }
}
