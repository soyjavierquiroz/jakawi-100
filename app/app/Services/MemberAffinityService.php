<?php

namespace App\Services;

use App\Models\ExperienceReservation;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MemberAffinityService
{
    /** @return array<string, int> */
    public function explicitCategoryAffinity(?User $user): array
    {
        if (! $this->isEligible($user)) {
            return [];
        }

        $interests = $user->profile()->value('interests') ?? [];
        $weight = config('personalization.weights.explicit_interest');

        return collect($interests)
            // `experiences` is a generic Home experience preference, not a
            // category match. HomePersonalizationService applies its +25 boost.
            ->filter(fn ($interest) => is_string($interest) && $interest !== 'experiences' && in_array($interest, config('jakawi.categories'), true))
            ->unique()
            ->mapWithKeys(fn (string $interest) => [$interest => $weight])
            ->all();
    }

    /** @return array<string, int> */
    public function behavioralCategoryAffinity(?User $user): array
    {
        if (! $this->isEligible($user)) {
            return [];
        }

        $valueSince = now()->subDays(config('personalization.windows.value_days'));
        $viewSince = now()->subDays(config('personalization.windows.view_days'));
        $scores = [];

        $this->addCappedCounts($scores, DB::table('redemptions')
            ->join('benefits', 'benefits.id', '=', 'redemptions.benefit_id')
            ->where('redemptions.user_id', $user->id)
            ->where('redemptions.status', Redemption::STATUS_CONFIRMED)
            ->where('redemptions.confirmed_at', '>=', $valueSince)
            ->selectRaw('benefits.category, count(*) as total')->groupBy('benefits.category')->pluck('total', 'category')->all(), 'confirmed_redemption');

        // A check-in is the strongest experience signal. Confirmed reservations
        // with a check-in are intentionally excluded from the weaker signal.
        $this->addCappedCounts($scores, DB::table('experience_reservations')
            ->join('experiences', 'experiences.id', '=', 'experience_reservations.experience_id')
            ->where('experience_reservations.user_id', $user->id)
            ->whereNotNull('experience_reservations.checked_in_at')
            ->where('experience_reservations.checked_in_at', '>=', $valueSince)
            ->selectRaw('experiences.category, count(*) as total')->groupBy('experiences.category')->pluck('total', 'category')->all(), 'experience_checkin');

        $this->addCappedCounts($scores, DB::table('experience_reservations')
            ->join('experiences', 'experiences.id', '=', 'experience_reservations.experience_id')
            ->where('experience_reservations.user_id', $user->id)
            ->where('experience_reservations.status', ExperienceReservation::STATUS_CONFIRMED)
            ->whereNull('experience_reservations.checked_in_at')
            ->where('experience_reservations.responded_at', '>=', $valueSince)
            ->selectRaw('experiences.category, count(*) as total')->groupBy('experiences.category')->pluck('total', 'category')->all(), 'confirmed_reservation');

        $views = DB::query()->fromSub(
            DB::table('analytics_events')->join('benefits', 'benefits.id', '=', 'analytics_events.benefit_id')
                ->where('analytics_events.user_id', $user->id)->where('analytics_events.event_name', 'benefit_view')->where('analytics_events.occurred_at', '>=', $viewSince)
                ->selectRaw('benefits.category, count(*) as total')->groupBy('benefits.category')
                ->unionAll(DB::table('analytics_events')->join('experiences', 'experiences.id', '=', 'analytics_events.experience_id')
                    ->where('analytics_events.user_id', $user->id)->where('analytics_events.event_name', 'experience_view')->where('analytics_events.occurred_at', '>=', $viewSince)
                    ->selectRaw('experiences.category, count(*) as total')->groupBy('experiences.category')),
            'views',
        )->selectRaw('category, sum(total) as total')->groupBy('category')->pluck('total', 'category')->all();
        $this->addCappedCounts($scores, $views, 'recent_view');

        return $scores;
    }

    /** @return array<string, int> */
    public function combinedCategoryAffinity(?User $user, ?array $behavioralAffinity = null): array
    {
        $scores = $this->explicitCategoryAffinity($user);

        foreach ($behavioralAffinity ?? $this->behavioralCategoryAffinity($user) as $category => $score) {
            $scores[$category] = ($scores[$category] ?? 0) + $score;
        }

        return $scores;
    }

    /** @param array<string, int> $scores @param array<string, int|numeric-string> $counts */
    private function addCappedCounts(array &$scores, array $counts, string $signal): void
    {
        $weight = config("personalization.weights.{$signal}");
        $cap = config("personalization.caps.{$signal}");

        foreach ($counts as $category => $count) {
            if (! in_array($category, config('jakawi.categories'), true)) {
                continue;
            }

            $scores[$category] = ($scores[$category] ?? 0) + min((int) $count * $weight, $cap);
        }
    }

    private function isEligible(?User $user): bool
    {
        return $user !== null && ! $user->isPartnerOnly();
    }
}
