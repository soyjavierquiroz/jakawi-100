<?php

namespace App\Payments\Qr\Providers;

use App\Payments\Qr\Contracts\QrPaymentGateway;
use App\Payments\Qr\DTOs\QrPaymentCreationResult;
use App\Payments\Qr\DTOs\QrPaymentRequest;
use App\Payments\Qr\DTOs\QrPaymentStatusResult;
use App\Payments\Qr\Exceptions\UnsafeQrPaymentConfiguration;
use App\Payments\Qr\QrPaymentStatus;
use Illuminate\Support\Str;

/** Test and local-development adapter only. It holds no durable payment data. */
final class FakeQrPaymentGateway implements QrPaymentGateway
{
    /** @var array<string, QrPaymentStatus> */
    private array $statuses = [];

    public function __construct()
    {
        if (app()->isProduction()) {
            throw new UnsafeQrPaymentConfiguration('The fake QR payment gateway cannot run in production.');
        }
    }

    public function createPayment(QrPaymentRequest $request): QrPaymentCreationResult
    {
        $providerReference = 'fake-'.Str::uuid();
        $this->statuses[$providerReference] = QrPaymentStatus::PENDING;

        return new QrPaymentCreationResult($providerReference, null, null, QrPaymentStatus::PENDING);
    }

    public function checkStatus(string $providerReference): QrPaymentStatusResult
    {
        return new QrPaymentStatusResult($providerReference, $this->statuses[$providerReference] ?? QrPaymentStatus::FAILED);
    }

    public function simulateConfirmation(string $providerReference): void
    {
        $this->simulateStatus($providerReference, QrPaymentStatus::CONFIRMED);
    }

    public function simulateFailure(string $providerReference): void
    {
        $this->simulateStatus($providerReference, QrPaymentStatus::FAILED);
    }

    public function simulateExpiration(string $providerReference): void
    {
        $this->simulateStatus($providerReference, QrPaymentStatus::EXPIRED);
    }

    private function simulateStatus(string $providerReference, QrPaymentStatus $status): void
    {
        if (! array_key_exists($providerReference, $this->statuses)) {
            throw new \InvalidArgumentException('Unknown fake QR payment reference.');
        }

        $this->statuses[$providerReference] = $status;
    }
}
