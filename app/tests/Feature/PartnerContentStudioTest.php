<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerContentStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_promotion_workflow_is_scoped_and_admin_publishes(): void
    {
        [$partner, $user, $location] = $this->partner();
        $foreign = Location::factory()->withPartner(Partner::factory()->create())->create();
        $payload = ['title' => 'Promo propia', 'location_scope' => 'selected', 'location_ids' => [$location->id], 'status' => 'published', 'review_status' => 'approved', 'featured' => true];
        $this->actingAs($user)->post('/partner/'.$partner->slug.'/promociones', $payload)->assertRedirect();
        $benefit = Benefit::firstOrFail();
        $this->assertSame('draft', $benefit->review_status); $this->assertSame('draft', $benefit->status); $this->assertFalse($benefit->featured);
        $this->actingAs($user)->put('/partner/'.$partner->slug.'/promociones/'.$benefit->slug, array_replace($payload, ['location_ids' => [$foreign->id]]))->assertStatus(422);
        $this->actingAs($user)->post('/partner/'.$partner->slug.'/promociones/'.$benefit->slug.'/enviar')->assertRedirect();
        $this->assertSame('submitted', $benefit->fresh()->review_status);
        $this->actingAs($user)->put('/partner/'.$partner->slug.'/promociones/'.$benefit->slug, $payload)->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/review/benefits/'.$benefit->id, ['action' => 'changes', 'notes' => 'Ajustar texto'])->assertRedirect();
        $this->assertSame('changes_requested', $benefit->fresh()->review_status);
        $this->actingAs($user)->post('/partner/'.$partner->slug.'/promociones/'.$benefit->slug.'/enviar')->assertRedirect();
        $this->actingAs($admin)->post('/admin/review/benefits/'.$benefit->id, ['action' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $benefit->fresh()->review_status); $this->assertSame('published', $benefit->fresh()->status);
    }

    public function test_partner_experience_sessions_are_scoped_editable_and_reviewed(): void
    {
        [$partner, $user, $location] = $this->partner();
        $foreign = Location::factory()->withPartner(Partner::factory()->create())->create();
        $payload = ['title' => 'Taller propio', 'reservation_method' => 'none', 'status' => 'published', 'review_status' => 'approved'];
        $this->actingAs($user)->post('/partner/'.$partner->slug.'/experiencias', $payload)->assertRedirect();
        $experience = Experience::firstOrFail();
        $this->assertTrue($experience->partners()->wherePivot('role', 'organizer')->whereKey($partner->id)->exists());
        $sessionPayload = ['starts_at' => now()->addDay()->format('Y-m-d H:i:s'), 'capacity' => 10, 'location_id' => $location->id, 'venue_label' => 'Local'];
        $this->actingAs($user)->post('/partner/'.$partner->slug.'/experiencias/'.$experience->slug.'/sessions', $sessionPayload)->assertRedirect();
        $session = ExperienceSession::firstOrFail(); $this->assertSame($partner->id, $session->reservation_partner_id);
        $this->actingAs($user)->put('/partner/'.$partner->slug.'/experiencias/'.$experience->slug.'/sessions/'.$session->id, array_replace($sessionPayload, ['capacity' => 12]))->assertRedirect();
        $this->assertSame(12, $session->fresh()->capacity);
        $this->actingAs($user)->put('/partner/'.$partner->slug.'/experiencias/'.$experience->slug.'/sessions/'.$session->id, array_replace($sessionPayload, ['location_id' => $foreign->id]))->assertStatus(422);
        $this->actingAs($user)->post('/partner/'.$partner->slug.'/experiencias/'.$experience->slug.'/enviar')->assertRedirect();
        $this->actingAs($user)->put('/partner/'.$partner->slug.'/experiencias/'.$experience->slug.'/sessions/'.$session->id, $sessionPayload)->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/review/experiences/'.$experience->id, ['action' => 'approve'])->assertRedirect();
        $this->assertSame('published', $experience->fresh()->status); $this->assertSame('approved', $experience->fresh()->review_status);
    }

    public function test_partner_cannot_access_other_partner_content_and_member_cannot_access_studio(): void
    {
        [$a, $user] = $this->partner(); [$b] = $this->partner();
        $benefit = Benefit::factory()->forPartner($b)->create(['review_status' => 'draft']);
        $experience = Experience::factory()->create(['review_status' => 'draft']); $experience->partners()->attach($b, ['role' => 'organizer', 'sort_order' => 0]);
        $this->actingAs($user)->get('/partner/'.$a->slug.'/promociones/'.$benefit->slug.'/editar')->assertNotFound();
        $this->actingAs($user)->get('/partner/'.$a->slug.'/experiencias/'.$experience->slug.'/editar')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/partner/'.$a->slug.'/promociones')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/review')->assertOk();
    }

    private function partner(): array
    {
        $partner = Partner::factory()->create(); $user = User::factory()->create(); $user->partners()->attach($partner, ['role' => 'manager']);
        return [$partner, $user, Location::factory()->withPartner($partner)->create()];
    }
}
