<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_owner_can_upload_supported_avatar_formats_without_losing_profile_fields(): void
    {
        config()->set('media.disk', 'public');
        Storage::fake('public');
        $user = User::factory()->create();
        $user->profile()->create(['city' => 'Cochabamba', 'interests' => ['cafe']]);

        foreach (['avatar.jpg', 'avatar.png', 'avatar.webp'] as $name) {
            $this->actingAs($user)->put('/mi-jakawi/perfil', [
                'avatar' => $this->image($name), 'city' => 'Cochabamba', 'interests' => ['cafe'],
            ])->assertRedirect('/mi-jakawi/perfil');

            $path = $user->fresh()->profile->avatar_path;
            $this->assertMatchesRegularExpression('#^avatars/'.$user->id.'/[0-9a-f-]+\\.(jpg|png|webp)$#', $path);
            $this->assertStringNotContainsString('http', $path);
            Storage::disk('public')->assertExists($path);
            $this->assertSame(['cafe'], $user->fresh()->profile->interests);
        }
    }

    public function test_svg_avatar_is_rejected_and_old_avatar_survives_until_replaced(): void
    {
        config()->set('media.disk', 'public');
        Storage::fake('public');
        $user = User::factory()->create();
        $old = 'avatars/'.$user->id.'/550e8400-e29b-41d4-a716-446655440000.jpg';
        Storage::disk('public')->put($old, 'old');
        $user->profile()->create(['avatar_path' => $old, 'interests' => ['food']]);

        $this->actingAs($user)->from('/mi-jakawi/perfil')->put('/mi-jakawi/perfil', [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ])->assertRedirect('/mi-jakawi/perfil')->assertSessionHasErrors('avatar');
        $this->assertSame($old, $user->fresh()->profile->avatar_path);
        Storage::disk('public')->assertExists($old);

        $this->actingAs($user)->put('/mi-jakawi/perfil', ['avatar' => $this->image('new.png')])->assertRedirect();
        $this->assertNotSame($old, $user->fresh()->profile->avatar_path);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($user->fresh()->profile->avatar_path);
    }

    private function image(string $name): UploadedFile
    {
        $images = [
            'jpg' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AR//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AR//2Q==',
            'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbhwAAAABJRU5ErkJggg==',
            'webp' => 'UklGRiIAAABXRUJQVlA4IC4AAAAwAQCdASoBAAEAAUAmJaQAA3AA/vuUAAA=',
        ];

        return UploadedFile::fake()->createWithContent($name, base64_decode($images[pathinfo($name, PATHINFO_EXTENSION)]));
    }
}
