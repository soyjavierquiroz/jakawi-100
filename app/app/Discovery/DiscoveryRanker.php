<?php

namespace App\Discovery;

/**
 * Transparent V1 order: explicit category affinity, behavioural category
 * affinity, editorial priority, real urgency, then type and source id.
 * Unlocks intentionally receive neither category signal.
 */
final class DiscoveryRanker
{
    /** @param list<DiscoveryOpportunity> $opportunities @param array<string,int> $explicit @param array<string,int> $behavioral @return list<DiscoveryOpportunity> */
    public function rank(array $opportunities, array $explicit, array $behavioral): array
    {
        $now = now()->getTimestamp();
        usort($opportunities, function (DiscoveryOpportunity $left, DiscoveryOpportunity $right) use ($explicit, $behavioral, $now): int {
            $leftSignals = [$this->categoryAffinity($left, $explicit), $this->categoryAffinity($left, $behavioral), $left->editorialPriority ?? 0.0, $this->urgency($left, $now)];
            $rightSignals = [$this->categoryAffinity($right, $explicit), $this->categoryAffinity($right, $behavioral), $right->editorialPriority ?? 0.0, $this->urgency($right, $now)];
            foreach ($leftSignals as $index => $leftSignal) {
                $difference = $rightSignals[$index] <=> $leftSignal;
                if ($difference !== 0) {
                    return $difference;
                }
            }

            return $left->type->value <=> $right->type->value
                ?: (string) $left->sourceId <=> (string) $right->sourceId;
        });

        return $opportunities;
    }

    /** @param array<string,int> $explicit @param array<string,int> $behavioral */
    private function categoryAffinity(DiscoveryOpportunity $item, array $affinity): int
    {
        return $item->type === OpportunityType::UNLOCK ? 0 : max(array_map(fn (string $category): int => $affinity[$category] ?? 0, $item->categories) ?: [0]);
    }

    private function urgency(DiscoveryOpportunity $item, int $now): int
    {
        $at = $item->type === OpportunityType::EXPERIENCE ? $item->startsAt : $item->endsAt;
        if ($at === null || $at->getTimestamp() < $now) {
            return 0;
        }

        return max(0, 604800 - ($at->getTimestamp() - $now));
    }
}
