<?php

namespace Tests\Feature;

use App\Discovery\DiscoveryContext;
use App\Discovery\DiscoveryService;
use App\Discovery\OpportunityType;
use App\Models\City;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use App\Services\ExperiencePricing;
use App\Services\ExperiencePublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExperienceV2Test extends TestCase
{
    use RefreshDatabase;

    private function proposal(array $fields = []): array
    {
        $city = City::factory()->create();
        $partner = Partner::factory()->published()->create();
        Location::factory()->published()->for($partner)->create(['city_id' => $city->id]);
        $experience = Experience::factory()->published()->create($fields + ['catalog_visibility' => 'upcoming', 'regular_price' => 75, 'member_price' => 60, 'duration_minutes' => 90, 'reservation_method' => 'whatsapp']);
        $experience->partners()->attach($partner, ['role' => 'organizer']);

        return [$experience, $partner, $city];
    }

    public function test_upcoming_without_sessions_is_discoverable_and_has_a_real_native_destination(): void
    {
        [$experience, $partner, $city] = $this->proposal();
        $experience->landingPresentations()->create(['slug' => $experience->slug, 'name' => 'Draft', 'status' => 'DRAFT']);
        $items = app(DiscoveryService::class)->explore(new DiscoveryContext(city: $city), OpportunityType::EXPERIENCE, 'experiences', $partner->name);
        $this->assertCount(1, $items);
        $this->assertSame('upcoming', $items[0]->availability->state);
        $this->assertFalse($items[0]->availability->isAvailable);
        $this->assertNull($items[0]->startsAt);
        $this->assertSame(route('experiences.show', $experience), $items[0]->destinationUrl);
        $this->get($items[0]->destinationUrl)->assertOk()->assertInertia(fn (Assert $p) => $p->where('availability.available', false)->has('experience.sessions', 0)->has('experience.reservation_targets', 0));
        $this->get('/'.$partner->slug)->assertOk()->assertInertia(fn (Assert $p) => $p->has('experiences', 1));
        $this->get('/l/'.$experience->slug)->assertNotFound();
        $this->get('/experiencias/'.$experience->slug.'/reservar')->assertNotFound();
        $this->assertDatabaseCount('experience_sessions', 0);
    }

    public function test_published_landing_can_present_an_upcoming_experience_without_operational_actions(): void
    {
        [$experience] = $this->proposal();
        $landing = $experience->landingPresentations()->create(['slug' => 'public-proposal', 'name' => 'Proposal', 'status' => 'PUBLISHED', 'default_scope' => 'ALL']);
        $this->get('/l/'.$landing->slug)->assertOk()->assertInertia(fn (Assert $p) => $p->where('copy.action.kind', 'unavailable')->where('copy.action.label', 'EN PREPARACIÓN')->where('copy.prices.1.label', 'Precio preferencial previsto · activación y verificación pendientes')->where('landingDirectAction', null));
        $this->get('/experiencias/'.$experience->slug)->assertRedirect('/l/'.$landing->slug);
        $this->actingAs(User::factory()->create())->get('/l/'.$landing->slug.'/activar')->assertNotFound();
    }

    public function test_admin_can_publish_an_informational_landing_only_after_authorizing_visibility(): void
    {
        [$experience] = $this->proposal(['catalog_visibility' => 'hidden']);
        $landing = $experience->landingPresentations()->create(['slug' => 'present-commercial', 'name' => 'Proposal', 'status' => 'DRAFT', 'publish_scope' => 'ALL']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/admin/experiencias/'.$experience->slug.'/landings/'.$landing->slug.'/publish')->assertSessionHasErrors('presentation');
        $this->assertSame('DRAFT', $landing->fresh()->status);
        $experience->update(['catalog_visibility' => 'upcoming']);
        $this->post('/admin/experiencias/'.$experience->slug.'/landings/'.$landing->slug.'/publish')->assertSessionHasNoErrors();
        $this->assertSame('PUBLISHED', $landing->fresh()->status);
        $this->get('/l/'.$landing->slug)->assertOk()->assertInertia(fn (Assert $p) => $p->where('copy.action.kind', 'unavailable'));
        $this->assertDatabaseCount('experience_sessions', 0);
    }

    public function test_hidden_draft_paused_and_archived_do_not_leak_even_through_published_landings(): void
    {
        foreach ([['status' => 'draft'], ['catalog_visibility' => 'hidden'], ['status' => 'paused'], ['status' => 'archived']] as $fields) {
            [$experience, $partner, $city] = $this->proposal($fields);
            $landing = $experience->landingPresentations()->create(['slug' => $experience->slug, 'name' => 'Hidden', 'status' => 'PUBLISHED', 'default_scope' => 'ALL']);
            $this->get('/experiencias/'.$experience->slug)->assertNotFound();
            $this->get('/l/'.$landing->slug)->assertNotFound();
            $this->assertSame([], app(DiscoveryService::class)->explore(new DiscoveryContext(city: $city), OpportunityType::EXPERIENCE));
        }
    }

    public function test_sessions_do_not_accidentally_activate_an_upcoming_proposal(): void
    {
        [$experience, $partner] = $this->proposal(['reservation_method' => 'jakawi', 'operations_confirmed' => true]);
        $session = ExperienceSession::factory()->for($experience)->upcoming()->create(['reservation_partner_id' => $partner->id]);
        $member = User::factory()->create();
        $member->memberships()->create(['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'amount_paid' => 0]);
        $this->actingAs($member)->post('/experiencias/'.$experience->slug.'/reservas', ['experience_session_id' => $session->id])->assertSessionHasErrors('session');
        $this->get('/experiencias/'.$experience->slug)->assertInertia(fn (Assert $p) => $p->has('experience.sessions', 0)->where('availability.available', false));
        $this->assertDatabaseCount('experience_reservations', 0);
    }

    public function test_external_activation_requires_confirmation_channel_and_real_sessions_and_blocks_member_discount(): void
    {
        [$experience, $partner] = $this->proposal(['catalog_visibility' => 'active']);
        $policy = app(ExperiencePublication::class);
        $this->assertFalse($policy->canOperate($experience));
        ExperienceSession::factory()->for($experience)->upcoming()->create(['venue_label' => 'Sede confirmada']);
        $experience->update(['operations_confirmed' => true, 'reservation_whatsapp' => '+59170000000']);
        $this->assertFalse($policy->canOperate($experience));
        $this->assertStringContainsString('no verifica membresía', implode(' ', $policy->operationalBlockers($experience)));
        $experience->update(['member_price' => null]);
        $this->assertTrue($policy->canOperate($experience));
        $this->get('/experiencias/'.$experience->slug.'/reservar')->assertRedirect('https://wa.me/59170000000');
        $this->get('/experiencias/'.$experience->slug.'/reservar?method=url')->assertNotFound();
        $experience->update(['reservation_whatsapp' => null]);
        $this->get('/experiencias/'.$experience->slug.'/reservar')->assertNotFound();
    }

    public function test_prices_are_optional_and_membership_eligibility_is_real_for_internal_display(): void
    {
        [$experience] = $this->proposal(['reservation_method' => 'jakawi']);
        $pricing = app(ExperiencePricing::class);
        $guest = $pricing->present($experience);
        $this->assertFalse($guest[1]['memberAdvantage']);
        $user = User::factory()->create();
        $this->assertFalse($pricing->present($experience, $user)[1]['memberAdvantage']);
        $membership = $user->memberships()->create(['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'amount_paid' => 0]);
        $this->assertTrue($pricing->present($experience, $user)[1]['memberAdvantage']);
        $membership->update(['ends_at' => now()->subMinute()]);
        $this->assertFalse($pricing->present($experience, $user)[1]['memberAdvantage']);
        $membership->update(['ends_at' => now()->addDay(), 'status' => 'cancelled']);
        $this->assertFalse($pricing->present($experience, $user)[1]['memberAdvantage']);
        $experience->update(['member_price' => null]);
        $this->assertCount(1, $pricing->present($experience, $user));
        $experience->update(['regular_price' => null]);
        $this->assertSame([], $pricing->present($experience, $user));
    }

    public function test_admin_can_save_pending_fields_and_preserve_values_and_relationships(): void
    {
        [$experience, $partner] = $this->proposal(['catalog_visibility' => 'legacy', 'terms' => 'Precio JAKAWI: Bs 60 por persona. Sin membresía JAKAWI.']);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/experiences/'.$experience->slug.'/edit')->assertInertia(fn (Assert $p) => $p->component('admin/experiences/form')->has('publication.warnings', 1));
        $data = $experience->only(['title', 'slug', 'status', 'reservation_method', 'regular_price', 'member_price', 'duration_minutes', 'currency', 'terms']);
        $this->put('/admin/experiences/'.$experience->slug, $data + ['catalog_visibility' => 'upcoming'])->assertSessionHasNoErrors()->assertRedirect(route('admin.experiences.edit', $experience));
        $experience->refresh();
        $this->assertSame('75.00', $experience->regular_price);
        $this->assertSame('60.00', $experience->member_price);
        $this->assertSame(90, $experience->duration_minutes);
        $this->assertNull($experience->reservation_whatsapp);
        $this->assertSame($data['terms'], $experience->terms);
        $this->assertSame([$partner->id], $experience->partners()->pluck('partners.id')->all());
        $this->assertDatabaseCount('experience_sessions', 0);
        $this->put('/admin/experiences/'.$experience->slug, $data + ['catalog_visibility' => 'active', 'operations_confirmed' => true])->assertSessionHasErrors('catalog_visibility');
        $this->assertSame('upcoming', $experience->fresh()->catalog_visibility);
    }

    public function test_admin_can_create_without_knowing_slugs_and_duplicate_titles_get_distinct_links(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $data = ['title' => 'Nuevo plan de café', 'status' => 'draft', 'reservation_method' => 'none', 'slug' => ''];
        $this->post('/admin/experiences', $data)->assertSessionHasNoErrors();
        $this->post('/admin/experiences', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('experiences', ['slug' => 'nuevo-plan-de-cafe', 'catalog_visibility' => 'hidden']);
        $this->assertDatabaseHas('experiences', ['slug' => 'nuevo-plan-de-cafe-2', 'catalog_visibility' => 'hidden']);
    }

    public function test_invalid_prices_return_errors_and_optional_member_price_can_be_blank(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $base = ['title' => 'New experience', 'slug' => 'new-experience', 'status' => 'draft', 'currency' => 'BOB', 'reservation_method' => 'none'];
        $this->actingAs($admin)->post('/admin/experiences', $base + ['regular_price' => 75, 'member_price' => 80])->assertSessionHasErrors('member_price');
        $this->post('/admin/experiences', $base + ['member_price' => 60])->assertSessionHasErrors('regular_price');
        $this->post('/admin/experiences', $base + ['regular_price' => 75, 'member_price' => ''])->assertSessionHasNoErrors();
        $created = Experience::where('slug', 'new-experience')->sole();
        $this->assertNull($created->member_price);
        $this->assertSame('hidden', $created->catalog_visibility);
        $this->get('/experiencias/'.$created->slug)->assertNotFound();
    }

    public function test_explicit_member_price_copy_is_coherent_but_a_general_member_amount_is_flagged(): void
    {
        [$experience] = $this->proposal(['terms' => "Sin membresía: Bs 75.\nCon membresía: Bs 60."]);
        $this->assertSame([], app(ExperiencePublication::class)->copyWarnings($experience));
        $experience->terms = 'Precio JAKAWI: Bs 60 por persona. Sin membresía JAKAWI.';
        $this->assertCount(1, app(ExperiencePublication::class)->copyWarnings($experience));
        $this->assertSame('75.00', $experience->regular_price);
    }

    public function test_additive_migration_and_rollback_preserve_existing_commercial_data(): void
    {
        [$experience] = $this->proposal();
        $before = $experience->only(['id', 'title', 'regular_price', 'member_price', 'duration_minutes', 'description', 'terms', 'status', 'updated_at']);
        $draft = Experience::factory()->draft()->create();
        $migration = require database_path('migrations/2026_10_14_000002_add_experience_catalog_controls.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('experiences', 'catalog_visibility'));
        $migration->up();
        $this->assertEquals($before, $experience->fresh()->only(array_keys($before)));
        $this->assertSame('legacy', $experience->fresh()->catalog_visibility);
        $this->assertFalse($experience->fresh()->operations_confirmed);
        $this->assertSame('hidden', $draft->fresh()->catalog_visibility);
        $this->assertDatabaseCount('experience_sessions', 0);
    }

    public function test_admin_preview_is_private_and_works_without_a_landing_or_sessions(): void
    {
        [$experience] = $this->proposal(['status' => 'draft', 'catalog_visibility' => 'hidden']);
        $url = '/admin/experiences/'.$experience->slug.'/preview';
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->where('availability.available', false)->where('experience.regular_price', '75.00'));
    }

    public function test_proposals_follow_city_category_and_search_filters_without_invented_locations(): void
    {
        [$experience, $partner, $city] = $this->proposal();
        $discovery = app(DiscoveryService::class);
        $this->assertCount(1, $discovery->explore(new DiscoveryContext(city: $city), OpportunityType::EXPERIENCE, term: $experience->title));
        $this->assertSame([], $discovery->explore(new DiscoveryContext(city: $city), OpportunityType::EXPERIENCE, 'cafe'));
        $this->assertSame([], $discovery->explore(new DiscoveryContext(city: City::factory()->create()), OpportunityType::EXPERIENCE));
        $partner->update(['status' => 'draft']);
        $this->assertSame([], $discovery->explore(new DiscoveryContext(city: $city), OpportunityType::EXPERIENCE));
        $this->get('/experiencias/'.$experience->slug)->assertNotFound();
    }
}
