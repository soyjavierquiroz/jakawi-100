<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Support\Collection;

class HomePersonalizationService
{
    public function __construct(private readonly MemberAffinityService $affinity) {}

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

        return array_values(array_intersect(
            config('jakawi.categories'),
            array_filter($user->profile()->value('interests') ?? [], 'is_string'),
        ));
    }

    /** @param Collection<int, Benefit> $benefits @param array<int, string> $interests @return Collection<int, Benefit> */
    public function rankBenefits(Collection $benefits, array $affinities): Collection
    {
        return $benefits->sort($this->benefitComparator($affinities))->values();
    }

    /** @param Collection<int, Experience> $experiences @param array<int, string> $interests @return Collection<int, Experience> */
    public function rankExperiences(Collection $experiences, array $affinities, bool $hasGenericExperiencesInterest = false): Collection
    {
        return $experiences->sort($this->experienceComparator($affinities, $hasGenericExperiencesInterest))->values();
    }

    /** @param array<int, string> $interests */
    private function benefitComparator(array $affinities): callable
    {
        return function (Benefit $left, Benefit $right) use ($affinities): int {
            $score = fn (Benefit $benefit): int => ($affinities[$benefit->category] ?? 0)
                + ($benefit->featured ? config('personalization.weights.featured') : 0);

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
    private function experienceComparator(array $affinities, bool $hasGenericExperiencesInterest): callable
    {
        return function (Experience $left, Experience $right) use ($affinities, $hasGenericExperiencesInterest): int {
            $score = fn (Experience $experience): int => ($affinities[$experience->category] ?? 0)
                + ($hasGenericExperiencesInterest ? config('personalization.weights.generic_experiences_interest') : 0)
                + ($experience->featured ? config('personalization.weights.featured') : 0);

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
