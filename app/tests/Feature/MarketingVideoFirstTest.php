<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Experience;
use App\Models\Partner;
use App\Models\User;
use App\Services\LandingVideoConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketingVideoFirstTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_switch_defaults_off_persists_and_does_not_change_public_policy(): void
    {
        config(['jakawi.prelaunch_mode' => true]);
        Partner::factory()->published()->create(['slug' => 'demo-policy']);
        $this->assertFalse(AppSetting::showDemoBanner());
        $this->get('/admin/settings')->assertRedirect('/login');
        $this->put('/admin/settings', ['show_demo_banner' => true])->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->get('/admin/settings')->assertForbidden();
        $this->put('/admin/settings', ['show_demo_banner' => true])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/settings')->assertInertia(fn (Assert $p) => $p->component('admin/site-settings')->where('showDemoBanner', false));
        $this->putJson('/admin/settings', ['show_demo_banner' => 'invalid'])->assertUnprocessable();
        foreach ([true, false] as $enabled) {
            $this->put('/admin/settings', ['show_demo_banner' => $enabled])->assertRedirect();
            $this->assertSame($enabled, AppSetting::showDemoBanner());
            foreach (['/explorar', '/demo-policy'] as $url) {
                $this->get($url)->assertSee('name="robots" content="noindex,nofollow"', false)
                    ->assertInertia(fn (Assert $p) => $p->where('prelaunchMode', true)->where('prelaunchPublic', true)->where('showDemoBanner', $enabled));
            }
        }
        $this->assertDatabaseCount('app_settings', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_effective_video_is_shared_by_public_preview_and_admin_without_media_guessing(): void
    {
        $experience = Experience::factory()->published()->create(['catalog_visibility' => 'upcoming', 'operations_confirmed' => false]);
        $experience->syncPartnersWithRoles([['partner_id' => Partner::factory()->published()->create()->id, 'role' => 'organizer']]);
        $landing = $experience->landingPresentations()->create(['name' => 'Video real configurado', 'slug' => 'video-source', 'status' => 'PUBLISHED', 'media_mode' => 'VSL']);
        $before = $experience->getAttributes();
        foreach ([['youtube', 'https://www.youtube.com/shorts/P42gDOTL6Ys'], ['mp4', 'https://media.example.test/a.mp4'], ['bunny', 'https://media.example.test/a.m3u8']] as [$type, $url]) {
            $landing->update(['vsl' => ['source' => compact('type', 'url'), 'framing' => 'native-vertical']]);
            $this->get('/l/video-source')->assertInertia(fn (Assert $p) => $p->where('vsl.source.url', $url)->where('copy.action.kind', 'unavailable')->where('copy.action.label', 'EN PREPARACIÓN'));
            $this->actingAs(User::factory()->create(['is_admin' => true]));
            $this->get('/admin/landings?video=yes')->assertInertia(fn (Assert $p) => $p->has('landings', 1)->where('landings.0.has_video', true)->where('landings.0.video.source.url', $url));
            $landing->update(['status' => 'DRAFT']);
            $this->get('/l/video-source')->assertNotFound();
            $this->get('/admin/experiencias/'.$experience->slug.'/landings/video-source/preview')->assertInertia(fn (Assert $p) => $p->where('preview', true)->where('noindex', true)->where('presentation.status', 'DRAFT')->where('vsl.source.url', $url));
            $landing->update(['status' => 'PUBLISHED']);
        }
        foreach (['IMAGE', 'NONE'] as $mode) {
            $landing->update(['media_mode' => $mode]);
            $this->get('/l/video-source')->assertInertia(fn (Assert $p) => $p->where('vsl', null));
            $this->get('/admin/landings?video=yes')->assertInertia(fn (Assert $p) => $p->has('landings', 0));
        }
        $landing->update(['media_mode' => 'VSL', 'vsl' => ['source' => ['type' => 'youtube', 'url' => 'https://example.test/image.jpg']]]);
        $this->assertNull(app(LandingVideoConfiguration::class)->forPresentation($landing));
        $this->get('/l/video-source')->assertInertia(fn (Assert $p) => $p->where('vsl', null));
        $this->assertSame($before, $experience->fresh()->getAttributes());
    }
}
