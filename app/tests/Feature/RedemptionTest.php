<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Membership;
use App\Models\Merchant;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_and_unverified_users_do_not_create_redemptions(): void
    {
        $benefit = $this->benefit();

        $this->post("/beneficios/{$benefit->slug}/canjear")->assertRedirect('/login');

        $this->actingAs(User::factory()->unverified()->create())
            ->post("/beneficios/{$benefit->slug}/canjear")
            ->assertForbidden();
    }

    public function test_membership_and_benefit_availability_are_required_to_create(): void
    {
        $benefit = $this->benefit();

        $this->actingAs(User::factory()->create())
            ->post("/beneficios/{$benefit->slug}/canjear")
            ->assertForbidden();

        $user = $this->member(Membership::STATUS_CANCELLED);

        $this->actingAs($user)
            ->post("/beneficios/{$benefit->slug}/canjear")
            ->assertForbidden();

        $expiredUser = User::factory()->create();
        Membership::query()->create([
            'user_id' => $expiredUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($expiredUser)
            ->post("/beneficios/{$benefit->slug}/canjear")
            ->assertForbidden();

        $user = $this->member();
        $inactive = $this->benefit(['is_active' => false]);

        $this->actingAs($user)
            ->post("/beneficios/{$inactive->slug}/canjear")
            ->assertNotFound();

        $inactiveMerchantBenefit = $this->benefit([
            'merchant' => ['is_active' => false],
        ]);

        $this->actingAs($user)
            ->post("/beneficios/{$inactiveMerchantBenefit->slug}/canjear")
            ->assertNotFound();
    }

    public function test_merchant_without_pin_cannot_create_redemption(): void
    {
        $user = $this->member();
        $merchant = Merchant::factory()->create(['redemption_pin_hash' => null]);
        $benefit = Benefit::factory()->create(['merchant_id' => $merchant->id]);

        $this->actingAs($user)
            ->post("/beneficios/{$benefit->slug}/canjear")
            ->assertStatus(422);
    }

    public function test_active_member_creates_pending_redemption_with_snapshot_and_configured_ttl(): void
    {
        config(['jakawi.redemption.code_ttl_minutes' => 17]);
        $user = $this->member();
        $benefit = $this->benefit(['estimated_savings' => '25.50']);

        $this->actingAs($user)
            ->post("/beneficios/{$benefit->slug}/canjear")
            ->assertRedirect();

        $redemption = Redemption::query()->firstOrFail();

        $this->assertNotNull($redemption->public_id);
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/', $redemption->code);
        $this->assertSame(Redemption::STATUS_PENDING, $redemption->status);
        $this->assertSame('25.50', $redemption->savings_amount);
        $this->assertTrue($redemption->expires_at->between(now()->addMinutes(16), now()->addMinutes(18)));
        $this->assertSame($benefit->title, $redemption->benefit_title);
        $this->assertSame($benefit->merchant->name, $redemption->merchant_name);
    }

    public function test_existing_valid_pending_is_reused_and_expired_pending_allows_new_code(): void
    {
        $user = $this->member();
        $benefit = $this->benefit();

        $this->actingAs($user)->post("/beneficios/{$benefit->slug}/canjear");
        $first = Redemption::query()->firstOrFail();

        $this->actingAs($user)->post("/beneficios/{$benefit->slug}/canjear");
        $this->assertSame(1, Redemption::query()->count());

        $first->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)->post("/beneficios/{$benefit->slug}/canjear");
        $this->assertSame(2, Redemption::query()->count());
    }

    public function test_redemption_limit_counts_only_confirmed_and_null_allows_multiple(): void
    {
        $user = $this->member();
        $limited = $this->benefit(['redemption_limit_per_member' => 1]);

        Redemption::query()->create($this->redemptionPayload($user, $limited, ['status' => Redemption::STATUS_EXPIRED]));
        $this->actingAs($user)->post("/beneficios/{$limited->slug}/canjear")->assertRedirect();

        Redemption::query()->where('benefit_id', $limited->id)->update([
            'status' => Redemption::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($user)->post("/beneficios/{$limited->slug}/canjear")->assertForbidden();

        $unlimited = $this->benefit(['redemption_limit_per_member' => null]);
        Redemption::query()->create($this->redemptionPayload($user, $unlimited, ['status' => Redemption::STATUS_CONFIRMED, 'confirmed_at' => now()]));

        $this->actingAs($user)->post("/beneficios/{$unlimited->slug}/canjear")->assertRedirect();
    }

    public function test_confirmation_limit_is_revalidated_across_multiple_redemptions_for_same_member_and_benefit(): void
    {
        $user = $this->member();
        $benefit = $this->benefit(['redemption_limit_per_member' => 2]);

        Redemption::query()->create($this->redemptionPayload($user, $benefit, [
            'status' => Redemption::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]));
        Redemption::query()->create($this->redemptionPayload($user, $benefit, [
            'status' => Redemption::STATUS_CANCELLED,
        ]));
        $second = Redemption::query()->create($this->redemptionPayload($user, $benefit));
        $third = Redemption::query()->create($this->redemptionPayload($user, $benefit));

        $this->post('/validar', ['code' => $second->code, 'pin' => '123456'])
            ->assertSessionHas('success');

        $this->post('/validar', ['code' => $third->code, 'pin' => '123456'])
            ->assertSessionHas('error');

        $this->assertSame(2, Redemption::query()->where('user_id', $user->id)->where('benefit_id', $benefit->id)->confirmed()->count());
        $this->assertSame(Redemption::STATUS_PENDING, $third->refresh()->status);
    }

    public function test_only_owner_can_view_redemption_page(): void
    {
        $user = $this->member();
        $redemption = Redemption::query()->create($this->redemptionPayload($user, $this->benefit()));

        $this->actingAs($user)
            ->get("/canjes/{$redemption->public_id}")
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get("/canjes/{$redemption->public_id}")
            ->assertForbidden();
    }

    public function test_validator_confirms_with_correct_pin_and_is_idempotent(): void
    {
        $user = $this->member();
        $benefit = $this->benefit();
        $redemption = Redemption::query()->create($this->redemptionPayload($user, $benefit));

        $this->post('/validar', ['code' => $redemption->code, 'pin' => '000000'])
            ->assertSessionHas('error');
        $this->assertDatabaseHas('redemptions', ['id' => $redemption->id, 'status' => Redemption::STATUS_PENDING]);

        Merchant::factory()->withRedemptionPin('654321')->create();
        $this->post('/validar', ['code' => $redemption->code, 'pin' => '654321'])
            ->assertSessionHas('error');

        $this->post('/validar', ['code' => strtolower($redemption->code), 'pin' => '123456'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('redemptions', ['id' => $redemption->id, 'status' => Redemption::STATUS_CONFIRMED]);
        $this->assertNotNull($redemption->refresh()->confirmed_at);

        $this->post('/validar', ['code' => $redemption->code, 'pin' => '123456'])
            ->assertSessionHas('success', 'Este canje ya fue confirmado.');
        $this->assertSame(1, Redemption::query()->confirmed()->count());
    }

    public function test_validator_rejects_expired_or_changed_state(): void
    {
        $user = $this->member();
        $benefit = $this->benefit();
        $expired = Redemption::query()->create($this->redemptionPayload($user, $benefit, ['expires_at' => now()->subMinute()]));

        $this->post('/validar', ['code' => $expired->code, 'pin' => '123456'])
            ->assertSessionHas('error');
        $this->assertSame(Redemption::STATUS_EXPIRED, $expired->refresh()->status);

        $redemption = Redemption::query()->create($this->redemptionPayload($user, $benefit));
        $benefit->update(['is_active' => false]);

        $this->post('/validar', ['code' => $redemption->code, 'pin' => '123456'])
            ->assertSessionHas('error');

        $benefit->update(['is_active' => true]);
        $membershipRedemption = Redemption::query()->create($this->redemptionPayload($user, $benefit));
        $user->memberships()->update(['status' => Membership::STATUS_CANCELLED]);

        $this->post('/validar', ['code' => $membershipRedemption->code, 'pin' => '123456'])
            ->assertSessionHas('error');

        $activeUser = $this->member();
        $merchantBenefit = $this->benefit();
        $merchantRedemption = Redemption::query()->create($this->redemptionPayload($activeUser, $merchantBenefit));
        $merchantBenefit->merchant->update(['is_active' => false]);

        $this->post('/validar', ['code' => $merchantRedemption->code, 'pin' => '123456'])
            ->assertSessionHas('error');
    }

    public function test_mi_jakawi_uses_only_confirmed_savings_snapshots(): void
    {
        $user = $this->member();
        $benefit = $this->benefit(['estimated_savings' => '40.00']);
        Redemption::query()->create($this->redemptionPayload($user, $benefit, ['status' => Redemption::STATUS_PENDING, 'savings_amount' => '99.00']));
        Redemption::query()->create($this->redemptionPayload($user, $benefit, ['status' => Redemption::STATUS_CONFIRMED, 'confirmed_at' => now(), 'savings_amount' => '40.00']));
        Redemption::query()->create($this->redemptionPayload($user, $benefit, ['status' => Redemption::STATUS_CONFIRMED, 'confirmed_at' => now(), 'savings_amount' => null]));
        $benefit->update(['estimated_savings' => '200.00']);

        $this->actingAs($user)
            ->get('/mi-jakawi')
            ->assertInertia(fn (Assert $page) => $page
                ->where('redemptionStats.count', 2)
                ->where('redemptionStats.savings_total', '40'));
    }

    public function test_admin_redemptions_auth_and_listing(): void
    {
        Redemption::query()->create($this->redemptionPayload($this->member(), $this->benefit()));

        $this->get('/admin/redemptions')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/admin/redemptions')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/redemptions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/redemptions/index')
                ->has('redemptions.data', 1));
    }

    public function test_pin_is_hashed_and_empty_update_keeps_existing_hash(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/merchants', [
            'name' => 'PIN Merchant',
            'redemption_pin' => '654321',
        ]);

        $merchant = Merchant::query()->firstOrFail();
        $this->assertNotSame('654321', $merchant->redemption_pin_hash);
        $this->assertTrue(Hash::check('654321', $merchant->redemption_pin_hash));

        $hash = $merchant->redemption_pin_hash;
        $this->actingAs($admin)->put("/admin/merchants/{$merchant->id}", [
            'name' => $merchant->name,
            'redemption_pin' => '',
        ]);
        $this->assertSame($hash, $merchant->refresh()->redemption_pin_hash);

        $this->actingAs($admin)->put("/admin/merchants/{$merchant->id}", [
            'name' => $merchant->name,
            'redemption_pin' => '111111',
        ]);
        $this->assertTrue(Hash::check('111111', $merchant->refresh()->redemption_pin_hash));

        $this->actingAs($admin)
            ->get("/admin/merchants/{$merchant->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('merchant.has_redemption_pin', true)
                ->missing('merchant.redemption_pin_hash'));
    }

    private function member(string $status = Membership::STATUS_ACTIVE): User
    {
        $user = User::factory()->create();
        Membership::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        return $user;
    }

    /** @param array<string, mixed> $attributes */
    private function benefit(array $attributes = []): Benefit
    {
        $merchant = Merchant::factory()->withRedemptionPin()->create($attributes['merchant'] ?? []);
        unset($attributes['merchant']);

        return Benefit::factory()->create(['merchant_id' => $merchant->id, ...$attributes]);
    }

    /** @param array<string, mixed> $overrides */
    private function redemptionPayload(User $user, Benefit $benefit, array $overrides = []): array
    {
        $membership = $user->activeMembership()->first() ?? $user->memberships()->firstOrFail();

        return [
            'code' => strtoupper(fake()->unique()->bothify('A##B##')),
            'user_id' => $user->id,
            'membership_id' => $membership->id,
            'merchant_id' => $benefit->merchant_id,
            'benefit_id' => $benefit->id,
            'merchant_name' => $benefit->merchant->name,
            'benefit_title' => $benefit->title,
            'status' => Redemption::STATUS_PENDING,
            'savings_amount' => $benefit->estimated_savings,
            'expires_at' => now()->addMinutes(10),
            ...$overrides,
        ];
    }
}
