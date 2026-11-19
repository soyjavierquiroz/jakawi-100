<?php

namespace Tests\Feature;

use App\Models\{Benefit, Experience, Location, Partner, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerEditorialExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode' => true]);
    }

    public function test_summary_and_filters_separate_publication_from_review_and_scope_both_products(): void
    {
        $partner = Partner::factory()->published()->create();
        $other = Partner::factory()->create();
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => 'owner']);
        foreach ([$partner, $other] as $business) {
            foreach (['draft', 'submitted', 'changes_requested', 'approved', 'rejected'] as $review) {
                Benefit::factory()->forPartner($business)->create(['status' => 'draft', 'review_status' => $review]);
                $experience = Experience::factory()->create(['status' => 'draft', 'review_status' => $review]);
                $experience->partners()->attach($business, ['role' => 'organizer', 'sort_order' => 0]);
            }
            // Historical/inconsistent review metadata must not make public content a draft.
            Benefit::factory()->forPartner($business)->create(['status' => 'published', 'review_status' => 'draft', 'ends_at' => now()->subDay()]);
            $published = Experience::factory()->create(['status' => 'published', 'review_status' => 'changes_requested']);
            $published->partners()->attach($business, ['role' => 'organizer', 'sort_order' => 0]);
            Benefit::factory()->forPartner($business)->create(['status' => 'paused', 'review_status' => 'draft']);
        }
        $before = Benefit::orderBy('id')->get()->toJson();
        $base = '/partner/'.$partner->slug;
        $this->actingAs($user)->get($base)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('prelaunchMode', true)
            ->where('editorialSummary.benefits', ['published' => 1, 'draft' => 1, 'submitted' => 1, 'changes_requested' => 1, 'rejected' => 1, 'approved' => 1])
            ->where('editorialSummary.experiences', ['published' => 1, 'draft' => 1, 'submitted' => 1, 'changes_requested' => 1, 'rejected' => 1, 'approved' => 1]));
        foreach (['promociones', 'experiencias'] as $type) {
            foreach (['draft', 'submitted', 'changes_requested', 'approved', 'rejected', 'published'] as $state) {
                $this->get($base.'/'.$type.'?estado='.$state)->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->where('state', $state)->has('items.data', 1)
                    ->where('items.data.0.status', $state === 'published' ? 'published' : 'draft'));
            }
        }
        $this->assertSame($before, Benefit::orderBy('id')->get()->toJson());
    }

    public function test_role_and_status_capabilities_stay_unchanged_and_multi_partner_context_is_scoped(): void
    {
        $partner = Partner::factory()->published()->create();
        $other = Partner::factory()->create();
        foreach (['owner', 'manager', 'staff'] as $role) {
            $user = User::factory()->create();
            $user->partners()->attach($partner, ['role' => $role]);
            $user->partners()->attach($other, ['role' => 'staff']);
            $canEdit = $role !== 'staff';
            $this->actingAs($user)->get('/partner')->assertOk()->assertInertia(fn (Assert $page) => $page->has('partners', 2));
            $base = '/partner/'.$partner->slug;
            $this->get($base)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('partnerAccess.can_edit_content', $canEdit)->where('partnerAccess.can_manage', $canEdit));
            foreach (['perfil', 'promociones/crear', 'experiencias/crear'] as $path) {
                $this->get($base.'/'.$path)->assertStatus($canEdit ? 200 : 403);
            }
            $this->get('/partner/'.$other->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('partnerAccess.can_edit_content', false));
            $foreign = Partner::factory()->create();
            $this->get('/partner/'.$foreign->slug)->assertForbidden();
        }
        $manager = User::factory()->create();
        $manager->partners()->attach($partner, ['role' => 'manager']);
        $partner->update(['status' => 'paused']);
        $this->actingAs($manager)->get('/partner/'.$partner->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('partnerAccess.can_edit_content', false)->where('partnerAccess.can_manage', true));
        $this->get('/partner/'.$partner->slug.'/perfil')->assertForbidden();
    }

    public function test_prelaunch_allows_editorial_save_and_submit_without_publishing_or_changing_permissions(): void
    {
        $partner = Partner::factory()->create();
        $manager = User::factory()->create();
        $manager->partners()->attach($partner, ['role' => 'manager', 'economic_access' => false]);
        $before = $manager->partners()->first()->pivot->getAttributes();
        $base = '/partner/'.$partner->slug;
        $this->actingAs($manager)->post($base.'/promociones', ['title' => 'Menú editorial', 'location_scope' => 'all'])->assertRedirect();
        $benefit = Benefit::where('partner_id', $partner->id)->firstOrFail();
        $this->post($base.'/promociones/'.$benefit->slug.'/enviar')->assertRedirect();
        $this->assertSame('submitted', $benefit->fresh()->review_status);
        $this->assertSame('draft', $benefit->fresh()->status);
        $this->post($base.'/experiencias', ['title' => 'Taller editorial', 'reservation_method' => 'none'])->assertRedirect();
        $experience = Experience::where('title', 'Taller editorial')->firstOrFail();
        $this->put($base.'/experiencias/'.$experience->slug, ['title' => 'Taller corregido', 'reservation_method' => 'none'])->assertRedirect();
        $this->post($base.'/experiencias/'.$experience->slug.'/enviar')->assertRedirect();
        $this->assertSame('submitted', $experience->fresh()->review_status);
        $this->assertSame('draft', $experience->fresh()->status);
        $this->assertSame($before, $manager->partners()->first()->pivot->getAttributes());
    }

    public function test_published_and_foreign_content_and_locations_keep_existing_protection(): void
    {
        $partner = Partner::factory()->published()->create();
        $foreign = Partner::factory()->create();
        $owner = User::factory()->create();
        $owner->partners()->attach($partner, ['role' => 'owner']);
        $benefit = Benefit::factory()->forPartner($partner)->create(['status' => 'published', 'review_status' => 'approved']);
        $experience = Experience::factory()->create(['status' => 'published', 'review_status' => 'approved']);
        $experience->partners()->attach($partner, ['role' => 'organizer', 'sort_order' => 0]);
        $base = '/partner/'.$partner->slug;
        $before = $benefit->fresh()->getAttributes();
        $this->actingAs($owner)->put($base.'/promociones/'.$benefit->slug, ['title' => 'No permitido', 'location_scope' => 'all'])->assertForbidden();
        $this->post($base.'/promociones/'.$benefit->slug.'/enviar')->assertForbidden();
        $this->put($base.'/experiencias/'.$experience->slug, ['title' => 'No permitido', 'reservation_method' => 'none'])->assertForbidden();
        $this->assertSame($before, $benefit->fresh()->getAttributes());
        $location = Location::factory()->withPartner($foreign)->create();
        $this->get($base.'/sedes/'.$location->id.'/editar')->assertNotFound();
        $foreignBenefit = Benefit::factory()->forPartner($foreign)->create();
        $this->get($base.'/promociones/'.$foreignBenefit->slug.'/editar')->assertNotFound();
    }
}
