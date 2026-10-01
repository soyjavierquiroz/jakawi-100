<?php

namespace Tests\Unit;

use App\Discovery\DiscoveryCity;
use App\Discovery\DiscoveryOpportunity;
use App\Discovery\DiscoveryRanker;
use App\Discovery\OpportunityType;
use Tests\TestCase;

class DiscoveryRankerTest extends TestCase
{
    public function test_category_affinity_applies_to_benefits_and_experiences_but_not_unlocks(): void
    {
        $city = new DiscoveryCity(1, 'Cochabamba', 'cochabamba');
        $benefit = new DiscoveryOpportunity(OpportunityType::BENEFIT, 3, 'Benefit', '/', $city, categories: ['food']);
        $experience = new DiscoveryOpportunity(OpportunityType::EXPERIENCE, 2, 'Experience', '/', $city, categories: ['food']);
        $unlock = new DiscoveryOpportunity(OpportunityType::UNLOCK, 1, 'Unlock', '/', $city, editorialPriority: 10.0);

        $ranked = app(DiscoveryRanker::class)->rank([$unlock, $experience, $benefit], ['food' => 100], []);

        $this->assertSame([OpportunityType::BENEFIT, OpportunityType::EXPERIENCE, OpportunityType::UNLOCK], array_map(fn ($item) => $item->type, $ranked));
    }

    public function test_ties_are_deterministic_by_type_then_source_id(): void
    {
        $city = new DiscoveryCity(1, 'Cochabamba', 'cochabamba');
        $items = [
            new DiscoveryOpportunity(OpportunityType::UNLOCK, 9, 'U', '/', $city),
            new DiscoveryOpportunity(OpportunityType::BENEFIT, 2, 'B', '/', $city),
            new DiscoveryOpportunity(OpportunityType::BENEFIT, 1, 'B', '/', $city),
        ];

        $ranked = app(DiscoveryRanker::class)->rank($items, [], []);

        $this->assertSame([1, 2, 9], array_map(fn ($item) => $item->sourceId, $ranked));
    }
}
