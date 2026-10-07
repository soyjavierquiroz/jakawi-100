<?php

namespace App\Discovery;

enum OpportunityType: string
{
    case BENEFIT = 'BENEFIT';
    case EXPERIENCE = 'EXPERIENCE';
    case UNLOCK = 'UNLOCK';
    case CHALLENGE = 'CHALLENGE';
}
