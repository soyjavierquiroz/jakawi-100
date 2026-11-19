<?php

namespace Tests\Feature;

use App\Models\{CrmDelivery, User};
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Http, Notification, URL};
use Tests\TestCase;

class PrelaunchAccountMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode' => true]);
        Notification::fake();
        Http::preventStrayRequests();
    }

    public function test_password_recovery_and_email_verification_remain_operable_during_prelaunch(): void
    {
        $user = User::factory()->unverified()->create();
        $this->get('/forgot-password')->assertOk();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class, function ($notice) use ($user) {
            $this->post(route('password.update'), ['token' => $notice->token, 'email' => $user->email, 'password' => 'SafeRecovery-2026!', 'password_confirmation' => 'SafeRecovery-2026!'])->assertSessionHasNoErrors();
            return true;
        });
        $this->assertTrue(Hash::check('SafeRecovery-2026!', $user->fresh()->password));
        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
        $this->post(route('verification.send'))->assertRedirect();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(20), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseCount('memberships', 0);
        Http::assertNothingSent();
    }

    public function test_crm_dispatch_is_not_gated_and_only_uses_fake_http(): void
    {
        config(['crm.enabled' => true, 'crm.provider' => 'fluentcrm', 'crm.bridge_url' => 'https://crm.jakawi.com/wp-json/jakawi-fluentcrm/v1/events', 'crm.secret' => str_repeat('a', 32)]);
        $user = User::factory()->create();
        $payload = app(\App\Services\Crm\CrmContactProjectionService::class)->project($user, 'signup_completed');
        $this->assertNotNull($payload);
        $delivery = CrmDelivery::create(['provider' => 'fluentcrm', 'operation' => 'CONTACT_UPSERT', 'event_id' => $payload['event_id'], 'source_type' => User::class, 'source_id' => $user->id, 'status' => 'PENDING', 'attempts' => 0, 'payload' => $payload]);
        Http::fake(['*' => Http::response(['result_code' => 'OK', 'provider_contact_id' => 42])]);
        $this->assertSame(1, app(\App\Services\Crm\CrmDeliveryDispatcher::class)->run());
        $this->assertSame('SENT', $delivery->fresh()->status);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_noncommercial_maintenance_commands_remain_available(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['media.disk' => 'public']);
        $this->artisan('identity:prune-nonces')->assertSuccessful();
        $this->artisan('media:gc --dry-run')->assertSuccessful();
        $this->assertDatabaseCount('reward_transactions', 0);
    }
}
