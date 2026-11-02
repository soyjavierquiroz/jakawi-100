<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Services\Commercial\MembershipAcquisition;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembershipIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_unverified_identity_and_canonical_editor_preserve_origin(): void
    {
        $user = User::factory()->unverified()->create();
        $partner = Partner::factory()->published()->create();
        $origin = app(MembershipAcquisition::class)->insert(['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $partner->id], 'lead', $user->id);
        $this->actingAs($user)->get('/perfil')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('membershipStatus.type', 'free')->where('membershipStatus.status', 'free')
            ->where('membershipStatus.benefits.club_access', true)->where('membershipStatus.benefits.exclusive_member_benefits', false)
            ->where('acquisitionOrigin.name', $partner->name));
        $this->get('/mi-jakawi')->assertOk()->assertInertia(fn (Assert $p) => $p->where('membershipStatus.type', 'free'));
        $this->get('/settings/profile')->assertRedirect('/perfil');
        $this->get('/mi-jakawi/perfil')->assertRedirect('/perfil');
        $this->put('/perfil', ['name' => 'Updated', 'email' => $user->email, 'interests' => ['cafe'], 'is_admin' => true, 'global_subject_id' => (string) Str::uuid()])->assertRedirect('/perfil');
        $this->assertSame('Updated', $user->fresh()->name);
        $this->assertSame($user->global_subject_id, $user->fresh()->global_subject_id);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertEquals($origin, app(MembershipAcquisition::class)->original($user));
        $this->assertDatabaseCount('attribution_touches', 0);
        $this->assertDatabaseCount('referral_relationships', 0);
    }

    public function test_profile_email_edit_invalidates_verification_and_rejects_duplicate_identity(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->put('/perfil', ['email' => $other->email])->assertSessionHasErrors('email');
        $this->put('/perfil', ['email' => 'updated@example.test', 'name' => 'Updated'])->assertRedirect('/perfil');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('updated@example.test', $user->fresh()->email);
    }

    public function test_pioneer_uses_effective_expiry_in_both_personal_views(): void
    {
        config(['jakawi.membership.official_launch_date' => null]);
        $this->travelTo(now()->setDate(2030, 1, 1));
        $user = User::factory()->create();
        $m = app(MembershipService::class)->activate($user, User::factory()->create(), now()->subYears(3));
        $m->update(['type' => 'pioneer']);
        foreach (['/perfil', '/mi-jakawi'] as $path) {
            $this->actingAs($user)->get($path)->assertOk()->assertInertia(fn (Assert $p) => $p
                ->where('membershipStatus.type', 'pioneer')->where('membershipStatus.status', 'active')
                ->where('membershipStatus.effective_ends_at', null)->where('membershipStatus.official_launch_date', null)
                ->where('membershipStatus.benefits.exclusive_member_benefits', true));
        }
        $this->get('/mi-jakawi')->assertInertia(fn (Assert $p) => $p->where('membership.ends_at', null)->where('membership.days_remaining', null));
        $this->travelBack();
    }

    public function test_signed_contract_is_current_bounded_and_does_not_require_a_purchase(): void
    {
        config(['club_purchases.secret' => str_repeat('p', 64), 'club_purchases.key_id' => 'club-purchases-v1', 'jakawi.membership.official_launch_date' => null]);
        $user = User::factory()->unverified()->create();
        $subject = ['global_subject_id' => $user->global_subject_id];
        $this->signed($subject)->assertOk()->assertJsonPath('membership.type', 'free')->assertJsonPath('membership.is_active', false)
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('Set-Cookie')->assertJsonMissingPath('email');
        $m = app(MembershipService::class)->activate($user, User::factory()->create());
        $this->signed($subject)->assertJsonPath('membership.type', 'standard')->assertJsonPath('membership.is_active', true);
        $m->update(['type' => 'pioneer', 'ends_at' => now()->subDay()]);
        $this->signed($subject)->assertJsonPath('membership.type', 'pioneer')->assertJsonPath('membership.is_active', true)->assertJsonPath('membership.effective_ends_at', null);
        config(['jakawi.membership.official_launch_date' => '2026-01-31']);
        $this->signed($subject)->assertJsonPath('membership.effective_ends_at', '2027-05-01T03:59:59.999999Z');
        $this->travelTo(now()->setDate(2027, 5, 1)->setTime(4, 0));
        $this->signed($subject)->assertJsonPath('membership.status', 'expired')->assertJsonPath('membership.is_active', false);
        $m->update(['starts_at' => now()->addDay()]);
        $this->signed($subject)->assertJsonPath('membership.status', 'scheduled')->assertJsonPath('membership.is_active', false);
        $m->update(['status' => 'cancelled']);
        $this->signed($subject)->assertJsonPath('membership.status', 'cancelled')->assertJsonPath('membership.benefits.exclusive_member_benefits', false);
        $this->travelBack();
        $this->signed(['global_subject_id' => (string) Str::uuid()])->assertNotFound();
        $this->signed(['global_subject_id' => 'invalid'])->assertUnprocessable();
        $this->postJson('/internal/club/memberships/status', $subject)->assertUnauthorized();
        $nonce = (string) Str::uuid();
        $this->signed($subject, $nonce)->assertOk();
        $this->signed($subject, $nonce)->assertStatus(409);
        $this->signed($subject, secret: 'wrong')->assertUnauthorized();
        $this->assertDatabaseCount('membership_bank_payments', 0);
        $this->assertDatabaseCount('identity_links', 0);
    }

    private function signed(array $data, ?string $nonce = null, ?string $secret = null): TestResponse
    {
        $path = '/internal/club/memberships/status';
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $ts = (string) time();
        $nonce ??= (string) Str::uuid();
        $canonical = implode("\n", ['jakawi-club-purchases-v1', 'club-purchases-v1', $ts, $nonce, 'POST', $path, hash('sha256', $body)]);

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_JAKAWI_KEY_ID' => 'club-purchases-v1', 'HTTP_X_JAKAWI_TIMESTAMP' => $ts,
            'HTTP_X_JAKAWI_NONCE' => $nonce,
            'HTTP_X_JAKAWI_SIGNATURE' => hash_hmac('sha256', $canonical, $secret ?? config('club_purchases.secret')),
        ], $body);
    }
}
