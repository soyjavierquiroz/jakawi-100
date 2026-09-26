<?php

namespace App\Payments\Qr\Providers;

use App\Payments\Qr\Contracts\QrPaymentGateway;
use App\Payments\Qr\DTOs\QrPaymentCreationResult;
use App\Payments\Qr\DTOs\QrPaymentRequest;
use App\Payments\Qr\DTOs\QrPaymentStatusResult;
use App\Payments\Qr\Exceptions\QrPaymentUnavailable;

final class DisabledQrPaymentGateway implements QrPaymentGateway
{
    public function createPayment(QrPaymentRequest $request): QrPaymentCreationResult
    {
        throw new QrPaymentUnavailable;
    }

    public function checkStatus(string $providerReference): QrPaymentStatusResult
    {
        throw new QrPaymentUnavailable;
    }
}
