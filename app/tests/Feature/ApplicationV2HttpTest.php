<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationV2HttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_routes_only_expose_published_records(): void
    {
        $partner = Partner::factory()->create(['status' => 'published']);
        $draftPartner = Partner::factory()->create(['status' => 'draft']);
        $location = Location::factory()->for($partner)->create(['status' => 'published']);
        $draftLocation = Location::factory()->for($partner)->create(['status' => 'paused']);
        $benefit = Benefit::factory()->for($partner)->create(['status' => 'published']);
        $draftBenefit = Benefit::factory()->for($partner)->create(['status' => 'draft']);
        $experience = Experience::factory()->create(['status' => 'published']);
        $draftExperience = Experience::factory()->create(['status' => 'draft']);

        $this->get('/')->assertOk();
        $this->get('/partners/'.$partner->slug)->assertOk()->assertDontSee('tax_id');
        $this->get('/partners/'.$draftPartner->slug)->assertNotFound();
        $this->get('/lugares/'.$location->slug)->assertOk()->assertDontSee('redemption_pin_hash');
        $this->get('/lugares/'.$draftLocation->slug)->assertNotFound();
        $this->get('/beneficios/'.$benefit->slug)->assertOk();
        $this->get('/beneficios/'.$draftBenefit->slug)->assertNotFound();
        $this->get('/experiencias/'.$experience->slug)->assertOk();
        $this->get('/experiencias/'.$draftExperience->slug)->assertNotFound();
    }

    public function test_admin_is_server_side_protected(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin')->assertOk();
    }
}
