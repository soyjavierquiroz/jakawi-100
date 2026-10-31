<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\City;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_public_application_converts_once_without_implicitly_granting_ownership(): void
    {
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post('/ciudades/la-paz/partner', [
            'business_name' => 'Café Nuevo', 'contact_name' => 'Contacto', 'contact_email' => $applicant->email,
            'contact_phone' => '70000000', 'category' => 'cafe', 'message' => 'Solicitud original',
        ])->assertRedirect();
        $application = PartnerApplication::sole();
        $admin = User::factory()->create(['is_admin' => true]);
        $url = '/admin/solicitudes-partner/'.$application->id;
        $data = ['name' => 'Café Nuevo', 'slug' => 'cafe-nuevo', 'category' => 'cafe'];
        $this->actingAs($admin)->post($url.'/convertir', $data)->assertStatus(409);
        $this->assertDatabaseCount('partners', 0);
        $this->post($url.'/estado', ['status' => 'APPROVED'])->assertRedirect();
        $this->assertDatabaseCount('partners', 0);
        $this->post($url.'/convertir', $data + ['status' => 'published', 'owner_user_id' => $applicant->id])->assertRedirect();
        $partner = Partner::sole();
        $this->assertSame('draft', $partner->status);
        $this->assertSame($application->id, PartnerApplication::where('partner_id', $partner->id)->sole()->id);
        $this->assertSame($application->contact_name, $partner->contact_name);
        $this->assertSame($application->contact_email, $partner->contact_email);
        $this->assertSame($application->contact_phone, $partner->contact_phone);
        $this->assertSame('Solicitud original', $application->fresh()->message);
        $this->assertDatabaseCount('partner_user', 0);
        $this->post($url.'/convertir', $data)->assertRedirect();
        $this->assertDatabaseCount('partners', 1);
        $this->assertDatabaseCount('locations', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'partner_application_converted', 'actor_user_id' => $admin->id]);
        $this->get($url)->assertInertia(fn ($page) => $page->where('application.partner.id', $partner->id));
        $this->actingAs($applicant)->get('/partner/'.$partner->slug)->assertForbidden();
        $this->actingAs($admin)->post($url.'/owner', $this->grant($applicant))->assertRedirect();
        $this->actingAs($applicant)->get('/partner/'.$partner->slug)->assertOk();
        $this->get($url)->assertForbidden();
    }

    public function test_admin_explicitly_assigns_an_existing_owner_without_changing_identity_or_other_access(): void
    {
        $application = $this->application();
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->unverified()->create(['email' => 'authorized@example.test']);
        $password = $owner->password;
        $subject = $owner->global_subject_id;
        $url = '/admin/solicitudes-partner/'.$application->id;
        $this->actingAs($admin)->post($url.'/owner', $this->grant($owner))->assertStatus(409);
        $this->post($url.'/convertir', ['name' => $application->business_name, 'slug' => 'authorized-business'])->assertRedirect();
        $partner = $application->fresh()->partner;
        $otherOwner = User::factory()->create();
        $partner->users()->attach($otherOwner, ['role' => 'owner']);
        $this->post($url.'/owner', ['owner_user_id' => $owner->id])->assertSessionHasErrors(['authorization_confirmed', 'authorization_reason']);
        $this->assertFalse($partner->users()->whereKey($owner->id)->exists());
        $this->post($url.'/owner', $this->grant($owner))->assertRedirect();
        $this->post($url.'/owner', $this->grant($owner))->assertRedirect();
        $this->assertSame(2, $partner->users()->wherePivot('role', 'owner')->count());
        $this->assertNull($owner->fresh()->email_verified_at);
        $this->assertSame($password, $owner->fresh()->password);
        $this->assertSame($subject, $owner->fresh()->global_subject_id);
        $this->assertSame(1, AuditLog::where('action', 'partner_owner_authorized')->count());
        $this->get($url.'?owner_q=authorized')->assertInertia(fn ($page) => $page->has('accessCandidates', 1)->where('accessCandidates.0.id', $owner->id)->where('partnerOwners', fn ($owners) => collect($owners)->contains('id', $owner->id)));
    }

    public function test_matching_contact_email_or_application_account_does_not_authorize_conversion_or_owner_grant(): void
    {
        $user = User::factory()->create();
        $application = $this->application(['user_id' => $user->id, 'contact_email' => $user->email]);
        $url = '/admin/solicitudes-partner/'.$application->id;
        $this->actingAs($user)->post($url.'/convertir', ['name' => 'Business', 'slug' => 'business'])->assertForbidden();
        $this->post($url.'/owner', $this->grant($user))->assertForbidden();
        $this->assertDatabaseCount('partner_user', 0);
        $this->assertDatabaseCount('partners', 0);
    }

    public function test_invalid_reserved_or_existing_slug_cannot_replace_an_existing_partner(): void
    {
        $application = $this->application();
        $existing = Partner::factory()->create(['slug' => 'occupied']);
        $admin = User::factory()->create(['is_admin' => true]);
        $url = '/admin/solicitudes-partner/'.$application->id.'/convertir';
        foreach (['admin', 'invalid slug', 'occupied'] as $slug) {
            $this->actingAs($admin)->post($url, ['name' => 'Business', 'slug' => $slug])->assertSessionHasErrors('slug');
        }
        $this->assertDatabaseCount('partners', 1);
        $this->assertNull($application->fresh()->partner_id);
        $this->assertNotSame('Business', $existing->fresh()->name);
    }

    private function application(array $overrides = []): PartnerApplication
    {
        return PartnerApplication::create($overrides + ['city_id' => City::where('slug', 'la-paz')->sole()->id,
            'business_name' => 'Business', 'business_name_normalized' => 'business', 'contact_name' => 'Contact',
            'contact_email' => 'contact@example.test', 'status' => PartnerApplication::APPROVED]);
    }

    private function grant(User $owner): array
    {
        return ['owner_user_id' => $owner->id, 'authorization_confirmed' => true,
            'authorization_reason' => 'Autorización comprobada por el administrador con el representante.'];
    }
}
