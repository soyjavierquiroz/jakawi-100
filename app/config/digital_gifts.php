<?php

return [
    'enabled' => (bool) env('DIGITAL_GIFTS_ENABLED', false),
    'invitation_days' => (int) env('DIGITAL_GIFTS_INVITATION_DAYS', 14),
];
