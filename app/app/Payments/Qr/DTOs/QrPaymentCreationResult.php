<?php

namespace App\Payments\Qr\DTOs;

use App\Payments\Qr\QrPaymentStatus;
use Carbon\CarbonInterface;

final readonly class QrPaymentCreationResult
{
    /**
     * paymentData is opaque provider data. A future adapter defines how it is
     * presented; this boundary neither renders nor persists a visual QR.
     */
    public function __construct(
        public string $providerReference,
        public ?string $paymentData,
        public ?CarbonInterface $expiresAt,
        public QrPaymentStatus $status,
    ) {}
}
