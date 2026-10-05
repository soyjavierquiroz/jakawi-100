<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AttributionTouch;
use App\Models\City;
use App\Models\PartnerApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnersLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_uses_selected_city_and_existing_application_flow(): void
    {
        City::query()->where('slug', 'la-paz')->sole()->update(['status' => City::ACTIVE]);
        $visitor = 'cecc2e8f-2a74-4c33-b92a-4ce5a913c4bc';
        $response = $this->withUnencryptedCookie('jakawi_visitor_id', $visitor)
            ->withCookie('selected_city', 'la-paz')
            ->get('/partners?utm_source=radio&campaign=octubre');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('partners/index')->where('city.slug', 'la-paz')->where('canonical', route('partners.index')));
        $touch = AttributionTouch::sole();
        $this->assertSame('/partners', $touch->landing_page);
        $this->assertSame('radio', $touch->utm_source);
        $this->assertSame('octubre', $touch->utm_campaign);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'landing_view', 'visitor_id' => $visitor]);

        $this->get('/partners/aplicar')->assertRedirect('/ciudades/la-paz/partner');
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'landing_cta_click', 'visitor_id' => $visitor]);
        $this->get('/ciudades/la-paz/partner')->assertOk();
        $this->post('/ciudades/la-paz/partner', ['business_name' => 'Café Ruta', 'contact_name' => 'Ana', 'contact_phone' => '70000000'])->assertRedirect('/ciudades/la-paz/partner/recibida');
        $this->assertSame($touch->id, PartnerApplication::sole()->attribution_touch_id);
    }

    public function test_landing_uses_the_product_fallback_when_city_cookie_is_missing_or_stale(): void
    {
        $fallback = City::query()->where('slug', 'cochabamba')->sole();
        $fallback->update(['status' => City::ACTIVE]);
        $this->get('/partners')->assertInertia(fn (Assert $page) => $page->where('city.slug', 'cochabamba'));
        $this->withCookie('selected_city', 'missing')->get('/partners/aplicar')->assertRedirect('/ciudades/cochabamba/partner');
    }

    public function test_landing_and_cta_do_not_duplicate_application_events(): void
    {
        $this->get('/partners')->assertOk();
        $this->get('/partners/aplicar')->assertRedirect();
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'landing_view')->count());
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'landing_cta_click')->count());
        $this->assertSame(0, PartnerApplication::count());
    }
}
