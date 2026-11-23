<?php

return [
    'welcome_prelaunch_enabled' => (bool) env('DIGITAL_WELCOME_GIFTS_PRELAUNCH_ENABLED', false),
    'enabled' => (bool) env('DIGITAL_GIFTS_ENABLED', false),
    'invitation_days' => (int) env('DIGITAL_GIFTS_INVITATION_DAYS', 14),
];
