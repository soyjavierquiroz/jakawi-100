<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBenefitFormUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_expose_partner_locations_and_preserve_the_save_contract(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->create();
        $location = Location::factory()->for($partner)->create(['status' => 'draft']);
        $other = Location::factory()->create();
        $this->get('/admin/benefits/create')->assertInertia(fn (Assert $page) => $page
            ->component('admin/resources/form')->where('item', null)
            ->has('locations', 2)->where('locations', fn ($items) => collect($items)->pluck('partner_id')->contains($partner->id)));
        $benefit = Benefit::factory()->forPartner($partner)->create([
            'starts_at' => '2026-10-15 12:34:56', 'ends_at' => '2026-11-15 13:45:57',
            'estimated_savings' => '12.50', 'image_path' => 'existing.webp', 'status' => 'draft',
        ]);
        $benefit->syncLocations([$location->id]);
        $this->get('/admin/benefits/'.$benefit->slug.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('item.id', $benefit->id)->where('item.locations.0.id', $location->id)->has('locations', 2));
        $before = $benefit->fresh()->only(['starts_at', 'ends_at', 'estimated_savings', 'image_path', 'access_mode', 'status', 'partner_id']);
        $payload = [...$benefit->fresh()->toArray(), 'title' => 'Título actualizado', 'location_scope' => 'selected', 'location_ids' => [$location->id]];
        $this->put('/admin/benefits/'.$benefit->slug, $payload)->assertRedirect('/admin/benefits')->assertSessionHasNoErrors();
        $this->assertEquals($before, $benefit->fresh()->only(array_keys($before)));
        $this->assertSame([$location->id], $benefit->fresh()->locations->modelKeys());
        $this->assertSame('draft', $location->fresh()->status);
        $this->put('/admin/benefits/'.$benefit->slug, [...$payload, 'location_ids' => [$other->id]])->assertSessionHasErrors('location_ids');
        $this->assertSame([$location->id], $benefit->fresh()->locations->modelKeys());
        $this->post('/admin/benefits', [...$payload, 'slug' => 'benefit-create-ux', 'location_scope' => 'all', 'location_ids' => []])->assertRedirect('/admin/benefits')->assertSessionHasNoErrors();
    }
}
