<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_preference_defaults_to_system(): void
    {
        $this->assertSame('system', User::factory()->create()->fresh()->theme_preference);
    }

    public function test_authenticated_user_can_save_each_allowed_theme(): void
    {
        $user = User::factory()->create();
        foreach (['light', 'dark', 'system'] as $theme) {
            $this->actingAs($user)->patch(route('appearance.update'), ['theme_preference' => $theme])->assertNoContent();
            $this->assertSame($theme, $user->refresh()->theme_preference);
        }
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->patch(route('appearance.update'), ['theme_preference' => 'neon'])->assertSessionHasErrors('theme_preference');
    }

    public function test_theme_endpoint_only_updates_authenticated_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create(['theme_preference' => 'light']);
        $this->actingAs($first)->patch(route('appearance.update'), ['theme_preference' => 'dark', 'user_id' => $second->id])->assertNoContent();
        $this->assertSame('dark', $first->refresh()->theme_preference);
        $this->assertSame('light', $second->refresh()->theme_preference);
    }

    public function test_profile_still_renders_after_a_theme_change(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('appearance.update'), ['theme_preference' => 'dark'])->assertNoContent();
        $this->actingAs($user)->get('/perfil')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('member-profile'));
    }
}
