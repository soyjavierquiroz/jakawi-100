<?php

namespace App\Payments\Qr;

enum QrPaymentStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
}
