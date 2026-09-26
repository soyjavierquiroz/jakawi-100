<?php

namespace App\Payments\Qr\DTOs;

use App\Payments\Qr\QrPaymentStatus;

final readonly class QrPaymentStatusResult
{
    public function __construct(
        public string $providerReference,
        public QrPaymentStatus $status,
    ) {}
}
