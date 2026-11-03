<?php

namespace Tests\Feature;

use App\Models\{AnalyticsEvent, Benefit, Partner, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerUsefulPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_reuses_event_metrics_and_counts_only_current_partner_inventory(): void
    {
        $partner = Partner::factory()->published()->create();
        $owner = User::factory()->create();
        $owner->partners()->attach($partner, ['role' => 'owner', 'economic_access' => false]);
        Benefit::factory()->forPartner($partner)->create(['status' => 'published', 'review_status' => 'approved', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        Benefit::factory()->forPartner($partner)->create(['status' => 'published', 'review_status' => 'approved', 'ends_at' => now()->subDay()]);
        Benefit::factory()->forPartner($partner)->create(['status' => 'published', 'review_status' => 'approved', 'starts_at' => now()->addDay()]);
        Benefit::factory()->forPartner($partner)->create(['status' => 'draft', 'review_status' => 'draft']);
        Benefit::factory()->forPartner($partner)->create(['status' => 'draft', 'review_status' => 'submitted']);
        $other = Partner::factory()->published()->create();
        Benefit::factory()->forPartner($other)->create(['status' => 'draft', 'review_status' => 'draft']);
        foreach (['partner_view', 'partner_view', 'benefit_view'] as $event) AnalyticsEvent::create(['event_name' => $event, 'partner_id' => $partner->id, 'occurred_at' => now()]);
        AnalyticsEvent::create(['event_name' => 'partner_view', 'partner_id' => $other->id, 'occurred_at' => now()]);
        $base = '/partner/'.$partner->slug;
        $this->actingAs($owner)->get($base)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('benefitPublished', 1)->where('benefitDrafts', 1)->where('benefitSubmitted', 1)
            ->where('activity.partner_views', 2)->where('activity.benefit_views', 1)->where('activity.confirmed', 0)
            ->where('partnerAccess.can_view_economics', true)->missing('activity.savings'));
        $this->get($base.'/promociones?estado=vigentes')->assertOk()->assertInertia(fn (Assert $p) => $p->has('items.data', 1));
        $this->get($base.'/promociones?estado=submitted')->assertOk()->assertInertia(fn (Assert $p) => $p->has('items.data', 1)->where('items.data.0.review_status', 'submitted'));
        $this->get($base.'/promociones?estado=expired')->assertOk()->assertInertia(fn (Assert $p) => $p->has('items.data', 1));
        $staff = User::factory()->create();
        $staff->partners()->attach($partner, ['role' => 'staff']);
        $this->actingAs($staff)->get($base)->assertOk()->assertInertia(fn (Assert $p) => $p->where('activity', null)->where('partnerAccess.can_view_economics', false));
        $this->get($base.'/rendimiento')->assertForbidden();
    }

    public function test_commercial_terms_and_dates_follow_review_and_published_rows_stay_unchanged(): void
    {
        $partner = Partner::factory()->published()->create();
        $owner = User::factory()->create();
        $owner->partners()->attach($partner, ['role' => 'owner']);
        $base = '/partner/'.$partner->slug.'/promociones';
        $data = ['title' => 'Promo con condiciones', 'terms' => 'Sólo lunes. Consumo mínimo Bs 50.', 'location_scope' => 'all', 'starts_at' => '2026-11-04T12:00:00-04:00', 'ends_at' => '2026-12-04T12:00:00-04:00', 'redemption_limit_per_member' => 2];
        $this->actingAs($owner)->post($base, $data)->assertRedirect();
        $benefit = Benefit::where('title', $data['title'])->firstOrFail();
        $this->assertSame($data['terms'], $benefit->terms);
        $this->assertSame('2026-11-04 16:00:00', $benefit->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->post($base.'/'.$benefit->slug.'/enviar')->assertRedirect();
        $this->assertSame('submitted', $benefit->fresh()->review_status);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/review/benefits/'.$benefit->slug, ['action' => 'approve'])->assertRedirect();
        $before = $benefit->fresh()->getAttributes();
        $this->actingAs($owner)->put($base.'/'.$benefit->slug, array_replace($data, ['title' => 'Cambio no aprobado']))->assertForbidden();
        $this->post($base.'/'.$benefit->slug.'/enviar')->assertForbidden();
        $this->assertSame($before, $benefit->fresh()->getAttributes());
        // Even an unusual published row with changes_requested must remain protected.
        $benefit->update(['review_status' => 'changes_requested']);
        $this->put($base.'/'.$benefit->slug, $data)->assertForbidden();
        $this->post($base.'/'.$benefit->slug.'/enviar')->assertForbidden();
    }
}
