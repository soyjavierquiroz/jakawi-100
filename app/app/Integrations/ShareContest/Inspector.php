<?php
namespace App\Integrations\ShareContest;
use App\Models\SocialChallengeParticipation;
interface Inspector { public function inspect(SocialChallengeParticipation $participation): InspectionResult; }
