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
    public function test_guest_cannot_save_an_account_preference(): void
    {
        $this->patchJson(route('appearance.update'), ['theme_preference' => 'dark'])->assertUnauthorized();
    }

    public function test_account_preference_wins_over_browser_cookie_in_bootstrap(): void
    {
        $user = User::factory()->create(['theme_preference' => 'dark']);
        $this->actingAs($user)->withUnencryptedCookie('appearance', 'light')->get('/perfil')
            ->assertOk()->assertSee('data-appearance="dark"', false)
            ->assertSee('data-appearance-user="'.$user->id.'"', false);
    }

    public function test_invalid_guest_cookie_falls_back_to_system(): void
    {
        $this->withUnencryptedCookie('appearance', 'invalid')->get('/login')
            ->assertOk()->assertSee('data-appearance="system"', false);
    }

    public function test_partner_can_save_preference_without_changing_role(): void
    {
        $user = User::factory()->create();
        $partner = \App\Models\Partner::factory()->create();
        $user->partners()->attach($partner, ['role' => 'staff']);
        $this->actingAs($user)->patchJson(route('appearance.update'), ['theme_preference' => 'dark'])->assertNoContent();
        $this->assertSame('staff', $user->partners()->first()->pivot->role);
        $this->assertSame('dark', $user->fresh()->theme_preference);
    }
}
