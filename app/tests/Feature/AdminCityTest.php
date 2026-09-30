<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_forbidden_from_city_administration(): void
    {
        $city = City::factory()->create();

        $this->actingAs(User::factory()->create())->get('/admin/ciudades')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/admin/ciudades', $this->payload())->assertForbidden();
        $this->actingAs(User::factory()->create())->post("/admin/ciudades/{$city->slug}/estado", ['status' => City::ACTIVE])->assertForbidden();
    }

    public function test_admin_lists_cities(): void
    {
        $admin = $this->admin();
        $city = City::factory()->create(['name' => 'Sucre']);

        $this->actingAs($admin)->get('/admin/ciudades')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/cities/index')
            ->has('cities', 6)
            ->where('cities.5.id', $city->id));
    }

    public function test_admin_views_city_detail(): void
    {
        $city = City::factory()->create(['name' => 'La Paz']);

        $this->actingAs($this->admin())->get("/admin/ciudades/{$city->slug}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('admin/cities/show')->where('city.id', $city->id)
        );
    }

    public function test_admin_creates_a_city_and_normalizes_country_code(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/ciudades', $this->payload(['country_code' => 'bo']))->assertRedirect();

        $this->assertDatabaseHas('cities', ['name' => 'Potosí', 'country_code' => 'BO', 'status' => City::COMING_SOON, 'priority' => 3]);
        $city = City::where('slug', 'potosi')->sole();
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'action' => 'city_created', 'subject_type' => City::class, 'subject_id' => $city->id]);
        $this->assertSame(null, AuditLog::where('action', 'city_created')->sole()->metadata['before']);
    }

    public function test_city_slug_must_be_unique_and_status_must_be_valid(): void
    {
        City::factory()->create(['slug' => 'potosi']);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/ciudades', $this->payload())->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post('/admin/ciudades', $this->payload(['slug' => 'tarija', 'status' => 'INVALID']))->assertSessionHasErrors('status');
    }

    public function test_admin_edits_a_city_and_priority_persists_with_audit(): void
    {
        $admin = $this->admin();
        $city = City::factory()->create(['name' => 'Antes', 'priority' => 0]);

        $this->actingAs($admin)->put("/admin/ciudades/{$city->slug}", $this->payload(['name' => 'Después', 'slug' => 'despues', 'priority' => 12]))->assertRedirect();

        $city->refresh();
        $this->assertSame('Después', $city->name);
        $this->assertSame(12, $city->priority);
        $audit = AuditLog::where('action', 'city_updated')->sole();
        $this->assertSame('Antes', $audit->metadata['before']['name']);
        $this->assertSame('Después', $audit->metadata['after']['name']);
    }

    public function test_admin_changes_city_status_explicitly_with_audit(): void
    {
        $admin = $this->admin();
        $city = City::factory()->create(['status' => City::COMING_SOON]);

        $this->actingAs($admin)->post("/admin/ciudades/{$city->slug}/estado", ['status' => City::ACTIVE])->assertRedirect();

        $this->assertSame(City::ACTIVE, $city->fresh()->status);
        $audit = AuditLog::where('action', 'city_status_changed')->sole();
        $this->assertSame(City::COMING_SOON, $audit->metadata['previous_status']);
        $this->assertSame(City::ACTIVE, $audit->metadata['new_status']);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Potosí',
            'slug' => 'potosi',
            'region' => 'Potosí',
            'country_code' => 'BO',
            'status' => City::COMING_SOON,
            'priority' => 3,
        ], $overrides);
    }
}
