<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\Conversion;
use App\Models\Partner;
use App\Models\ReferralRelationship;
use App\Models\User;
use App\Services\AttributionService;
use App\Services\ConversionRecorder;
use App\Services\PartnerAcquisitionMetrics;
use App\Services\ReferralCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private function partner(string $name = 'Burger Mania'): Partner
    {
        $partner = Partner::factory()->create(['name' => $name]);
        app(ReferralCodeService::class)->ensureFor($partner);
        return $partner->refresh();
    }

    public function test_partner_code_is_global_and_referral_records_partner_source(): void
    {
        $partner = $this->partner();
        $user = User::factory()->create(['referral_code' => 'USERCODE', 'referral_code_normalized' => 'USERCODE']);
        $this->assertNotSame('USERCODE', app(ReferralCodeService::class)->ensureFor($partner));
        $this->get('/r/'.$partner->referral_code.'?utm_source=poster&utm_campaign=door')->assertRedirect('/?utm_source=poster&utm_campaign=door');
        $this->post('/register', ['name' => 'New User', 'email' => 'partner@example.test', 'whatsapp' => '71234567'])->assertRedirect();
        $registered = User::whereEmail('partner@example.test')->firstOrFail();
        $this->assertDatabaseHas('attribution_touches', ['user_id' => $registered->id, 'acquisition_partner_id' => $partner->id, 'utm_source' => 'poster']);
        $this->assertDatabaseHas('referral_relationships', ['referred_user_id' => $registered->id, 'acquisition_partner_id' => $partner->id, 'referrer_user_id' => null]);
    }

    public function test_first_partner_remains_and_confirmed_purchase_is_aggregated_without_pii(): void
    {
        $a = $this->partner('Partner Alpha'); $b = $this->partner('Partner Beta');
        $this->get('/r/'.$a->referral_code); $this->get('/r/'.$b->referral_code);
        $this->post('/register', ['name' => 'New User', 'email' => 'metrics@example.test', 'whatsapp' => '71234567']);
        $user = User::whereEmail('metrics@example.test')->firstOrFail();
        $relationship = ReferralRelationship::where('referred_user_id', $user->id)->firstOrFail();
        $this->assertSame($a->id, $relationship->acquisition_partner_id);
        app(ConversionRecorder::class)->record($user, ['idempotency_key' => 'partner-confirmed', 'type' => 'membership_purchased', 'gross_amount' => '99.50', 'status' => 'confirmed']);
        app(ConversionRecorder::class)->record($user, ['idempotency_key' => 'partner-refund', 'type' => 'membership_purchased', 'gross_amount' => '50.00', 'status' => 'refunded']);
        $metrics = app(PartnerAcquisitionMetrics::class)->forPartner($a);
        $this->assertSame(1, $metrics['clicks']); $this->assertSame(1, $metrics['registrations']); $this->assertSame(1, $metrics['purchases']); $this->assertSame('99.50', $metrics['revenue']);
        $owner = User::factory()->create(); $owner->partners()->attach($a, ['role' => 'owner']);
        $this->actingAs($owner)->get('/partner/'.$a->slug)->assertOk()->assertDontSee('metrics@example.test');
    }

    public function test_partner_does_not_replace_user_referrer_and_expired_policy_can_replace_it(): void
    {
        $partner = $this->partner();
        $referrer = User::factory()->create(['referral_code' => 'USERREF', 'referral_code_normalized' => 'USERREF']);
        $user = User::factory()->create();
        $service = app(AttributionService::class);
        $userTouch = AttributionTouch::create(['user_id' => $user->id, 'referrer_user_id' => $referrer->id, 'referral_code' => 'USERREF', 'occurred_at' => now()]);
        $service->applyFirstValidReferrer($user, $userTouch);
        $partnerTouch = AttributionTouch::create(['user_id' => $user->id, 'acquisition_partner_id' => $partner->id, 'referral_code' => $partner->referral_code, 'occurred_at' => now()]);
        $this->assertSame($referrer->id, $service->applyFirstValidReferrer($user, $partnerTouch)->referrer_user_id);
        ReferralRelationship::where('referred_user_id', $user->id)->update(['expires_at' => now()->subSecond()]);
        $this->assertSame($partner->id, $service->applyFirstValidReferrer($user, $partnerTouch)->acquisition_partner_id);
    }
}
