<?php

namespace App\Payments\Qr\Exceptions;

use DomainException;

final class QrPaymentUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('QR payments are not available');
    }
}
