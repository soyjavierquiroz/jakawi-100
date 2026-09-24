<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_open_profile_without_an_active_membership(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/mi-jakawi/perfil')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('member-profile')->where('profile.completion_percentage', 0));
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
    }

    public function test_partner_only_user_is_kept_out_of_member_profile(): void
    {
        $user = User::factory()->create();
        $user->partners()->attach(Partner::factory()->create(), ['role' => 'manager']);

        $this->actingAs($user)->get('/mi-jakawi/perfil')->assertRedirect('/partner');
        $this->actingAs($user)->put('/mi-jakawi/perfil', [])->assertForbidden();
    }

    public function test_preferences_are_whitelisted_deduplicated_and_completed_once(): void
    {
        $user = User::factory()->create();
        $payload = ['city' => 'Cochabamba', 'interests' => ['food', 'food', 'cafe'], 'social_contexts' => ['amigos'], 'preferred_days' => ['fin_de_semana'], 'preferred_times' => ['noche']];

        $this->actingAs($user)->put('/mi-jakawi/perfil', $payload)->assertRedirect('/mi-jakawi/perfil');
        $profile = UserProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['food', 'cafe'], $profile->interests);
        $this->assertSame(100, $profile->completionPercentage());
        $completedAt = $profile->profile_completed_at;
        $this->assertNotNull($completedAt);

        $this->actingAs($user)->put('/mi-jakawi/perfil', ['interests' => [], 'social_contexts' => ['amigos'], 'preferred_days' => ['fin_de_semana'], 'preferred_times' => ['noche']])->assertRedirect();
        $profile->refresh();
        $this->assertSame(60, $profile->completionPercentage());
        $this->assertTrue($completedAt->equalTo($profile->profile_completed_at));
    }

    public function test_each_preference_group_can_be_updated_and_invalid_values_are_rejected(): void
    {
        $user = User::factory()->create();
        foreach ([['interests' => ['wellness']], ['social_contexts' => ['familia']], ['preferred_days' => ['entre_semana']], ['preferred_times' => ['tarde']]] as $payload) {
            $this->actingAs($user)->put('/mi-jakawi/perfil', $payload)->assertRedirect();
        }
        $this->assertSame(['wellness'], $user->profile->interests);
        $this->assertSame(['familia'], $user->profile->social_contexts);
        $this->assertSame(['entre_semana'], $user->profile->preferred_days);
        $this->assertSame(['tarde'], $user->profile->preferred_times);

        $this->actingAs($user)->from('/mi-jakawi/perfil')->put('/mi-jakawi/perfil', ['interests' => ['unknown']])
            ->assertRedirect('/mi-jakawi/perfil')->assertSessionHasErrors('interests.0');
    }

    public function test_user_cannot_update_another_users_profile_and_partner_pages_do_not_receive_preferences(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userB->profile()->create(['interests' => ['food']]);
        $partner = Partner::factory()->create();
        $userA->partners()->attach($partner, ['role' => 'manager']);
        app(MembershipService::class)->activate($userA, User::factory()->create());

        $this->actingAs($userA)->put('/mi-jakawi/perfil', ['interests' => ['cafe']])->assertRedirect();
        $this->assertSame(['food'], $userB->fresh()->profile->interests);
        $this->actingAs($userA)->get('/partner/'.$partner->slug)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('profile')->missing('preferences')->missing('interests'));
    }
}
