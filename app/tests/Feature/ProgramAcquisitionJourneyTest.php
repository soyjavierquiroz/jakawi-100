<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AttributionTouch;
use App\Models\ProgramApplication;
use App\Models\ProgramEnrollment;
use App\Models\User;
use App\Services\PublicJourneyContinuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class ProgramAcquisitionJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_program_resumes_registration_and_prevents_duplicate_active_applications(): void
    {
        $count = 0;
        foreach (['afiliados' => 'AFFILIATE', 'creadores' => 'CREATOR', 'promotores' => 'PROMOTER'] as $slug => $type) {
            $this->get('/'.$slug)->assertOk()->assertInertia(fn (Assert $page) => $page->component('programs/index')->where('authenticated', false)->where('canonical', route('programs.show', $slug)));
            $this->get('/'.$slug.'/aplicar')->assertRedirect('/register');
            $this->assertSame(['journey' => 'ACQUISITION', 'resource_id' => $type, 'action' => 'APPLY'], app(PublicJourneyContinuation::class)->get());
            $email = strtolower($type).'@example.test';
            $this->post('/register', ['name' => $type, 'email' => $email, 'whatsapp' => '71234567'])
                ->assertRedirect('/'.$slug.'#solicitud');
            $this->assertNull(app(PublicJourneyContinuation::class)->get());
            $this->assertDatabaseCount('program_applications', $count);
            $this->get('/'.$slug)->assertInertia(fn (Assert $page) => $page->where('authenticated', true)->where('application', null));
            $this->post('/'.$slug.'/solicitudes', ['message' => 'Quiero compartir JAKAWI'])->assertRedirect('/'.$slug);
            $application = ProgramApplication::where('user_id', auth()->id())->where('program_type', $type)->sole();
            $this->assertSame(ProgramApplication::SUBMITTED, $application->status);
            $this->post('/'.$slug.'/solicitudes', ['message' => 'Otra vez'])->assertRedirect('/'.$slug);
            $this->assertSame(1, ProgramApplication::where('user_id', auth()->id())->where('program_type', $type)->count());
            $this->get('/'.$slug)->assertInertia(fn (Assert $page) => $page->where('application.status', 'SUBMITTED'));
            $count++;
            auth()->logout();
        }
        $this->assertSame(3, AnalyticsEvent::where('event_name', 'program_application_submitted')->count());
    }

    public function test_login_continuation_and_external_destinations_are_safe(): void
    {
        $user = User::factory()->create();
        $this->get('/creadores/aplicar')->assertRedirect('/register');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/creadores#solicitud');
        $this->assertNull(app(PublicJourneyContinuation::class)->get());
        try {
            app(PublicJourneyContinuation::class)->set('ACQUISITION', 'https://evil.test', 'APPLY');
            $this->fail('External continuation was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertNull(app(PublicJourneyContinuation::class)->get());
        }
        $this->get('/creadores')->assertInertia(fn (Assert $page) => $page->where('authenticated', true)->where('application', null));
    }

    public function test_admin_filters_transitions_activates_once_and_preserves_referral_code(): void
    {
        $user = User::factory()->create(['referral_code' => 'EXISTING1', 'referral_code_normalized' => 'EXISTING1']);
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $this->actingAs($user)->post('/afiliados/solicitudes', [])->assertRedirect();
        $application = ProgramApplication::sole();
        $this->actingAs($other)->get('/admin/solicitudes-programas/'.$application->id)->assertForbidden();
        $this->actingAs($other)->post('/admin/solicitudes-programas/'.$application->id.'/estado', ['status' => 'APPROVED'])->assertForbidden();
        $this->actingAs($admin)->get('/admin/solicitudes-programas?program=AFFILIATE&status=SUBMITTED')
            ->assertInertia(fn (Assert $page) => $page->component('admin/program-applications/index')->has('applications.data', 1));
        $this->get('/admin/solicitudes-programas?program=CREATOR')->assertInertia(fn (Assert $page) => $page->has('applications.data', 0));
        $this->get('/admin/solicitudes-programas/'.$application->id)->assertOk();
        $this->post('/admin/solicitudes-programas/'.$application->id.'/estado', ['status' => 'CONTACTED'])->assertRedirect();
        $this->post('/admin/solicitudes-programas/'.$application->id.'/estado', ['status' => 'QUALIFIED'])->assertRedirect();
        $this->post('/admin/solicitudes-programas/'.$application->id.'/estado', ['status' => 'APPROVED'])->assertRedirect();
        $this->post('/admin/solicitudes-programas/'.$application->id.'/estado', ['status' => 'APPROVED'])->assertRedirect();
        $this->assertSame(1, ProgramEnrollment::where('user_id', $user->id)->where('program_type', 'AFFILIATE')->count());
        $this->assertTrue($user->fresh()->hasActiveProgram('AFFILIATE'));
        $this->assertSame('EXISTING1', $user->fresh()->referral_code);
        $this->actingAs($user)->get('/afiliados')->assertInertia(fn (Assert $page) => $page->where('active', true));
    }

    public function test_rejection_allows_reapplication_and_existing_enrollment_is_reused(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $enrollment = $user->programEnrollments()->create(['program_type' => 'PROMOTER', 'status' => 'inactive']);
        $this->actingAs($user)->post('/promotores/solicitudes', [])->assertRedirect();
        $first = ProgramApplication::sole();
        $this->actingAs($admin)->post('/admin/solicitudes-programas/'.$first->id.'/estado', ['status' => 'REJECTED'])->assertRedirect();
        $this->actingAs($user)->post('/promotores/solicitudes', [])->assertRedirect();
        $this->assertSame(2, ProgramApplication::count());
        $second = ProgramApplication::latest('id')->firstOrFail();
        $this->actingAs($admin)->post('/admin/solicitudes-programas/'.$second->id.'/estado', ['status' => 'APPROVED'])->assertRedirect();
        $this->assertSame(1, ProgramEnrollment::where('user_id', $user->id)->where('program_type', 'PROMOTER')->count());
        $this->assertSame($enrollment->id, ProgramEnrollment::sole()->id);
    }

    public function test_attribution_snapshot_and_analytics_do_not_include_personal_contact_data(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'SHARE123', 'referral_code_normalized' => 'SHARE123']);
        $this->get('/afiliados?utm_source=radio&campaign=lanzamiento&ref=SHARE123')->assertOk();
        $this->get('/afiliados/aplicar')->assertRedirect('/register');
        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.test', 'whatsapp' => '71234567'])->assertRedirect('/afiliados#solicitud');
        $this->post('/afiliados/solicitudes', ['message' => 'Me interesa'])->assertRedirect();
        $application = ProgramApplication::sole();
        $snapshot = $application->attribution_snapshot;
        $this->assertSame('radio', $snapshot['first']['utm_source']);
        $this->assertSame('lanzamiento', $snapshot['conversion']['utm_campaign']);
        $this->assertSame('SHARE123', $snapshot['conversion']['referral_code']);
        $this->assertSame('/afiliados', $snapshot['conversion']['landing_page']);
        $this->assertNotNull($application->attribution_touch_id);
        $event = AnalyticsEvent::where('event_name', 'program_application_submitted')->sole();
        $this->assertEquals(['program' => 'AFFILIATE', 'landing' => 'afiliados'], $event->metadata);
        $this->assertStringNotContainsString('new@example.test', json_encode($event->metadata));
        $this->assertStringNotContainsString('71234567', json_encode($event->metadata));
    }

    public function test_first_and_conversion_touches_are_preserved_separately(): void
    {
        $user = User::factory()->create();
        $visitor = 'cecc2e8f-2a74-4c33-b92a-4ce5a913c4bc';
        AttributionTouch::create(['user_id' => User::factory()->create()->id, 'anonymous_id' => $visitor, 'utm_source' => 'otra-cuenta', 'landing_page' => '/partners', 'occurred_at' => now()->subDay()]);
        $this->actingAs($user)->withUnencryptedCookie('jakawi_visitor_id', $visitor)->get('/afiliados?utm_source=radio')->assertOk();
        $this->get('/creadores?utm_source=instagram&utm_campaign=octubre')->assertOk();
        $this->post('/creadores/solicitudes', [])->assertRedirect();
        $snapshot = ProgramApplication::sole()->attribution_snapshot;
        $this->assertSame('radio', $snapshot['first']['utm_source']);
        $this->assertSame('/afiliados', $snapshot['first']['landing_page']);
        $this->assertSame('instagram', $snapshot['conversion']['utm_source']);
        $this->assertSame('octubre', $snapshot['conversion']['utm_campaign']);
        $this->assertSame('/creadores', $snapshot['conversion']['landing_page']);
    }

    public function test_active_enrollment_never_accepts_a_public_application(): void
    {
        $user = User::factory()->create();
        $user->programEnrollments()->create(['program_type' => 'CREATOR', 'status' => 'active']);
        $this->actingAs($user)->post('/creadores/solicitudes', [])->assertRedirect('/creadores');
        $this->assertDatabaseCount('program_applications', 0);
    }

    public function test_guest_cannot_submit_and_program_type_is_allowlisted(): void
    {
        $this->post('/afiliados/solicitudes', [])->assertRedirect('/login');
        $this->post('/otros/solicitudes', [])->assertNotFound();
        $this->get('/otros')->assertNotFound();
        $this->assertDatabaseCount('program_applications', 0);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/afiliados/solicitudes', ['program_type' => 'PROMOTER'])->assertRedirect();
        $this->assertSame('AFFILIATE', ProgramApplication::sole()->program_type);
    }
}
