<?php

namespace App\Services\Meta;

class MetaEventMapper
{
    public const EVENTS = [
        'landing_view' => ['name' => 'ViewContent', 'standard' => true],
        'landing_cta_click' => ['name' => 'JakawiLandingCTA', 'standard' => false],
        'signup_completed' => ['name' => 'CompleteRegistration', 'standard' => true],
        'membership_purchase_requested' => ['name' => 'Lead', 'standard' => true],
        'membership_activated' => ['name' => 'JakawiMembershipActivated', 'standard' => false],
        'benefit_redeemed' => ['name' => 'JakawiBenefitRedeemed', 'standard' => false],
        'experience_reserved' => ['name' => 'JakawiExperienceReserved', 'standard' => false],
        'challenge_joined' => ['name' => 'JakawiChallengeJoined', 'standard' => false],
        'unlock_committed' => ['name' => 'JakawiUnlockCommitted', 'standard' => false],
    ];

    public function map(string $event): ?array
    {
        return self::EVENTS[$event] ?? null;
    }
}
