<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\RedemptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerOperationalPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_and_manager_can_prepare_or_submit_content_in_draft_or_published_partners(): void
    {
        foreach (['draft', 'published', 'paused', 'archived'] as $state) {
            $partner = Partner::factory()->create(['status' => $state]);
            foreach (['owner', 'manager', 'staff'] as $role) {
                $user = $this->actor($partner, $role);
                $allowed = $role !== 'staff' && in_array($state, ['draft', 'published'], true);
                $base = '/partner/'.$partner->slug;
                $this->actingAs($user)->get($base)->assertOk()->assertInertia(fn ($page) => $page
                    ->missing('acquisition')->missing('rewards')->missing('partnerPayout')
                    ->where('partnerAccess.can_edit_content', $allowed));
                $this->get($base.'/promociones/crear')->assertStatus($allowed ? 200 : 403);
                $payload = ['title' => 'Beneficio '.$role, 'location_scope' => 'all'];
                $this->post($base.'/promociones', $payload)->assertStatus($allowed ? 302 : 403);
                $benefit = Benefit::factory()->forPartner($partner)->create(['review_status' => 'draft']);
                $this->post($base.'/promociones/'.$benefit->slug.'/enviar')->assertStatus($allowed ? 302 : 403);
                $this->assertSame($allowed ? 'submitted' : 'draft', $benefit->fresh()->review_status);
                $this->get($base.'/experiencias/crear')->assertStatus($allowed ? 200 : 403);
                $this->post($base.'/experiencias', ['title' => 'Experiencia', 'reservation_method' => 'none'])->assertStatus($allowed ? 302 : 403);
                $this->post($base.'/desbloqueos', [])->assertStatus($allowed ? 302 : 403); // owners reach validation; staff never do
            }
        }
    }

    public function test_staff_can_validate_pin_only_while_partner_is_published(): void
    {
        foreach (['published', 'draft', 'paused', 'archived'] as $state) {
            $partner = Partner::factory()->published()->create();
            $staff = $this->actor($partner, 'staff');
            $redemption = $this->redemption($partner);
            $partner->update(['status' => $state]);
            $this->actingAs($staff)->post('/partner/'.$partner->slug.'/validar', ['code' => $redemption->code, 'pin' => '123456'])
                ->assertStatus($state === 'published' ? 200 : 403);
            $this->assertSame($state === 'published' ? 'confirmed' : 'pending', $redemption->fresh()->status);
        }
    }

    public function test_paused_and_archived_partners_cannot_confirm_reservations_or_checkins(): void
    {
        foreach (['draft', 'paused', 'archived'] as $state) {
            $partner = Partner::factory()->create(['status' => $state]);
            $user = $this->actor($partner, 'owner');
            $base = '/partner/'.$partner->slug;
            // Authorization must reject before any operation lookup or mutation.
            $this->actingAs($user)->post($base.'/reservas/not-an-id/confirmed')->assertForbidden();
            $this->post($base.'/asistencias/not-an-id')->assertForbidden();
            $this->get($base.'/validar')->assertForbidden();
        }
    }

    private function actor(Partner $partner, string $role): User
    {
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => $role]);

        return $user;
    }

    private function redemption(Partner $partner): Redemption
    {
        $location = Location::factory()->published()->withPartner($partner)->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        $member = User::factory()->create();
        app(MembershipService::class)->activate($member, User::factory()->create());

        return app(RedemptionService::class)->start($member, $benefit, $location);
    }
}
