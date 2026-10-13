<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Partner;
use App\Models\User;
use App\Services\DiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrelaunchModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode' => true, 'jakawi.analytics.enabled' => false]);
    }

    public function test_root_is_static_coming_soon_and_indexable_without_inventory(): void
    {
        $this->mock(DiscoveryService::class)->shouldNotReceive('discover', 'explore');
        $this->get('/')->assertOk()
            ->assertSee('<meta data-inertia="robots" name="robots" content="index,follow">', false)
            ->assertSee('href="https://jakawi.com/"', false)
            ->assertSee('Próximamente en Cochabamba')
            ->assertInertia(fn (Assert $page) => $page->component('coming-soon')
                ->where('prelaunchMode', true)->where('prelaunchPublic', false)
                ->missing('discovery')->missing('challenges'));
        $this->assertDatabaseCount('partners', 0);
        $this->assertDatabaseCount('benefits', 0);
        $this->assertDatabaseCount('landing_presentations', 0);
    }

    public function test_landing_native_fallback_and_explore_remain_available_with_public_policy(): void
    {
        $challenge = $this->challenge();
        $challenge->landingPresentations()->create([
            'name' => 'Prelaunch QA', 'slug' => 'prelaunch-qa', 'status' => 'PUBLISHED', 'default_scope' => 'NONE',
        ]);
        Partner::factory()->published()->create(['slug' => 'prelaunch-notice-qa']);
        foreach (['/prelaunch-notice-qa', '/l/prelaunch-qa', '/retos/prelaunch-qa', '/explorar'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('<meta data-inertia="robots" name="robots" content="noindex,nofollow">', false)
                ->assertInertia(fn (Assert $page) => $page->where('prelaunchMode', true)->where('prelaunchPublic', true));
        }
        $this->assertSame('NONE', $challenge->landingPresentations()->sole()->default_scope);
    }

    public function test_admin_and_saved_preview_and_auth_are_excluded(): void
    {
        $challenge = $this->challenge();
        $landing = $challenge->landingPresentations()->create([
            'name' => 'Preview QA', 'slug' => 'preview-qa', 'status' => 'DRAFT', 'default_scope' => 'NONE',
        ]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['/admin', '/admin/retos/prelaunch-qa/landings/preview-qa/preview'] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('<meta data-inertia="robots" name="robots" content="noindex,nofollow">', false)
                ->assertInertia(fn (Assert $page) => $page->where('prelaunchPublic', false));
        }
        $this->assertSame('DRAFT', $landing->fresh()->status);
        $this->app['auth']->forgetGuards();
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->where('prelaunchPublic', false));
    }

    public function test_disabling_flag_restores_home_and_previous_public_seo(): void
    {
        config(['jakawi.prelaunch_mode' => false]);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('welcome')
            ->where('prelaunchMode', false)->where('prelaunchPublic', false)->has('discovery'));
        $this->challenge();
        Partner::factory()->published()->create(['slug' => 'prelaunch-notice-qa']);
        $this->get('/prelaunch-notice-qa')->assertOk()->assertInertia(fn (Assert $page) => $page->where('prelaunchPublic', false));
        $this->get('/retos/prelaunch-qa')->assertOk()
            ->assertDontSee('<meta data-inertia="robots" name="robots" content="noindex,nofollow">', false)
            ->assertInertia(fn (Assert $page) => $page->where('prelaunchPublic', false));
    }

    private function challenge(): Challenge
    {
        return Challenge::create([
            'title' => 'Selección de prueba', 'slug' => 'prelaunch-qa', 'status' => 'open', 'review_status' => 'APPROVED',
            'description' => 'Prueba aislada', 'instructions' => 'Comparte tu ciudad',
            'allowed_platforms' => ['instagram'], 'required_hashtags' => [], 'required_mentions' => [],
            'evidence_type' => 'SOCIAL_POST', 'qualification_type' => 'VALID_EVIDENCE', 'selection_type' => 'ALL_QUALIFIED',
            'evaluation_mode' => 'CONTINUOUS', 'review_mode' => 'AUTOMATIC',
            'participation_eligibility' => 'ALL_USERS', 'reward_eligibility' => 'ALL_USERS',
            'reward_type' => 'JP', 'reward_jp_amount' => 25,
        ]);
    }
}
