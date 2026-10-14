<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenefitDraftPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_native_and_published_landing_never_expose_commercial_copy(): void
    {
        $partner = Partner::factory()->published()->create();
        $benefit = Benefit::factory()->draft()->forPartner($partner)->create();
        $landing = $benefit->landingPresentations()->create([
            'name' => 'Private proposal', 'slug' => 'private-proposal',
            'status' => 'PUBLISHED', 'default_scope' => 'ALL',
        ]);

        foreach ([null, User::factory()->create(), User::factory()->create(['is_admin' => true])] as $viewer) {
            if ($viewer) {
                $this->actingAs($viewer);
            }
            $this->get(route('benefits.show', $benefit))->assertNotFound();
            $this->get('/l/'.$landing->slug)->assertNotFound();
        }
        $this->assertDatabaseCount('attribution_touches', 0);
    }

    public function test_draft_admin_preview_survives_and_guests_cannot_enter(): void
    {
        $partner = Partner::factory()->create();
        $benefit = Benefit::factory()->draft()->forPartner($partner)->create();
        $landing = $benefit->landingPresentations()->create([
            'name' => 'Private proposal', 'slug' => 'private-proposal',
            'status' => 'DRAFT', 'default_scope' => 'NONE',
        ]);
        $url = '/admin/beneficios/'.$benefit->slug.'/landings/'.$landing->slug.'/preview';
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get($url)->assertOk();
    }
}
