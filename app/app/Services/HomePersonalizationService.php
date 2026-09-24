<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Support\Collection;

class HomePersonalizationService
{
    /**
     * Only consumer accounts with explicit interests are eligible. This deliberately
     * does not inspect views, redemptions, reservations, or any other behaviour.
     *
     * @return array<int, string>
     */
    public function interestsFor(?User $user): array
    {
        if ($user === null || $user->isPartnerOnly()) {
            return [];
        }

        $interests = $user->profile()->value('interests') ?? [];

        return array_values(array_intersect(
            config('jakawi.categories'),
            array_filter($interests, 'is_string'),
        ));
    }

    /** @param Collection<int, Benefit> $benefits @param array<int, string> $interests @return Collection<int, Benefit> */
    public function rankBenefits(Collection $benefits, array $interests): Collection
    {
        return $benefits->sort($this->benefitComparator($interests))->values();
    }

    /** @param Collection<int, Experience> $experiences @param array<int, string> $interests @return Collection<int, Experience> */
    public function rankExperiences(Collection $experiences, array $interests): Collection
    {
        return $experiences->sort($this->experienceComparator($interests))->values();
    }

    /** @param array<int, string> $interests */
    private function benefitComparator(array $interests): callable
    {
        return function (Benefit $left, Benefit $right) use ($interests): int {
            $score = fn (Benefit $benefit): int => (in_array($benefit->category, $interests, true) ? 100 : 0)
                + ($benefit->featured ? 20 : 0);

            return $this->compare(
                $score($left),
                $score($right),
                $left->sort_order,
                $right->sort_order,
                $left->id,
                $right->id,
            );
        };
    }

    /** @param array<int, string> $interests */
    private function experienceComparator(array $interests): callable
    {
        return function (Experience $left, Experience $right) use ($interests): int {
            $score = fn (Experience $experience): int => (in_array($experience->category, array_diff($interests, ['experiences']), true) ? 100 : 0)
                + (in_array('experiences', $interests, true) ? 25 : 0)
                + ($experience->featured ? 20 : 0);

            $scoreDifference = $score($right) <=> $score($left);

            return $scoreDifference
                ?: ($left->sort_order ?? 0) <=> ($right->sort_order ?? 0)
                ?: ($left->sessions->first()?->starts_at?->getTimestamp() ?? PHP_INT_MAX) <=> ($right->sessions->first()?->starts_at?->getTimestamp() ?? PHP_INT_MAX)
                ?: ($right->published_at?->getTimestamp() ?? 0) <=> ($left->published_at?->getTimestamp() ?? 0)
                ?: $left->id <=> $right->id;
        };
    }

    private function compare(int $leftScore, int $rightScore, ?int $leftOrder, ?int $rightOrder, int $leftId, int $rightId): int
    {
        return $rightScore <=> $leftScore
            ?: ($leftOrder ?? 0) <=> ($rightOrder ?? 0)
            ?: $leftId <=> $rightId;
    }
}
