<?php

return [
    'partner_entity_types' => ['organization', 'individual'],
    'partner_types' => ['business', 'professional', 'creator', 'organizer', 'brand', 'other'],
    'location_types' => ['branch', 'venue', 'meeting_point', 'online', 'mobile', 'other'],
    'benefit_types' => ['percentage', 'fixed_amount', 'two_for_one', 'free_item', 'upgrade', 'exclusive_access', 'other'],
    'experience_types' => ['event', 'workshop', 'class', 'tour', 'tasting', 'wellness', 'outdoor', 'cultural', 'social', 'other'],
    'reservation_methods' => ['whatsapp', 'url', 'phone', 'external', 'none'],
    'experience_session_statuses' => ['scheduled', 'cancelled'],
    'experience_partner_roles' => ['organizer', 'host', 'venue', 'sponsor', 'participant', 'creator', 'provider', 'other'],
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
    'analytics' => [
        'enabled' => true,
        'visitor_cookie' => env('JAKAWI_ANALYTICS_VISITOR_COOKIE', 'jakawi_visitor_id'),
        'events' => [
            'home_view', 'partner_view', 'location_view', 'benefit_view', 'experience_view',
            'redeem_started', 'redeem_confirmed', 'experience_reserve_click', 'maps_click', 'whatsapp_click',
        ],
        'maps_sources' => ['location_detail', 'benefit_detail', 'experience_detail'],
    ],
];
