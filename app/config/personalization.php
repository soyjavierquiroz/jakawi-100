<?php

return [
    // Home affinity is deliberately deterministic: explicit choices lead, while
    // recent first-party activity can refine the category order.
    'weights' => [
        'explicit_interest' => 100,
        'confirmed_redemption' => 40,
        'experience_checkin' => 50,
        'confirmed_reservation' => 25,
        'recent_view' => 5,
        'featured' => 20,
        'generic_experiences_interest' => 25,
    ],
    'caps' => [
        'confirmed_redemption' => 80,
        'experience_checkin' => 100,
        'confirmed_reservation' => 50,
        'recent_view' => 20,
    ],
    'windows' => [
        'value_days' => 90,
        'view_days' => 30,
    ],
];
