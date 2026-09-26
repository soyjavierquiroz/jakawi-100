<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use App\Payments\Qr\Contracts\QrPaymentGateway;
use App\Payments\Qr\DTOs\QrPaymentRequest;
use App\Payments\Qr\Exceptions\QrPaymentUnavailable;
use App\Payments\Qr\Exceptions\UnsafeQrPaymentConfiguration;
use App\Payments\Qr\Providers\DisabledQrPaymentGateway;
use App\Payments\Qr\Providers\FakeQrPaymentGateway;
use App\Payments\Qr\QrPaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_gateway_creates_a_pending_payment_and_can_simulate_terminal_statuses(): void
    {
        config()->set('payments.qr.driver', 'fake');
        $gateway = app(QrPaymentGateway::class);

        $this->assertInstanceOf(FakeQrPaymentGateway::class, $gateway);
        $payment = $gateway->createPayment(new QrPaymentRequest('MS-TEST-1', '120.00', 'BOB', 'Membresía JAKAWI'));
        $this->assertSame(QrPaymentStatus::PENDING, $payment->status);

        $gateway->simulateConfirmation($payment->providerReference);
        $this->assertSame(QrPaymentStatus::CONFIRMED, $gateway->checkStatus($payment->providerReference)->status);

        $failed = $gateway->createPayment(new QrPaymentRequest('MS-TEST-2', '120.00', 'BOB'));
        $gateway->simulateFailure($failed->providerReference);
        $this->assertSame(QrPaymentStatus::FAILED, $gateway->checkStatus($failed->providerReference)->status);

        $expired = $gateway->createPayment(new QrPaymentRequest('MS-TEST-3', '120.00', 'BOB'));
        $gateway->simulateExpiration($expired->providerReference);
        $this->assertSame(QrPaymentStatus::EXPIRED, $gateway->checkStatus($expired->providerReference)->status);
    }

    public function test_disabled_gateway_returns_a_controlled_unavailable_error(): void
    {
        config()->set('payments.qr.driver', 'disabled');
        $gateway = app(QrPaymentGateway::class);

        $this->assertInstanceOf(DisabledQrPaymentGateway::class, $gateway);
        $this->expectException(QrPaymentUnavailable::class);
        $this->expectExceptionMessage('QR payments are not available');
        $gateway->createPayment(new QrPaymentRequest('MS-TEST-1', '120.00', 'BOB'));
    }

    public function test_fake_gateway_cannot_be_resolved_in_production(): void
    {
        config()->set('payments.qr.driver', 'fake');
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->expectException(UnsafeQrPaymentConfiguration::class);
        app(QrPaymentGateway::class);
    }

    public function test_a_regular_client_cannot_activate_a_membership_through_the_admin_endpoint(): void
    {
        $client = User::factory()->create();
        $beneficiary = User::factory()->create();

        $this->actingAs($client)->post('/admin/memberships', ['user_id' => $beneficiary->id])->assertForbidden();
        $this->assertSame(0, Membership::count());
    }
}
