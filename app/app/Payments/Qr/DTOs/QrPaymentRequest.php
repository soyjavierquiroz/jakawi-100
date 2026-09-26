<?php

namespace App\Payments\Qr\DTOs;

final readonly class QrPaymentRequest
{
    public function __construct(
        public string $purchaseReference,
        public string $amount,
        public string $currency,
        public ?string $description = null,
    ) {}
}
