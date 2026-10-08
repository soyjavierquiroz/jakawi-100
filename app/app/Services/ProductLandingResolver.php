<?php
namespace App\Services;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Unlock;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ProductLandingResolver
{
    public function defaultUrl(Model $subject): string
    {
        $presentation = LandingPresentation::query()->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())->where('status', 'PUBLISHED')->where('is_default', true)->first();
        return $presentation ? route('landing-presentations.show', $presentation->slug) : $this->nativeUrl($subject);
    }

    public function nativeUrl(Model $subject): string
    {
        return match (true) {
            $subject instanceof Benefit => route('benefits.show', $subject),
            $subject instanceof Experience => route('experiences.show', $subject),
            $subject instanceof Unlock => route('unlocks.show', $subject),
            $subject instanceof Challenge => route('social-challenges.show', $subject),
            default => throw new InvalidArgumentException('Unsupported landing subject.'),
        };
    }
}
