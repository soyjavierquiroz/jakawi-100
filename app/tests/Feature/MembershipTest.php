<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_enter_mi_jakawi(): void
    {
        $this->get('/mi-jakawi')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_enter_mi_jakawi(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/mi-jakawi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('mi-jakawi'));
    }

    public function test_user_without_membership_appears_inactive(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/mi-jakawi')
            ->assertInertia(fn (Assert $page) => $page->where('membership', null));
    }

    public function test_active_membership_is_recognized(): void
    {
        $user = User::factory()->create();
        Membership::query()->create([
            'user_id' => $user->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->assertTrue($user->hasActiveMembership());

        $this->actingAs($user)
            ->get('/mi-jakawi')
            ->assertInertia(fn (Assert $page) => $page->has('membership'));
    }

    public function test_expired_membership_is_not_recognized(): void
    {
        $user = User::factory()->create();
        Membership::query()->create([
            'user_id' => $user->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now()->subYear(),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($user->hasActiveMembership());
    }

    public function test_cancelled_membership_is_not_recognized(): void
    {
        $user = User::factory()->create();
        Membership::query()->create([
            'user_id' => $user->id,
            'status' => Membership::STATUS_CANCELLED,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->assertFalse($user->hasActiveMembership());
    }

    public function test_future_membership_is_not_recognized(): void
    {
        $user = User::factory()->create();
        Membership::query()->create([
            'user_id' => $user->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $this->assertFalse($user->hasActiveMembership());
    }

    public function test_guest_cannot_access_admin_memberships(): void
    {
        $this->get('/admin/memberships')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin_memberships(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/memberships')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_activate_or_cancel_memberships(): void
    {
        $user = User::factory()->create();
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->post('/admin/memberships', ['user_id' => $membership->user_id])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch("/admin/memberships/{$membership->id}/cancel")
            ->assertForbidden();
    }

    public function test_admin_lists_memberships(): void
    {
        User::factory()->create(['name' => 'Member User']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/memberships')
            ->assertOk()
            ->assertSee('Member User');
    }

    public function test_admin_activates_membership(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)->post('/admin/memberships', [
            'user_id' => $user->id,
        ])->assertRedirect();

        $membership = Membership::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(Membership::STATUS_ACTIVE, $membership->status);
        $this->assertSame(
            number_format((float) config('jakawi.membership.price_bob'), 2, '.', ''),
            $membership->amount_paid,
        );
        $this->assertSame($admin->id, $membership->activated_by);
        $this->assertSame(
            config('jakawi.membership.duration_days'),
            (int) $membership->starts_at->diffInDays($membership->ends_at),
        );
    }

    public function test_double_activation_does_not_create_second_active_membership(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $payload = ['user_id' => $user->id];

        $this->actingAs($admin)->post('/admin/memberships', $payload);
        $this->actingAs($admin)->post('/admin/memberships', $payload);

        $this->assertSame(1, Membership::query()->where('user_id', $user->id)->active()->count());
    }

    public function test_admin_cancels_membership(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->patch("/admin/memberships/{$membership->id}/cancel")
            ->assertRedirect();

        $this->assertDatabaseHas('memberships', [
            'id' => $membership->id,
            'status' => Membership::STATUS_CANCELLED,
        ]);
        $this->assertFalse($membership->user->hasActiveMembership());
    }

    public function test_cancelled_membership_allows_a_new_activation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        Membership::query()->create([
            'user_id' => $user->id,
            'status' => Membership::STATUS_CANCELLED,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/memberships', ['user_id' => $user->id])
            ->assertRedirect();

        $this->assertSame(1, Membership::query()->where('user_id', $user->id)->active()->count());
        $this->assertSame(2, Membership::query()->where('user_id', $user->id)->count());
    }

    public function test_user_only_sees_their_own_active_membership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $membership = Membership::query()->create([
            'user_id' => $otherUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get('/mi-jakawi?user_id='.$otherUser->id)
            ->assertInertia(fn (Assert $page) => $page->where('membership', null));

        $this->assertDatabaseHas('memberships', ['id' => $membership->id]);
    }

    public function test_benefit_detail_available_for_active_member(): void
    {
        $user = User::factory()->create();
        $benefit = Benefit::factory()->create();
        Membership::query()->create([
            'user_id' => $user->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get("/beneficios/{$benefit->slug}")
            ->assertInertia(fn (Assert $page) => $page->where('hasActiveMembership', true));
    }

    public function test_benefit_detail_unavailable_for_user_without_membership(): void
    {
        $benefit = Benefit::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/beneficios/{$benefit->slug}")
            ->assertInertia(fn (Assert $page) => $page->where('hasActiveMembership', false));
    }

    public function test_benefit_detail_unavailable_for_expired_or_cancelled_memberships(): void
    {
        $benefit = Benefit::factory()->create();

        foreach ([
            [Membership::STATUS_ACTIVE, now()->subYear(), now()->subDay()],
            [Membership::STATUS_CANCELLED, now()->subDay(), now()->addDay()],
        ] as [$status, $startsAt, $endsAt]) {
            $user = User::factory()->create();
            Membership::query()->create([
                'user_id' => $user->id,
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->actingAs($user)
                ->get("/beneficios/{$benefit->slug}")
                ->assertInertia(fn (Assert $page) => $page->where('hasActiveMembership', false));
        }
    }
}
