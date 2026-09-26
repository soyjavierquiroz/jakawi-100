<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\AttributionTouch;
use App\Models\Conversion;
use App\Models\ReferralRelationship;
use App\Models\User;
use App\Services\ConversionRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributionFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function referrer(string $code = 'COCHAFOOD'): User { return User::factory()->create(['referral_code' => $code, 'referral_code_normalized' => $code]); }
    private function register(array $data = []): void { $this->post('/register', array_merge(['name' => 'New User', 'email' => 'new@example.test', 'password' => 'password', 'password_confirmation' => 'password'], $data))->assertRedirect(); }

    public function test_anonymous_referral_is_associated_at_signup(): void { $referrer = $this->referrer(); $this->get('/r/COCHAFOOD?utm_source=tiktok&utm_campaign=launch')->assertRedirect('/?utm_source=tiktok&utm_campaign=launch'); $this->register(); $user = User::whereEmail('new@example.test')->firstOrFail(); $this->assertDatabaseHas('referral_relationships', ['referrer_user_id' => $referrer->id, 'referred_user_id' => $user->id]); $this->assertDatabaseHas('attribution_touches', ['user_id' => $user->id, 'utm_source' => 'tiktok']); }
    public function test_first_referrer_remains_within_window_and_second_is_a_touch(): void { $a = $this->referrer('A100'); $b = $this->referrer('B100'); $this->get('/r/A100'); $this->get('/r/B100'); $this->register(); $user = User::whereEmail('new@example.test')->firstOrFail(); $this->assertDatabaseHas('referral_relationships', ['referrer_user_id' => $a->id, 'referred_user_id' => $user->id, 'status' => 'active']); $this->assertDatabaseHas('attribution_touches', ['user_id' => $user->id, 'referrer_user_id' => $b->id]); }
    public function test_expired_relationship_allows_next_referrer(): void { $a = $this->referrer('A100'); $b = $this->referrer('B100'); AppSetting::create(['key' => 'attribution_window_days', 'value' => ['days' => 1]]); $this->get('/r/A100'); $this->register(); $user = User::whereEmail('new@example.test')->firstOrFail(); ReferralRelationship::where('referred_user_id', $user->id)->update(['expires_at' => now()->subDay()]); $this->actingAs($user)->get('/r/B100'); $touch = AttributionTouch::where('referrer_user_id', $b->id)->latest()->firstOrFail(); app(\App\Services\AttributionService::class)->applyFirstValidReferrer($user, $touch); $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $user->id, 'referrer_user_id' => $b->id, 'status' => 'active']); }
    public function test_manual_code_applies_but_does_not_overwrite_valid_referrer(): void { $a = $this->referrer('A100'); $b = $this->referrer('B100'); $this->get('/r/A100'); $this->register(['referral_code' => 'B100']); $user = User::whereEmail('new@example.test')->firstOrFail(); $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $user->id, 'referrer_user_id' => $a->id]); $this->assertDatabaseHas('attribution_touches', ['user_id' => $user->id, 'referrer_user_id' => $b->id]); }
    public function test_self_referral_is_not_created(): void { $user = $this->referrer('SELF100'); $touch = AttributionTouch::create(['user_id' => $user->id, 'referrer_user_id' => $user->id, 'referral_code' => 'SELF100', 'occurred_at' => now()]); $this->assertNull(app(\App\Services\AttributionService::class)->applyFirstValidReferrer($user, $touch)); }
    public function test_conversion_recording_is_idempotent(): void { $user = User::factory()->create(); $first = app(ConversionRecorder::class)->record($user, ['idempotency_key' => 'membership-sale-1', 'type' => 'membership_sale', 'gross_amount' => '100.00']); $second = app(ConversionRecorder::class)->record($user, ['idempotency_key' => 'membership-sale-1', 'type' => 'membership_sale', 'gross_amount' => '100.00']); $this->assertSame($first->id, $second->id); $this->assertSame(1, Conversion::count()); }
    public function test_admin_can_update_window_and_non_admin_cannot(): void { $admin = User::factory()->create(['is_admin' => true]); $this->actingAs($admin)->put('/admin/attribution/settings', ['attribution_window_days' => 45])->assertRedirect(); $this->assertDatabaseHas('app_settings', ['key' => 'attribution_window_days']); $this->actingAs(User::factory()->create())->put('/admin/attribution/settings', ['attribution_window_days' => 20])->assertForbidden(); }
    public function test_unknown_code_and_redirect_stay_safe(): void { $this->get('/r/UNKNOWN?redirect=https://evil.test')->assertRedirect('/'); }
}
