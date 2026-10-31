<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\LandingPresentation;
use App\Models\Partner;
use App\Models\User;
use App\Services\MediaUploadService;
use App\Services\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingSeoTest extends TestCase
{
    use RefreshDatabase;

    private const IMAGE = 'landings/12/image/11111111-1111-4111-8111-111111111111.webp';
    private const HERO = 'landings/12/cover/22222222-2222-4222-8222-222222222222.webp';

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode' => false, 'media.imgproxy_url' => 'https://images.example.test', 'media.imgproxy_key' => str_repeat('ab', 32), 'media.imgproxy_salt' => str_repeat('cd', 16)]);
    }

    private function offer(array $attributes = []): array
    {
        $benefit = Benefit::factory()->published()->for(Partner::factory()->published())->create(['title' => 'Café y postre en Dossis', 'short_description' => 'Disfruta café de origen y postre con tu membresía.', 'image_path' => null]);
        $landing = $benefit->landingPresentations()->create($attributes + ['name' => 'Interno', 'slug' => 'cafe-y-postre', 'status' => 'PUBLISHED', 'default_scope' => 'ALL', 'media_mode' => 'IMAGE']);
        return [$benefit, $landing];
    }

    private function htmlHead(string $html): string
    {
        return explode('</head>', explode('<head>', $html)[1])[0];
    }

    public function test_initial_html_has_complete_metadata_and_commercial_fallback_without_javascript(): void
    {
        [$benefit, $landing] = $this->offer(['hero_path' => self::HERO]);
        $head = $this->htmlHead($this->get('/l/'.$landing->slug.'?utm_source=network')->assertOk()->getContent());
        $this->assertStringContainsString('<title data-inertia="">Café y postre en Dossis | JAKAWI</title>', $head);
        foreach (['description', 'robots', 'og:type', 'og:title', 'og:description', 'og:image', 'og:image:width', 'og:image:height', 'og:image:type', 'og:image:alt', 'og:url', 'og:site_name', 'twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'] as $tag) {
            $this->assertMatchesRegularExpression('/(?:name|property)="'.preg_quote($tag, '/').'"/', $head);
        }
        $this->assertStringContainsString('content="index,follow"', $head);
        $this->assertStringContainsString('Disfruta café de origen y postre', $head);
        $this->assertStringContainsString('href="'.route('landing-presentations.show', $landing).'"', $head);
        $this->assertStringContainsString('/rs:fill:1200:630:1/q:85/f:jpg/', $head);
        $this->assertStringContainsString(self::HERO, $head);
        $this->assertStringNotContainsString('utm_source', $head);
        $other = $benefit->landingPresentations()->create(['name' => 'Otra campaña', 'slug' => 'otro-cafe', 'status' => 'PUBLISHED', 'headline' => 'Tu pausa con café boliviano']);
        $this->assertStringContainsString('Tu pausa con café boliviano | JAKAWI', $this->htmlHead($this->get('/l/'.$other->slug)->assertOk()->getContent()));
    }

    public function test_video_and_social_image_coexist_with_custom_copy_and_catalog_fallback(): void
    {
        $video = ['source' => ['type' => 'youtube', 'url' => 'https://youtu.be/hI-Ecy2D8pg']];
        [, $landing] = $this->offer(['media_mode' => 'VSL', 'vsl' => $video, 'hero_path' => self::HERO, 'social_image_path' => self::IMAGE, 'seo_title' => 'Tu pausa en Dossis', 'seo_description' => 'Conoce esta propuesta de café y postre.']);
        $response = $this->get('/l/'.$landing->slug)->assertOk();
        $head = $this->htmlHead($response->getContent());
        $this->assertStringContainsString(self::IMAGE, $head);
        $this->assertStringNotContainsString(self::HERO, $head);
        $this->assertStringNotContainsString('ytimg', $head);
        $this->assertStringContainsString('Tu pausa en Dossis | JAKAWI', $head);
        $this->assertSame($video, $landing->fresh()->vsl);
        $this->getJson('/api/catalog/v1/offers')->assertJsonPath('data.0.image_url', app(MediaUrl::class)->url(self::IMAGE, 'social'));
    }

    public function test_social_upload_replace_remove_preserve_video_and_main_image(): void
    {
        Storage::fake(config('media.disk'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        [$benefit, $landing] = $this->offer(['media_mode' => 'VSL', 'vsl' => ['source' => ['type' => 'mp4', 'url' => 'https://example.test/original.mp4']], 'hero_path' => self::HERO]);
        $video = $landing->vsl;
        $url = '/admin/beneficios/'.$benefit->slug.'/landings/'.$landing->slug;
        $payload = ['name' => $landing->name, 'slug' => $landing->slug, 'seo_title' => 'Mi título social', 'seo_description' => 'Mi descripción comercial'];
        foreach (range(1, 2) as $i) {
            $token = app(MediaUploadService::class)->temporary(UploadedFile::fake()->image('social.jpg', 1400, 900), auth()->id());
            $this->put($url, $payload + ['social_image_upload' => $token])->assertSessionHasNoErrors()->assertRedirect();
            $key = $landing->fresh()->social_image_path;
            Storage::disk(config('media.disk'))->assertExists($key);
            if (isset($previous)) $this->assertNotSame($previous, $key);
            $previous = $key;
            $this->assertTrue(app(\App\Services\MediaReferences::class)->contains($key));
            $this->assertArrayHasKey($key, app(\App\Services\MediaReferences::class)->all());
            $this->assertSame($video, $landing->fresh()->vsl);
            $this->assertSame('VSL', $landing->fresh()->media_mode->value);
            $this->assertSame(self::HERO, $landing->fresh()->hero_path);
        }
        $this->put($url, $payload + ['social_image_remove' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($landing->fresh()->social_image_path);
        $this->assertSame($video, $landing->fresh()->vsl);
        $this->assertSame('VSL', $landing->fresh()->media_mode->value);
        $this->get('/admin/landings/'.$landing->slug.'/edit')->assertOk();
        $this->put($url, $payload + ['hero_remove' => '1', 'media_mode' => 'VSL'])->assertSessionHasNoErrors();
        $this->assertSame('VSL', $landing->fresh()->media_mode->value);
        $this->assertSame($video, $landing->fresh()->vsl);
    }

    public function test_no_image_uses_corporate_fallback_and_private_previews_do_not_emit_social_metadata(): void
    {
        [$benefit, $landing] = $this->offer(['media_mode' => 'NONE']);
        $this->assertStringContainsString(config('media.social_fallback'), $this->htmlHead($this->get('/l/'.$landing->slug)->assertOk()->getContent()));
        $landing->update(['status' => 'DRAFT', 'default_scope' => 'NONE']);
        $this->get('/l/'.$landing->slug)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $head = $this->htmlHead($this->get('/admin/beneficios/'.$benefit->slug.'/landings/'.$landing->slug.'/preview')->assertOk()->getContent());
        $this->assertStringContainsString('content="noindex,nofollow"', $head);
        $this->assertStringNotContainsString('property="og:', $head);
        $this->assertStringNotContainsString('rel="canonical"', $head);
        $landing->update(['status' => 'ARCHIVED']);
        $this->get('/l/'.$landing->slug)->assertNotFound();
    }

    public function test_social_signature_and_dimensions_are_fixed_even_for_small_sources(): void
    {
        $url = app(MediaUrl::class)->url(self::IMAGE, 'social');
        $path = '/rs:fill:1200:630:1/q:85/f:jpg/plain/s3://'.config('media.bucket').'/'.self::IMAGE;
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', hex2bin(config('media.imgproxy_salt')).$path, hex2bin(config('media.imgproxy_key')), true)), '+/', '-_'), '=');
        $this->assertSame('https://images.example.test/'.$signature.$path, $url);
        $this->assertTrue(app(\App\Services\MediaReferences::class)->contains(config('media.social_fallback')));
    }
}
