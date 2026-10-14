<?php

namespace Tests\Feature;

use App\Models\{Experience, Partner, User};
use App\Services\{LandingAiBriefBuilder, ExperienceMarketingLandingPresenter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TocTocExperienceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_commercial_draft_has_no_public_action_or_membership_gate(): void
    {
        $data = json_decode(file_get_contents(database_path('catalog/toctoc-experience/product.json')), true, flags: JSON_THROW_ON_ERROR);
        $partner = Partner::factory()->published()->create(['slug' => 'toctoccafe', 'name' => 'Toc Toc Café Gourmet']);
        $experience = Experience::create($data);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
        $landing = $experience->landingPresentations()->create(['name' => 'Toc Toc', 'slug' => $experience->slug, 'status' => 'DRAFT']);
        $copy = app(ExperienceMarketingLandingPresenter::class)->present($experience, $landing, Request::create('/'));
        $this->assertSame('unavailable', $copy['action']['kind']);
        $this->assertSame('NO DISPONIBLE', $copy['action']['label']);
        $this->assertSame(0, $copy['sessionCount']);
        $this->assertSame('75.00', $experience->regular_price);
        $this->assertNull($experience->member_price);
        $this->assertNull($experience->duration_minutes);
        $this->assertSame('approved', $experience->review_status);
        $this->assertSame('none', $experience->reservation_method);
        $this->assertStringContainsString('Sin membresía JAKAWI', $experience->terms);
        $brief = app(LandingAiBriefBuilder::class)->build($experience, $copy);
        $this->assertSame('jakawi-product-brief-v1', $brief['brief_version']);
        $this->assertSame('Experience', $brief['domain']);
        $this->assertSame('informational', $brief['commercial_truth']['reservation_mode']);
        $this->assertSame(0, $brief['commercial_truth']['available_sessions']);
        $this->assertSame([$experience->terms], $brief['commercial_truth']['conditions']);
        $this->get('/l/'.$landing->slug)->assertNotFound();
        $this->get('/experiencias/'.$experience->slug)->assertNotFound();
        $this->get('/explorar?type=experiences')->assertInertia(fn (Assert $p) => $p->has('opportunities', 0));
        $user = User::factory()->create();
        $this->actingAs($user)->get('/experiencias/'.$experience->slug)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/admin/experiencias/'.$experience->slug.'/landings/'.$landing->slug.'/publish')->assertSessionHasErrors('presentation');
        $this->assertDatabaseHas('landing_presentations', ['id' => $landing->id, 'status' => 'DRAFT']);
        $this->assertDatabaseCount('experience_sessions', 0);
        $this->assertDatabaseCount('experience_reservations', 0);
    }
}
