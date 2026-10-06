<?php

return [
    'partner_entity_types' => ['organization', 'individual'],
    'partner_types' => ['business', 'professional', 'creator', 'organizer', 'brand', 'other'],
    'location_types' => ['branch', 'venue', 'meeting_point', 'online', 'mobile', 'other'],
    'benefit_types' => ['percentage', 'fixed_amount', 'two_for_one', 'free_item', 'upgrade', 'exclusive_access', 'other'],
    'experience_types' => ['event', 'workshop', 'class', 'tour', 'tasting', 'wellness', 'outdoor', 'cultural', 'social', 'other'],
    'reservation_methods' => ['whatsapp', 'url', 'phone', 'external', 'jakawi', 'none'],
    'experience_reservation_statuses' => ['pending', 'confirmed', 'rejected', 'cancelled'],
    'experience_session_statuses' => ['scheduled', 'cancelled'],
    'experience_partner_roles' => ['organizer', 'host', 'venue', 'sponsor', 'participant', 'creator', 'provider', 'other'],
    'publication_statuses' => ['draft', 'published', 'paused', 'archived'],
    'categories' => ['food', 'cafe', 'fitness', 'wellness', 'beauty', 'entertainment', 'nightlife', 'shopping', 'services', 'experiences'],
    'member_profile' => [
        'interests' => ['food', 'cafe', 'fitness', 'wellness', 'beauty', 'entertainment', 'nightlife', 'shopping', 'services', 'experiences'],
        'social_contexts' => ['solo', 'pareja', 'amigos', 'familia'],
        'preferred_days' => ['entre_semana', 'fin_de_semana'],
        'preferred_times' => ['manana', 'tarde', 'noche'],
        'cities' => ['Cochabamba'],
    ],
    'membership' => [
        'price_bob' => 100,
        'duration_days' => 365,
        'currency' => 'BOB',
        'product_key' => 'jakawi_annual',
    ],
    'membership_statuses' => ['active', 'cancelled'],
    'affiliate' => [
        // The database setting is authoritative once an admin has configured it.
        'minimum_payout' => env('JAKAWI_AFFILIATE_MINIMUM_PAYOUT', 0),
    ],
    'redemption' => [
        'code_ttl_minutes' => 10,
    ],
    'redemption_statuses' => ['pending', 'confirmed', 'expired', 'cancelled'],
    'analytics' => [
        'enabled' => true,
        'visitor_cookie' => env('JAKAWI_ANALYTICS_VISITOR_COOKIE', 'jakawi_visitor_id'),
        'events' => [
            'home_view', 'partner_view', 'location_view', 'benefit_view', 'experience_view',
            'opportunity_impression', 'opportunity_opened',
            'redeem_started', 'redeem_confirmed', 'experience_reserve_click', 'maps_click', 'whatsapp_click',
            'anonymous_session_created', 'referral_link_opened', 'referral_shared', 'referral_code_entered', 'signup_completed',
            'affiliate_payout_requested', 'affiliate_payout_completed',
            'reward_created', 'reward_available', 'reward_cancelled',
            'unlock_impression', 'unlock_viewed', 'unlock_interested', 'unlock_commitment_started', 'unlock_committed', 'unlock_commitment_cancelled', 'unlock_shared', 'unlock_referral_opened', 'unlock_referral_committed', 'unlock_referral_fulfilled', 'unlock_goal_reached', 'unlock_revealed', 'unlock_confirmation_requested', 'unlock_confirmed', 'unlock_confirmation_declined', 'unlock_fulfilled', 'unlock_no_show', 'unlock_jp_held', 'unlock_jp_released', 'unlock_jp_bonus_awarded', 'unlock_jp_forfeited',
            'city_viewed', 'city_interest_recorded', 'partner_application_started', 'partner_application_submitted',
            'landing_view', 'landing_cta_click',
            'program_application_submitted',
        ],
        'maps_sources' => ['location_detail', 'benefit_detail', 'experience_detail'],
    ],
];
