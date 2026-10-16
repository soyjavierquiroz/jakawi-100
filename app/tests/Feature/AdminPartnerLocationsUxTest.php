<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPartnerLocationsUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_editor_and_listing_expose_locations_and_only_published_public_links(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->create(['status' => 'draft', 'name' => 'A Partner']);
        $location = Location::factory()->for($partner)->create(['status' => 'draft']);
        $this->get('/admin/partners')->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.id', $partner->id)
            ->where('items.data.0.publicUrl', null)
            ->where('items.data.0.gallery_count', 0)
            ->where('items.data.0.locations.0.id', $location->id));
        $this->get('/admin/partners/'.$partner->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('publicUrl', null)
            ->where('locations.0.id', $location->id)
            ->where('locations.0.status', 'draft'));
        $partner->update(['status' => 'published']);
        $this->get('/admin/partners/'.$partner->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('publicUrl', route('partners.public', $partner)));
        $this->assertSame('draft', $location->fresh()->status);
    }

    public function test_associated_create_uses_existing_flow_and_preserves_owner_and_state_on_save(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->create();
        $city = City::where('slug', 'cochabamba')->firstOrFail();
        $this->get('/admin/locations/create?partner='.$partner->id)->assertInertia(fn (Assert $page) => $page
            ->where('initialPartnerId', $partner->id)->where('item', null));
        $payload = ['partner_id' => $partner->id, 'city_id' => $city->id, 'name' => 'Sede editorial', 'slug' => 'sede-editorial', 'location_type' => 'branch', 'status' => 'draft', 'opening_hours' => ['monday' => '09:00-18:00']];
        $this->post('/admin/locations', $payload)->assertRedirect('/admin/locations')->assertSessionHasNoErrors()->assertSessionHas('success');
        $location = Location::where('slug', 'sede-editorial')->sole();
        $this->put('/admin/locations/'.$location->id, [...$payload, 'name' => 'Sede actualizada'])->assertRedirect('/admin/locations')->assertSessionHasNoErrors();
        $this->assertSame($partner->id, $location->fresh()->partner_id);
        $this->assertSame('draft', $location->fresh()->status);
        $this->assertSame($payload['opening_hours'], $location->fresh()->opening_hours);
        $this->get('/admin/locations')->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.partner.name', $partner->name)
            ->where('items.data.0.city_entity.name', $city->name));
    }

    public function test_partner_identity_save_preserves_slug_media_and_location_draft(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->create(['status' => 'draft', 'logo_path' => 'existing-logo.webp', 'cover_path' => 'existing-cover.webp', 'internal_notes' => 'Solo administración']);
        $location = Location::factory()->for($partner)->create(['status' => 'draft']);
        $this->put('/admin/partners/'.$partner->id, [
            'name' => 'Nuevo nombre editorial', 'slug' => $partner->slug, 'entity_type' => $partner->entity_type,
            'partner_type' => $partner->partner_type, 'status' => 'published',
        ])->assertRedirect('/admin/partners')->assertSessionHasNoErrors()->assertSessionHas('success');
        $saved = $partner->fresh();
        $this->assertSame($partner->slug, $saved->slug);
        $this->assertSame('existing-logo.webp', $saved->logo_path);
        $this->assertSame('existing-cover.webp', $saved->cover_path);
        $this->assertSame('Solo administración', $saved->internal_notes);
        $this->assertSame('draft', $location->fresh()->status);
        $this->assertNull($saved->referral_code);
    }
}
