<?php

namespace App\Enums;

enum AcquisitionProvider: string
{
    case NONE = 'NONE';
    case META = 'META';
    case TIKTOK = 'TIKTOK';
    case GOOGLE = 'GOOGLE';
}
