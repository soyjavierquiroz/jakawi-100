<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
class PublicOperationsTest extends TestCase
{
    use RefreshDatabase;
    public function test_home_is_operational_and_old_flags_have_no_effect(): void
    {
        config(['jakawi.prelaunch_mode'=>true,'digital_gifts.welcome_prelaunch_enabled'=>true]);
        $this->get('/')->assertOk()->assertInertia(fn(Assert $page)=>$page->component('welcome')->has('discovery')->missing('prelaunchMode')->missing('prelaunchPublic'));
        $this->post('/membresia/checkout/aviso')->assertRedirect('/login');
        config(['club_purchases.secret'=>str_repeat('qa-only-',8)]);
        $this->postJson('/internal/club/purchases',[])->assertUnauthorized();
    }
}
