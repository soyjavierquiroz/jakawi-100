<?php

return [
    'partner_entity_types' => ['organization', 'individual'],
    'partner_types' => ['business', 'professional', 'creator', 'organizer', 'brand', 'other'],
    'location_types' => ['branch', 'venue', 'meeting_point', 'online', 'mobile', 'other'],
    'benefit_types' => ['percentage', 'fixed_amount', 'two_for_one', 'free_item', 'upgrade', 'exclusive_access', 'other'],
    'publication_statuses' => ['draft', 'published', 'paused', 'archived'],
    'categories' => ['food', 'cafe', 'fitness', 'wellness', 'beauty', 'entertainment', 'nightlife', 'shopping', 'services', 'experiences'],
    'membership' => [
        'price_bob' => 100,
        'duration_days' => 365,
    ],
    'membership_statuses' => ['active', 'cancelled'],
    'redemption' => [
        'code_ttl_minutes' => 10,
    ],
    'redemption_statuses' => ['pending', 'confirmed', 'expired', 'cancelled'],
];
