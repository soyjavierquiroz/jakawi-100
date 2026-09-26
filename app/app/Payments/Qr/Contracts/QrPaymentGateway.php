<?php

namespace App\Payments\Qr\Contracts;

use App\Payments\Qr\DTOs\QrPaymentCreationResult;
use App\Payments\Qr\DTOs\QrPaymentRequest;
use App\Payments\Qr\DTOs\QrPaymentStatusResult;

interface QrPaymentGateway
{
    public function createPayment(QrPaymentRequest $request): QrPaymentCreationResult;

    public function checkStatus(string $providerReference): QrPaymentStatusResult;
}
