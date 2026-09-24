<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_login_renders(): void
    {
        $this->get('/partner/login')->assertOk()->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->where('partnerPortal', true));
    }

    public function test_partner_login_uses_fortify_credentials_and_redirects_to_portal(): void
    {
        $partner = Partner::factory()->published()->create();
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => 'manager']);

        $this->post('/partner/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/partner/'.$partner->slug);

        $this->assertAuthenticatedAs($user);
    }

    public function test_partner_login_respects_an_intended_partner_url(): void
    {
        $partner = Partner::factory()->published()->create();
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => 'manager']);

        $this->withSession(['url.intended' => '/partner/'.$partner->slug.'/reservas'])
            ->post('/partner/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/partner/'.$partner->slug.'/reservas');
    }

    public function test_member_cannot_use_partner_login(): void
    {
        $user = User::factory()->create();

        $this->post('/partner/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/partner/login');

        $this->assertGuest();
    }

    public function test_root_login_redirects_by_role(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->post('/logout');

        $partner = Partner::factory()->published()->create();
        $partnerUser = User::factory()->create();
        $partnerUser->partners()->attach($partner, ['role' => 'manager']);
        $this->post('/login', ['email' => $partnerUser->email, 'password' => 'password'])->assertRedirect('/partner');
        $this->post('/logout');

        $member = User::factory()->create();
        $this->post('/login', ['email' => $member->email, 'password' => 'password'])->assertRedirect('/mi-jakawi');
    }

    public function test_partner_only_member_page_and_stale_intended_urls_use_the_right_context(): void
    {
        $partner = Partner::factory()->published()->create();
        $partnerUser = User::factory()->create();
        $partnerUser->partners()->attach($partner, ['role' => 'manager']);
        $this->actingAs($partnerUser)->get('/mi-jakawi')->assertRedirect('/partner');
        $this->post('/logout');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->withSession(['url.intended' => '/partner/'.$partner->slug.'/reservas'])
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->actingAs($admin)->get('/partner')->assertForbidden();
        $this->post('/logout');

        $member = User::factory()->create();
        app(MembershipService::class)->activate($member, User::factory()->create());
        $this->withSession(['url.intended' => '/mi-jakawi'])
            ->post('/login', ['email' => $member->email, 'password' => 'password'])->assertRedirect('/mi-jakawi');
        $this->actingAs($member)->get('/mi-jakawi')->assertOk();
    }

    public function test_automated_tests_are_configured_for_the_isolated_database(): void
    {
        $this->assertSame('jakawi_test', config('database.connections.pgsql.database'));
    }
}
