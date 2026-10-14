<?php

namespace Tests\Feature;

use App\Models\{Benefit, Challenge, Experience, Partner, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_draft_can_be_opened_and_saved_by_admin_without_duplicating_or_publishing(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->published()->create(['name' => 'Toc Toc Café Gourmet']);
        $experience = Experience::factory()->draft()->create(['id' => 1, 'slug' => 'toctoccafe-descubre-el-cafe-boliviano', 'title' => 'Descubre el café boliviano', 'regular_price' => 75]);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
        $landing = $experience->landingPresentations()->create(['name' => 'Café', 'slug' => 'cafe-preview', 'status' => 'DRAFT']);
        $url = route('admin.experiences.edit', $experience);
        $this->assertStringEndsWith('/admin/experiences/'.$experience->slug.'/edit', $url);
        $this->get('/admin/experiences')->assertInertia(fn (Assert $p) => $p->component('admin/catalog/index')->where('items.data.0.editUrl', $url)->where('items.data.0.partners.0.name', $partner->name)->where('items.data.0.publicUrl', null));
        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->where('item.id', 1)->where('item.status', 'draft')->where('item.regular_price', '75.00'));
        $this->get('/admin/experiences/1/edit')->assertNotFound(); // Documents the original ID/slug mismatch.
        $this->post(route('admin.experiences.update', $experience).'?q=café&status_filter=draft', [
            '_method' => 'put', 'slug' => $experience->slug, 'title' => 'Café actualizado', 'status' => 'draft', 'regular_price' => '75.00', 'currency' => 'BOB', 'reservation_method' => 'none',
        ])->assertRedirect('/admin/experiences?q=caf%C3%A9&status_filter=draft');
        $this->assertSame('Café actualizado', $experience->fresh()->title);
        $this->assertSame('draft', $experience->fresh()->status);
        $this->assertDatabaseCount('experiences', 1);
        $this->assertSame($partner->id, $experience->fresh()->partners->sole()->id);
        $this->assertSame('DRAFT', $landing->fresh()->status);
        $this->get('/admin/experiencias/'.$experience->slug.'/landings/'.$landing->slug.'/preview')->assertOk();
        $this->get('/l/'.$landing->slug)->assertNotFound();
        $this->get('/experiencias/'.$experience->slug)->assertNotFound();
        $landing->update(['status' => 'PUBLISHED', 'default_scope' => 'ALL']);
        $this->get('/experiencias/'.$experience->slug)->assertNotFound();
        $this->get('/l/'.$landing->slug)->assertNotFound();
    }

    public function test_published_edit_and_authorization_are_preserved(): void
    {
        $experience = Experience::factory()->published()->create();
        $url = route('admin.experiences.edit', $experience);
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get($url)->assertForbidden();
        $this->put(route('admin.experiences.update', $experience), [])->assertForbidden();
        foreach (['benefits', 'experiences', 'retos'] as $path) { $this->get('/admin/'.$path)->assertForbidden(); }
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get($url)->assertOk();
        $this->put(route('admin.experiences.update', $experience), ['slug' => $experience->slug, 'title' => 'Published edited', 'status' => 'published', 'reservation_method' => 'none'])->assertRedirect('/admin/experiences');
        $this->assertDatabaseHas('experiences', ['id' => $experience->id, 'title' => 'Published edited', 'status' => 'published']);
    }

    public function test_catalog_search_filters_order_pagination_and_empty_results(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $partner = Partner::factory()->create(['name' => 'Partner buscable']);
        $older = Experience::factory()->draft()->create(['title' => 'Taller café', 'updated_at' => now()->subDay()]);
        $older->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'host'], ['partner_id' => $partner->id, 'role' => 'organizer']]);
        $newer = Experience::factory()->published()->create(['title' => 'Recorrido nuevo']);
        $this->get('/admin/experiences')->assertInertia(fn (Assert $p) => $p->where('items.data.0.id', $newer->id)->where('items.data.0.partners', [])->where('items.data.1.partners.0.name', $partner->name)->has('items.data.1.partners', 1));
        foreach (['q=Partner%20buscable', 'q=CAFÉ', 'status_filter=draft', 'partner_filter='.$partner->id] as $filter) {
            $this->get('/admin/experiences?'.$filter)->assertInertia(fn (Assert $p) => $p->has('items.data', 1)->where('items.data.0.id', $older->id));
        }
        $this->get('/admin/experiences?q=sin-coincidencias')->assertInertia(fn (Assert $p) => $p->has('items.data', 0));
        $this->get('/admin/experiences?q=%25')->assertInertia(fn (Assert $p) => $p->has('items.data', 0));
        Experience::factory()->draft()->count(21)->create();
        $this->get('/admin/experiences?status_filter=draft')->assertInertia(fn (Assert $p) => $p->has('items.data', 20)->where('items.last_page', 2)->where('items.total', 22));
        $this->get('/admin/experiences?status_filter=draft&page=2')->assertInertia(fn (Assert $p) => $p->has('items.data', 2)->where('items.current_page', 2));
    }

    public function test_benefits_and_challenges_share_catalog_and_only_offer_valid_actions(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/benefits')->assertInertia(fn (Assert $p) => $p->has('items.data', 0));
        $this->get('/admin/retos')->assertInertia(fn (Assert $p) => $p->has('items.data', 0));
        $partner = Partner::factory()->published()->create(['name' => 'Partner beneficio']);
        $benefit = Benefit::factory()->for($partner)->published()->create(['benefit_type' => 'two_for_one', 'estimated_savings' => null]);
        $this->get('/admin/benefits?q=Partner%20beneficio')->assertInertia(fn (Assert $p) => $p->where('items.data.0.value', '2 por 1')->where('items.data.0.editUrl', route('admin.benefits.edit', $benefit))->where('items.data.0.previewUrl', null)->where('items.data.0.publicUrl', '/beneficios/'.$benefit->slug));
        $benefit->update(['ends_at' => now()->subDay()]);
        $this->get('/admin/benefits')->assertInertia(fn (Assert $p) => $p->where('items.data.0.publicUrl', null));
        $challenge = Challenge::create(['title' => 'Reto borrador', 'slug' => 'reto-borrador', 'status' => 'draft', 'review_status' => 'DRAFT', 'reward_type' => 'JP', 'reward_jp_amount' => 30]);
        $this->get('/admin/retos?q=Reto&status_filter=draft')->assertInertia(fn (Assert $p) => $p->component('admin/catalog/index')->where('items.data.0.id', $challenge->id)->where('items.data.0.value', '30 JP')->where('items.data.0.partners', [])->where('items.data.0.publicUrl', null));
    }
}
