<?php

return [
    'membership' => [
        'price_bob' => 100,
        'duration_days' => 365,
    ],
    'redemption' => [
        'code_ttl_minutes' => 10,
    ],
    'demo_catalog' => [
        'images' => [
            'disk' => 'public',
            'font_family' => 'Arial, Helvetica, sans-serif',
            'dimensions' => [
                'merchant_logo' => [512, 512],
                'merchant_cover' => [1200, 630],
                'benefit' => [1200, 900],
            ],
            'palettes' => [
                ['background' => '#F5E8D7', 'primary' => '#D65A4A', 'accent' => '#2B5D73', 'text' => '#173042'],
                ['background' => '#E2F0E9', 'primary' => '#287A62', 'accent' => '#F2B84B', 'text' => '#163B36'],
                ['background' => '#E8E7F7', 'primary' => '#6657B8', 'accent' => '#F07B5C', 'text' => '#29264A'],
                ['background' => '#E1F0F4', 'primary' => '#23758A', 'accent' => '#F0A65A', 'text' => '#173B4A'],
                ['background' => '#F8E5EC', 'primary' => '#B54B74', 'accent' => '#6D78B8', 'text' => '#44243A'],
            ],
            'category_labels' => [
                'coffee' => 'Café',
                'food' => 'Gastronomía',
                'wellness' => 'Bienestar',
                'sport' => 'Movimiento',
                'experience' => 'Experiencias',
                'bakery' => 'Panadería',
                'dessert' => 'Dulces',
            ],
        ],
    ],
];
