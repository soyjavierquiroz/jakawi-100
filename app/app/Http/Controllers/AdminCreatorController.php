<?php

namespace App\Http\Controllers;

use App\Models\ProgramEnrollment;

class AdminCreatorController extends AdminAffiliateController
{
    protected function programType(): string { return ProgramEnrollment::TYPE_CREATOR; }
    protected function programSlug(): string { return 'creators'; }
}
