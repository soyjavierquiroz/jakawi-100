<?php

// Publish landings and destinations explicitly here. The examples exist only in tests.
$testing = env('APP_ENV') === 'testing';

return [
    'landings' => $testing ? [
        'test-public-journey' => [
            'key' => 'test-public-journey', 'path' => '/test-public-journey', 'status' => 'active',
            'template' => 'marketing', 'title' => 'Test public journey', 'description' => 'Test landing only.',
            'blocks' => [], 'primary_cta' => ['type' => 'internal', 'label' => 'Continue', 'path' => '/register'],
            'campaign_key' => 'test-journey', 'seo' => ['title' => 'Test public journey', 'description' => 'Test landing only.'],
        ],
        'test-inactive-journey' => [
            'key' => 'test-inactive-journey', 'path' => '/test-inactive-journey', 'status' => 'inactive',
            'template' => 'marketing', 'title' => 'Inactive test', 'description' => 'Not public.',
            'blocks' => [], 'primary_cta' => ['type' => 'internal', 'label' => 'Continue', 'path' => '/register'],
            'campaign_key' => 'test-inactive', 'seo' => [],
        ],
        'test-external-journey' => [
            'key' => 'test-external-journey', 'path' => '/test-external-journey', 'status' => 'active',
            'template' => 'marketing', 'title' => 'External test', 'description' => 'Test landing only.',
            'blocks' => [], 'primary_cta' => ['type' => 'external', 'label' => 'Continue', 'slug' => 'test-website'],
            'campaign_key' => 'test-external', 'seo' => [],
        ],
    ] : [],

    'redirects' => $testing ? [
        'test-website' => [
            'slug' => 'test-website', 'status' => 'active', 'destination_type' => 'url',
            'destination' => 'https://example.org/tickets', 'campaign_key' => 'test-external',
            'landing' => 'test-external-journey',
        ],
    ] : [],
];
