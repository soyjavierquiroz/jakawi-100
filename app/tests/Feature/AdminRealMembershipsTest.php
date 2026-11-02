<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Membership, MembershipBankPayment, MembershipPurchase, Partner, User};
use App\Services\{MembershipService, MembershipPurchaseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminRealMembershipsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['is_admin' => true]); }
    private function membership(User $admin, ?User $user = null): Membership { return app(MembershipService::class)->activate($user ?? User::factory()->create(), $admin); }

    public function test_listing_contains_memberships_including_partner_holder_and_all_lifecycle_states_only(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Sin membresía']);
        $partner = Partner::create(['name' => 'Local', 'slug' => 'local', 'status' => 'published']);
        $operator = User::factory()->create(['name' => 'Operador sin membresía']);
        $operator->partners()->attach($partner, ['role' => 'owner']);
        $holder = User::factory()->create(['name' => 'Titular Partner']);
        $holder->partners()->attach($partner, ['role' => 'owner']);
        $active = $this->membership($admin, $holder);
        $expired = $this->membership($admin); $expired->update(['starts_at' => now()->subYears(2), 'ends_at' => now()->subDay()]);
        $cancelled = $this->membership($admin); $cancelled->update(['status' => 'cancelled']);
        $scheduled = $this->membership($admin); $scheduled->update(['starts_at' => now()->addDay(), 'ends_at' => now()->addYear()]);
        $this->actingAs($admin)->get('/admin/memberships')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/memberships/index')->has('memberships.data', 4)->where('summary.active', 1)->where('summary.expired', 1)->where('summary.cancelled', 1)->where('summary.scheduled', 1)->missing('users')->missing('paymentMethods'));
        foreach (['active' => $active, 'expired' => $expired, 'cancelled' => $cancelled, 'scheduled' => $scheduled] as $state => $m) {
            $this->get('/admin/memberships?status='.$state)->assertInertia(fn (Assert $p) => $p->has('memberships.data', 1)->where('memberships.data.0.id', $m->id)->where('memberships.data.0.status', $state));
        }
        $this->get('/admin/memberships?q=Titular')->assertInertia(fn (Assert $p) => $p->has('memberships.data', 1)->where('memberships.data.0.identity', 'Titular con vínculo Partner'));
        $this->get('/admin/memberships?q=Sin%20membresía')->assertInertia(fn (Assert $p) => $p->has('memberships.data', 0));
        $this->get('/admin/memberships?q=%25')->assertInertia(fn (Assert $p) => $p->has('memberships.data', 0));
        $this->get('/admin/memberships?status=invalid')->assertSessionHasErrors('status');
    }

    public function test_confirmed_bank_and_cash_links_and_unconfirmed_historical_amount_are_honest(): void
    {
        $admin = $this->admin(); $bank = $this->membership($admin);
        $payment = MembershipBankPayment::create(['reference' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $bank->user_id, 'membership_id' => $bank->id, 'status' => 'approved', 'amount' => 100, 'origin' => 'COM']);
        $sale = app(MembershipPurchaseService::class)->confirmManualCash(User::factory()->create(), $admin, null, 'REC-TEST', (string) \Illuminate\Support\Str::uuid());
        $legacy = $this->membership($admin);
        $this->actingAs($admin)->get('/admin/memberships?origin=bank')->assertInertia(fn (Assert $p) => $p->has('memberships.data', 1)->where('memberships.data.0.confirmed_amount', '100.00')->where('memberships.data.0.payments.0.href', '/admin/conciliacion-membresias?pago='.$payment->id));
        $this->get('/admin/memberships?origin=cash')->assertInertia(fn (Assert $p) => $p->has('memberships.data', 1)->where('memberships.data.0.origin', 'Venta en efectivo'));
        $this->get('/admin/memberships/'.$sale->membership_id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('membership.payments.0.href', '/admin/sales/'.$sale->id));
        $this->get('/admin/memberships/'.$legacy->id)->assertInertia(fn (Assert $p) => $p->where('membership.confirmed_amount', null)->where('membership.recorded_amount', '100.00')->has('membership.payments', 0));
        $payment->update(['status' => 'rejected']);
        $this->get('/admin/memberships/'.$bank->id)->assertInertia(fn (Assert $p) => $p->where('membership.confirmed_amount', null));
        $sale->update(['status' => 'refunded']);
        $this->get('/admin/memberships/'.$sale->membership_id)->assertInertia(fn (Assert $p) => $p->where('membership.confirmed_amount', null)->has('membership.payments', 1));
    }

    public function test_custody_is_not_presented_as_a_real_buyer(): void
    {
        $admin = $this->admin(); $m = $this->membership($admin, User::factory()->create(['name' => 'Compra CLUB test', 'email' => 'custody@purchases.invalid']));
        $this->actingAs($admin)->get('/admin/memberships/'.$m->id)->assertInertia(fn (Assert $p) => $p->where('membership.holder', 'Identidad pendiente de vinculación')->where('membership.email', null));
    }

    public function test_exception_requires_confirmation_reason_password_and_is_audited_without_a_purchase(): void
    {
        $admin = $this->admin(); $user = User::factory()->create();
        $data = ['user_id' => $user->id, 'amount_paid' => 0, 'payment_method' => 'other', 'payment_reference' => 'AUT-EX-1', 'reason' => 'Cortesía excepcional autorizada', 'confirm' => true, 'password' => 'password'];
        $this->actingAs($admin)->post('/admin/memberships', ['user_id' => $user->id])->assertSessionHasErrors(['reason', 'confirm', 'password', 'amount_paid', 'payment_reference']);
        $this->post('/admin/memberships', array_replace($data, ['password' => 'incorrecta']))->assertSessionHasErrors('password');
        $this->post('/admin/memberships', array_replace($data, ['confirm' => false]))->assertSessionHasErrors('confirm');
        $this->assertSame(0, Membership::count());
        $this->post('/admin/memberships', $data)->assertRedirect('/admin/memberships');
        $m = Membership::sole();
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership_exception_activated', 'subject_id' => $m->id, 'actor_user_id' => $admin->id]);
        $this->assertSame('Cortesía excepcional autorizada', AuditLog::sole()->metadata['reason']);
        $this->assertSame(0, MembershipPurchase::count()); $this->assertSame(0, MembershipBankPayment::count());
        $this->post('/admin/memberships', $data)->assertSessionHasErrors('user_id');
        $this->assertSame(1, Membership::count()); $this->assertSame(1, AuditLog::count());
        $this->delete('/admin/memberships/'.$m->id)->assertSessionHasErrors(['reason', 'confirm', 'password']);
        $this->delete('/admin/memberships/'.$m->id, ['reason' => 'Cancelación excepcional autorizada', 'confirm' => true, 'password' => 'password'])->assertRedirect();
        $this->assertSame('cancelled', $m->fresh()->status);
        $this->assertSame(2, AuditLog::count());
        $this->delete('/admin/memberships/'.$m->id, ['reason' => 'Cancelación excepcional autorizada', 'confirm' => true, 'password' => 'password'])->assertStatus(422);
    }

    public function test_guest_and_non_admin_cannot_read_or_operate_memberships(): void
    {
        $m = $this->membership($this->admin());
        foreach (['/admin/memberships', '/admin/memberships/'.$m->id] as $path) $this->get($path)->assertRedirect('/login');
        $this->post('/admin/memberships')->assertRedirect('/login');
        $this->delete('/admin/memberships/'.$m->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        foreach (['/admin/memberships', '/admin/memberships/'.$m->id] as $path) $this->get($path)->assertForbidden();
        $this->post('/admin/memberships')->assertForbidden();
        $this->delete('/admin/memberships/'.$m->id)->assertForbidden();
    }
}
