<?php
namespace App\Integrations\ShareContest;
use App\Models\ChallengeSocialEntry;
interface Inspector { public function inspect(ChallengeSocialEntry $entry): InspectionResult; }
