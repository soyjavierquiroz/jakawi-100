<?php

namespace Tests\Feature\Auth;

use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\User;
use App\Services\PublicJourneyContinuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $oldSession = session()->getId();
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => ' Test@Example.com ',
            'whatsapp' => '7123 4567',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotEmpty($user->password);
        $this->assertTrue(Hash::info($user->password)['algo'] !== null);
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'whatsapp' => '+59171234567', 'profile_completed_at' => null]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_cannot_assign_administrator_privileges()
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'not-an-admin@example.com',
            'whatsapp' => '+59171234567',
            'is_admin' => true,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'not-an-admin@example.com',
            'is_admin' => false,
        ]);
    }

    public function test_supplied_password_is_ignored_and_whatsapp_can_be_shared(): void
    {
        $this->post('/register', ['name' => 'First', 'email' => 'first@example.test', 'whatsapp' => '71234567', 'password' => 'Known123456!'])->assertRedirect();
        $first = User::whereEmail('first@example.test')->firstOrFail();
        $this->assertFalse(Hash::check('Known123456!', $first->password));
        auth()->logout();

        $this->post('/register', ['name' => 'Second', 'email' => 'second@example.test', 'whatsapp' => '+591 7123-4567'])->assertRedirect();
        $this->assertSame(2, User::count());
        $this->assertSame(2, \App\Models\UserProfile::whereWhatsapp('+59171234567')->count());
    }

    public function test_existing_email_does_not_create_or_authenticate_or_record_signup_events(): void
    {
        $existing = User::factory()->create(['email' => 'already@example.test']);
        $beforeEvents = AnalyticsEvent::whereIn('event_name', ['signup_completed', 'referral_code_entered'])->count();

        $this->post('/register', ['name' => 'Other', 'email' => ' ALREADY@EXAMPLE.TEST ', 'whatsapp' => '71234567', 'referral_code' => 'CODE'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertFalse($existing->profile()->exists());
        $this->assertSame($beforeEvents, AnalyticsEvent::whereIn('event_name', ['signup_completed', 'referral_code_entered'])->count());
    }

    public function test_invalid_whatsapp_is_rejected(): void
    {
        $this->post('/register', ['name' => 'Test', 'email' => 'new@example.test', 'whatsapp' => '1234'])->assertSessionHasErrors('whatsapp');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_registration_resumes_and_consumes_a_safe_continuation(): void
    {
        $experience = Experience::factory()->create();
        $continuation = app(PublicJourneyContinuation::class);
        $continuation->set('EXPERIENCE', $experience->id, 'RESERVE');
        $this->assertSame($continuation->get(), $continuation->get());

        $this->post('/register', ['name' => 'Test', 'email' => 'new@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect(route('experiences.show', $experience->slug, false));
        $this->assertNull($continuation->get());
        $this->assertNull($continuation->consume());
    }

    public function test_intended_url_is_used_only_for_allowed_local_destinations(): void
    {
        $this->withSession(['url.intended' => '/mi-jakawi'])
            ->post('/register', ['name' => 'Test', 'email' => 'new@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect('/mi-jakawi');
    }
}
