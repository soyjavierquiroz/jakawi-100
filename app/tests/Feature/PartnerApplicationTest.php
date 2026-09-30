<?php

namespace Tests\Feature;

use App\Models\AttributionTouch;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerApplicationTest extends TestCase
{
    use RefreshDatabase;

    private City $laPaz;
    private City $sucre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->laPaz = City::where('slug', 'la-paz')->sole();
        $this->sucre = City::where('slug', 'sucre')->sole();
    }

    public function test_public_flow_derives_city_accepts_guest_contacts_and_has_no_economic_side_effects(): void
    {
        $visitor = 'e4a3c55e-9107-4cd0-92dc-e44d73d087b4';
        $touch = AttributionTouch::create(['anonymous_id' => $visitor, 'utm_source' => 'radio', 'utm_campaign' => 'lapaz', 'occurred_at' => now()]);
        $this->get('/ciudades/la-paz')->assertInertia(fn (Assert $page) => $page->component('cities/show')->where('city.slug', 'la-paz')->missing('applications'));
        $this->get('/ciudades/la-paz/partner')->assertOk()->assertInertia(fn (Assert $page) => $page->where('city.slug', 'la-paz'));
        $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)->post('/ciudades/la-paz/partner', ['business_name' => 'Café Ruta', 'contact_name' => 'Ana', 'contact_phone' => '+591 700 12345'])->assertRedirect('/ciudades/la-paz/partner/recibida');
        $application = PartnerApplication::sole();
        $this->assertSame($this->laPaz->id, $application->city_id); $this->assertNull($application->user_id); $this->assertSame($touch->id, $application->attribution_touch_id); $this->assertSame('radio', $application->attribution_snapshot['utm_source']);
        $this->assertDatabaseCount('partners', 0); $this->assertDatabaseCount('locations', 0); $this->assertDatabaseCount('memberships', 0); $this->assertDatabaseCount('jp_holds', 0); $this->assertDatabaseCount('reward_transactions', 0); $this->assertDatabaseCount('unlock_participations', 0);
    }

    public function test_contact_validation_duplicate_and_city_independence(): void
    {
        $data = ['business_name' => 'Café Ruta', 'contact_name' => 'Ana', 'contact_email' => 'ana@example.test'];
        $this->post('/ciudades/la-paz/partner', $data)->assertRedirect();
        $this->post('/ciudades/la-paz/partner', $data)->assertRedirect();
        $this->post('/ciudades/sucre/partner', $data)->assertRedirect();
        $this->post('/ciudades/la-paz/partner', ['business_name' => 'Sin contacto', 'contact_name' => 'Ana'])->assertSessionHasErrors(['contact_phone', 'contact_email']);
        $this->assertDatabaseCount('partner_applications', 2);
    }

    public function test_authenticated_submit_admin_workflow_city_metrics_and_paused_protection(): void
    {
        $user = User::factory()->create(); $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user)->post('/ciudades/la-paz/partner', ['business_name' => 'Tienda Norte', 'contact_name' => 'Luis', 'contact_phone' => '70000000'])->assertRedirect();
        $application = PartnerApplication::sole(); $this->assertSame($user->id, $application->user_id);
        $this->actingAs($admin)->get('/admin/solicitudes-partner')->assertOk()->assertInertia(fn (Assert $page) => $page->has('applications.data', 1));
        $this->actingAs($admin)->get('/admin/solicitudes-partner?city='.$this->laPaz->id)->assertInertia(fn (Assert $page) => $page->has('applications.data', 1));
        $this->actingAs($admin)->get('/admin/solicitudes-partner?status=QUALIFIED')->assertInertia(fn (Assert $page) => $page->has('applications.data', 0));
        $this->actingAs($admin)->get('/admin/solicitudes-partner/'.$application->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('application.id', $application->id));
        $this->actingAs($admin)->post('/admin/solicitudes-partner/'.$application->id.'/estado', ['status' => 'QUALIFIED'])->assertRedirect();
        $this->assertDatabaseHas('partner_applications', ['id' => $application->id, 'status' => 'QUALIFIED']); $this->assertDatabaseHas('audit_logs', ['action' => 'partner_application_status_changed', 'subject_id' => $application->id]);
        $this->actingAs($admin)->get('/admin/ciudades/la-paz')->assertInertia(fn (Assert $page) => $page->where('partnerApplicationSummary.total', 1)->where('partnerApplicationSummary.qualified', 1));
        $this->laPaz->update(['status' => City::PAUSED]); $this->get('/ciudades/la-paz/partner')->assertNotFound(); $this->post('/ciudades/la-paz/partner', ['business_name' => 'x', 'contact_name' => 'x', 'contact_phone' => '1'])->assertNotFound();
    }
}
