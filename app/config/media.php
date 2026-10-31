<?php

return [
    'disk' => env('MEDIA_DISK', 'minio-media'),
    'imgproxy_url' => rtrim((string) env('IMGPROXY_URL', ''), '/'),
    'imgproxy_key' => env('IMGPROXY_KEY'),
    'imgproxy_salt' => env('IMGPROXY_SALT'),
    'bucket' => env('MEDIA_AWS_BUCKET', 'jakawi-media'),
    'max_bytes' => 10 * 1024 * 1024,
    'max_pixels' => 24_000_000,
    'social_fallback' => 'landings/1/image/00000000-0000-4000-8000-000000000001.webp',
    'presets' => [
        'social' => ['width' => 1200, 'height' => 630, 'quality' => 85, 'fit' => 'fill', 'format' => 'jpg'],
        'hero' => ['width' => 1600, 'height' => 1000, 'quality' => 82, 'fit' => 'fill'],
        'benefit_card' => ['width' => 640, 'height' => 480, 'quality' => 80, 'fit' => 'fill'],
        'experience_card' => ['width' => 720, 'height' => 480, 'quality' => 80, 'fit' => 'fill'],
        'partner_logo' => ['width' => 480, 'height' => 320, 'quality' => 82, 'fit' => 'fit'],
        'partner_cover' => ['width' => 960, 'height' => 640, 'quality' => 82, 'fit' => 'fill'],
        'partner_editorial' => ['width' => 960, 'height' => 1200, 'quality' => 82, 'fit' => 'fill'],
        'thumbnail' => ['width' => 320, 'height' => 240, 'quality' => 75, 'fit' => 'fill'],
        'avatar' => ['width' => 256, 'height' => 256, 'quality' => 80, 'fit' => 'fill'],
    ],
];
