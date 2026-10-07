<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AttributionTouch;
use App\Models\Benefit;
use App\Models\Conversion;
use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\Membership;
use App\Models\MembershipPurchase;
use App\Models\MembershipPurchaseRequest;
use App\Models\Location;
use App\Models\Redemption;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembershipConversionJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_states_and_registration_return(): void
    {
        $this->get('/membresia')->assertOk()->assertInertia(fn (Assert $page) => $page->component('membresia')->where('membership', null)->where('requestStatus', null));
        $this->get('/membresia/registro')->assertRedirect('/register');
        $this->post('/register', ['name' => 'New User', 'email' => 'join@example.test', 'whatsapp' => '71234567'])->assertRedirect('/membresia');
        $user = User::where('email', 'join@example.test')->firstOrFail();
        $this->get('/membresia')->assertInertia(fn (Assert $page) => $page->where('membership', null));
        $member = $this->activate($user);
        $this->get('/membresia')->assertInertia(fn (Assert $page) => $page->where('membership.ends_at', $member->ends_at->toISOString())->etc());
        $member->update(['ends_at' => now()->subDay()]);
        $this->get('/membresia')->assertInertia(fn (Assert $page) => $page->where('membership', null));
        $this->assertSame(4, AnalyticsEvent::where('event_name', 'membership_view')->count());
    }

    public function test_request_freezes_intent_and_touch_and_rejects_payloads_and_duplicates(): void
    {
        $user = User::factory()->create();
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create();
        $touch = AttributionTouch::create(['user_id' => $user->id, 'utm_source' => 'first', 'occurred_at' => now()->subMinute()]);
        $this->actingAs($user)->get('/membresia?journey=EXPERIENCE&action=RESERVE&resource_id='.$experience->id.'&experience_session_id='.$session->id)->assertOk();
        $this->post('/membresia/solicitar', ['return_to' => 'https://evil.test'])->assertSessionHasErrors('journey');
        $this->post('/membresia/solicitar')->assertRedirect('/membresia');
        $this->post('/membresia/solicitar')->assertRedirect('/membresia');
        $item = MembershipPurchaseRequest::firstOrFail();
        $this->assertSame(1, MembershipPurchaseRequest::count());
        $this->assertSame($touch->id, $item->attribution_touch_id);
        $this->assertSame('EXPERIENCE', $item->journey_type);
        $this->assertSame(['experience_session_id' => $session->id], $item->journey_context);
        $this->get('/membresia?journey=EXPERIENCE&action=DELETE&resource_id='.$experience->id)->assertSessionHasErrors('journey');
        $this->get('/membresia?journey=EXPERIENCE&action=RESERVE&resource_id='.$experience->id.'&url=https://evil.test')->assertSessionHasErrors('journey');
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'membership_assistance_requested')->count());
    }

    public function test_admin_sale_completes_request_with_original_attribution_and_single_use_return(): void
    {
        $user = User::factory()->create(); $admin = User::factory()->create(['is_admin' => true]);
        $benefit = Benefit::factory()->create();
        $first = AttributionTouch::create(['user_id' => $user->id, 'utm_source' => 'original', 'utm_campaign' => 'unknown-code', 'campaign_key' => 'marketing-only', 'occurred_at' => now()->subMinutes(3)]);
        $this->actingAs($user)->get('/membresia?journey=BENEFIT&action=REDEEM&resource_id='.$benefit->id)->assertOk();
        $this->post('/membresia/solicitar')->assertRedirect();
        $item = MembershipPurchaseRequest::firstOrFail();
        AttributionTouch::create(['user_id' => $user->id, 'utm_source' => 'later', 'occurred_at' => now()]);
        $this->actingAs($user)->get('/admin/solicitudes-membresia')->assertForbidden();
        $this->actingAs($admin)->get('/admin/solicitudes-membresia')->assertOk();
        $this->get('/admin/solicitudes-membresia/'.$item->id)->assertOk();
        $this->get('/admin/sales/create?membership_purchase_request_id='.$item->id)->assertOk();
        $key = (string) Str::uuid();
        $payload = ['user_id' => $user->id, 'membership_purchase_request_id' => $item->id, 'manual_reference' => 'CASH-1', 'idempotency_key' => $key];
        $this->post('/admin/sales', $payload)->assertRedirect();
        $this->post('/admin/sales', $payload)->assertRedirect();
        $item->refresh(); $sale = MembershipPurchase::firstOrFail();
        $this->assertSame(MembershipPurchaseRequest::COMPLETED, $item->status);
        $this->assertSame($sale->id, $item->membership_purchase_id);
        $this->assertTrue($user->hasActiveMembership());
        $this->assertSame(1, MembershipPurchase::count());
        $this->assertSame(1, Conversion::where('type', 'membership_purchased')->count());
        $this->assertSame($first->id, $sale->conversion->attribution_touch_id);
        $this->assertSame('original', $sale->conversion->attribution_snapshot['utm_source']);
        $this->assertNull($sale->conversion->campaign_id);
        $this->assertSame($admin->id, $sale->recorded_by_user_id);
        $this->assertNull($sale->collected_by_user_id);
        $this->actingAs($user)->get('/membresia')->assertInertia(fn (Assert $page) => $page->where('returnIntent.id', $item->id)->etc());
        $this->post('/membresia/volver/'.$item->id)->assertRedirect(route('benefits.show', $benefit->slug, false));
        $this->post('/membresia/volver/'.$item->id)->assertNotFound();
        $this->get('/membresia')->assertInertia(fn (Assert $page) => $page->where('returnIntent', null));
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'membership_purchase_confirmed')->count());
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'membership_returned_to_intent')->count());
        $this->assertSame(0, Redemption::count());
    }

    public function test_experience_return_restores_valid_session_without_reserving(): void
    {
        $user = User::factory()->create(); $admin = User::factory()->create(['is_admin' => true]);
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create();
        $this->actingAs($user)->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id])->assertRedirect();
        $this->get('/membresia?journey=EXPERIENCE&action=RESERVE&resource_id='.$experience->id.'&experience_session_id='.$session->id)->assertOk();
        $this->post('/membresia/solicitar')->assertRedirect();
        $item = MembershipPurchaseRequest::firstOrFail();
        $this->actingAs($admin)->post('/admin/sales', ['user_id' => $user->id, 'membership_purchase_request_id' => $item->id, 'manual_reference' => 'CASH-2', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->actingAs($user)->post('/membresia/volver/'.$item->id)->assertRedirect(route('experiences.show', $experience->slug, false));
        $this->get(route('experiences.show', $experience))->assertInertia(fn (Assert $page) => $page->where('restoredSessionId', $session->id));
        $this->assertSame(0, ExperienceReservation::count());
        $this->assertSame(0, UnlockParticipation::count());
    }

    public function test_member_gate_and_invalid_session_return_stays_on_experience_detail(): void
    {
        $user = User::factory()->create(); $admin = User::factory()->create(['is_admin' => true]);
        $experience = Experience::factory()->published()->create(['reservation_method' => 'jakawi']);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create();
        $this->actingAs($user)->post(route('experiences.reservations.store', $experience), ['experience_session_id' => $session->id])
            ->assertRedirect('/membresia?journey=EXPERIENCE&action=RESERVE&resource_id='.$experience->id.'&experience_session_id='.$session->id);
        $this->get('/membresia?journey=EXPERIENCE&action=RESERVE&resource_id='.$experience->id.'&experience_session_id='.$session->id)->assertOk();
        $this->post('/membresia/solicitar')->assertRedirect();
        $item = MembershipPurchaseRequest::firstOrFail();
        $this->actingAs($admin)->post('/admin/sales', ['user_id' => $user->id, 'membership_purchase_request_id' => $item->id, 'manual_reference' => 'CASH-3', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $session->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subHour()]);
        $this->actingAs($user)->post('/membresia/volver/'.$item->id)->assertRedirect(route('experiences.show', $experience->slug, false));
        $this->get(route('experiences.show', $experience))->assertInertia(fn (Assert $page) => $page->where('restoredSessionId', null));
        $this->assertSame(0, ExperienceReservation::count());
    }

    public function test_benefit_and_unlock_gates_and_active_member_cannot_request(): void
    {
        $user = User::factory()->create();
        $benefit = Benefit::factory()->published()->create();
        $location = Location::factory()->create();
        $unlock = Unlock::create(['title' => 'Member unlock', 'slug' => 'member-unlock', 'origin' => 'JAKAWI',
            'type' => 'BENEFIT', 'minimum_commitments' => 5, 'free_user_eligible' => false, 'member_eligible' => true,
            'jp_deposit' => 0, 'status' => Unlock::ACTIVE]);
        $this->actingAs($user)->post(route('redemptions.start', $benefit), ['location_id' => $location->id])
            ->assertRedirect('/membresia?journey=BENEFIT&action=REDEEM&resource_id='.$benefit->id);
        $this->post(route('unlocks.commit', $unlock))
            ->assertRedirect('/membresia?journey=UNLOCK&action=COMMIT&resource_id='.$unlock->id);
        $this->get('/membresia?journey=UNLOCK&action=COMMIT&resource_id='.$unlock->id)->assertOk();
        $this->post('/membresia/solicitar')->assertRedirect();
        $item = MembershipPurchaseRequest::firstOrFail();
        $this->assertSame('UNLOCK', $item->journey_type);
        $this->activate($user);
        $this->post('/membresia/solicitar')->assertRedirect();
        $this->assertSame(1, MembershipPurchaseRequest::count());
        $this->assertSame(0, Redemption::count());
        $this->assertSame(0, UnlockParticipation::count());
    }

    private function activate(User $user): Membership
    {
        return Membership::create(['user_id' => $user->id, 'status' => Membership::STATUS_ACTIVE, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'amount_paid' => 100]);
    }
}
