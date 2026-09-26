<?php

return [
    'qr' => [
        'enabled' => env('QR_PAYMENT_ENABLED', false),
        'driver' => env('QR_PAYMENT_DRIVER', 'disabled'),
    ],
];
