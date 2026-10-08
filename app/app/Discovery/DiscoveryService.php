<?php

namespace App\Discovery;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Unlock;
use App\Models\Challenge;
use App\Services\MemberAffinityService;
use App\Services\ProductLandingResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only aggregation boundary for Discovery. Candidate queries are capped
 * at 24/domain by default: enough headroom for a three-item diversity window
 * in the pilot catalog without reading complete domain tables.
 */
final readonly class DiscoveryService
{
    public function __construct(
        private BenefitDiscoveryQuery $benefits,
        private ExperienceDiscoveryQuery $experiences,
        private UnlockDiscoveryQuery $unlocks,
        private BenefitOpportunityAdapter $benefitAdapter,
        private ExperienceOpportunityAdapter $experienceAdapter,
        private UnlockOpportunityAdapter $unlockAdapter,
        private ChallengeOpportunityAdapter $challengeAdapter,
        private ProductLandingResolver $landingResolver,
        private UnlockProgressLoader $progress,
        private MemberAffinityService $affinity,
        private DiscoveryRanker $ranker,
    ) {}

    public function discover(DiscoveryContext $context): DiscoveryResult
    {
        $city = new DiscoveryCity($context->city->id, $context->city->name, $context->city->slug);
        $limit = max(1, $context->candidateLimitPerDomain);

        /** @var \Illuminate\Support\Collection<int, Benefit> $benefits */
        $benefits = $this->benefits->forCity($context->city)->limit($limit)->get();
        /** @var \Illuminate\Support\Collection<int, Experience> $experiences */
        $experiences = $this->experiences->forCity($context->city)->limit($limit)->get();
        /** @var \Illuminate\Support\Collection<int, Unlock> $unlocks */
        $unlocks = $this->unlocks->forCity($context->city)->limit($limit)->get();

        $progress = $this->progress->forUnlocks(
            $unlocks->pluck('id')->all(),
            $unlocks->mapWithKeys(fn (Unlock $unlock): array => [$unlock->id => $unlock->minimum_commitments])->all(),
        );

        $items = [];
        foreach ($benefits as $benefit) {
            $location = $benefit->applies_to_all_locations
                ? $benefit->partner->locations->first()
                : $benefit->locations->first();
            $items[] = $this->benefitAdapter->adapt($benefit, $city, $benefit->partner, $location, $this->landingResolver->defaultUrl($benefit));
        }
        foreach ($experiences as $experience) {
            $session = $experience->sessions->sortBy('starts_at')->first();
            if ($session !== null && $session->location !== null) {
                $items[] = $this->experienceAdapter->adapt($experience, $city, $session, $session->location, $experience->partners->first(), $this->landingResolver->defaultUrl($experience));
            }
        }
        foreach ($unlocks as $unlock) {
            $items[] = $this->unlockAdapter->adapt($unlock, $city, $progress[$unlock->id], $unlock->partner, $unlock->locations->first(), $this->landingResolver->defaultUrl($unlock));
        }
        foreach ($this->challengesForCity($context->city)->with(['partner:id,name,slug','benefit:id,title'])->limit($limit)->get() as $challenge) {
            $items[] = $this->challengeAdapter->adapt($challenge,$city,$this->landingResolver->defaultUrl($challenge));
        }

        // Canonical identity intentionally retains linked Unlock/Benefit or
        // Unlock/Experience pairs because their opportunity types differ.
        $items = array_values(collect($items)->unique(fn (DiscoveryOpportunity $item): string => $item->type->value.'|'.$item->sourceId)->all());
        $ranked = $this->ranker->rank(
            $items,
            $this->affinity->explicitCategoryAffinity($context->user),
            $this->affinity->behavioralCategoryAffinity($context->user),
        );
        $ranked = $this->diversify($ranked);

        $hero = array_shift($ranked);
        $happeningNow = [];
        $remaining = [];
        foreach ($ranked as $item) {
            if (count($happeningNow) < $context->happeningNowLimit && $this->isHappeningNow($item)) {
                $happeningNow[] = $item;
            } else {
                $remaining[] = $item;
            }
        }
        $forYou = array_splice($remaining, 0, max(0, $context->forYouLimit));

        return new DiscoveryResult($hero, $forYou, $happeningNow, array_slice($remaining, 0, max(0, $context->discoverMoreLimit)));
    }

    /**
     * Bounded, flat Discovery output for Explore. Filters are applied to the
     * source-domain predicates before adapting, so a category never becomes a
     * synthetic property of an Unlock.
     *
     * @return list<DiscoveryOpportunity>
     */
    public function explore(DiscoveryContext $context, ?OpportunityType $type = null, ?string $category = null, ?string $term = null, int $limit = 24): array
    {
        $city = new DiscoveryCity($context->city->id, $context->city->name, $context->city->slug);
        $limit = max(1, $limit);
        $like = $term !== null && $term !== '' ? '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%' : null;
        $items = [];

        if ($type === null || $type === OpportunityType::BENEFIT) {
            $benefits = $this->benefits->forCity($context->city);
            if ($category !== null) {
                $benefits->where('category', $category);
            }
            if ($like !== null) {
                $benefits->where(fn (Builder $query) => $query->where('title', 'ilike', $like)->orWhereHas('partner', fn (Builder $partner) => $partner->where('name', 'ilike', $like)));
            }
            foreach ($benefits->limit($limit)->get() as $benefit) {
                $location = $benefit->applies_to_all_locations ? $benefit->partner->locations->first() : $benefit->locations->first();
                $items[] = $this->benefitAdapter->adapt($benefit, $city, $benefit->partner, $location, $this->landingResolver->defaultUrl($benefit));
            }
        }

        if ($type === null || $type === OpportunityType::EXPERIENCE) {
            $experiences = $this->experiences->forCity($context->city);
            if ($category !== null) {
                $experiences->where('category', $category);
            }
            if ($like !== null) {
                $experiences->where(fn (Builder $query) => $query->where('title', 'ilike', $like)->orWhereHas('partners', fn (Builder $partner) => $partner->where('name', 'ilike', $like)));
            }
            foreach ($experiences->limit($limit)->get() as $experience) {
                $session = $experience->sessions->sortBy('starts_at')->first();
                if ($session !== null && $session->location !== null) {
                    $items[] = $this->experienceAdapter->adapt($experience, $city, $session, $session->location, $experience->partners->first(), $this->landingResolver->defaultUrl($experience));
                }
            }
        }

        // Unlocks have no category in V1. An explicit category therefore
        // yields no Unlocks rather than inventing a match from a linked domain.
        if (($type === null || $type === OpportunityType::UNLOCK) && $category === null) {
            $unlocks = $this->unlocks->forCity($context->city);
            if ($like !== null) {
                $unlocks->where(fn (Builder $query) => $query->where('title', 'ilike', $like)->orWhere('short_description', 'ilike', $like));
            }
            $unlocks = $unlocks->limit($limit)->get();
            $progress = $this->progress->forUnlocks($unlocks->pluck('id')->all(), $unlocks->mapWithKeys(fn (Unlock $unlock): array => [$unlock->id => $unlock->minimum_commitments])->all());
            foreach ($unlocks as $unlock) {
                $items[] = $this->unlockAdapter->adapt($unlock, $city, $progress[$unlock->id], $unlock->partner, $unlock->locations->first(), $this->landingResolver->defaultUrl($unlock));
            }
        }
        if (($type === null || $type === OpportunityType::CHALLENGE) && $category === null) {
            $challenges = $this->challengesForCity($context->city)->with(['partner:id,name,slug','benefit:id,title']);
            if ($like !== null) $challenges->where(fn (Builder $q) => $q->where('title','ilike',$like)->orWhere('description','ilike',$like));
            foreach ($challenges->limit($limit)->get() as $challenge) $items[] = $this->challengeAdapter->adapt($challenge,$city,$this->landingResolver->defaultUrl($challenge));
        }

        $items = array_values(collect($items)->unique(fn (DiscoveryOpportunity $item): string => $item->type->value.'|'.$item->sourceId)->all());
        $ranked = $this->ranker->rank($items, $this->affinity->explicitCategoryAffinity($context->user), $this->affinity->behavioralCategoryAffinity($context->user));

        return array_slice($type === null ? $this->diversify($ranked) : $ranked, 0, $limit);
    }

    /** @param list<DiscoveryOpportunity> $ranked @return list<DiscoveryOpportunity> */
    private function diversify(array $ranked): array
    {
        $result = [];
        while ($ranked !== []) {
            $last = $result[count($result) - 1] ?? null;
            $chosen = 0;
            // Only the next three ranked choices are "competitive". This is
            // a soft adjacency pass, not a quota or a global re-ranking.
            if ($last !== null) {
                foreach (array_slice($ranked, 1, 2, true) as $index => $candidate) {
                    if ($this->improvesDiversity($last, $candidate)) {
                        $chosen = $index;
                        break;
                    }
                }
            }
            $result[] = $ranked[$chosen];
            array_splice($ranked, $chosen, 1);
        }

        return $result;
    }

    private function improvesDiversity(DiscoveryOpportunity $last, DiscoveryOpportunity $candidate): bool
    {
        if ($candidate->type !== $last->type) {
            return true;
        }
        if ($candidate->partner?->id !== null && $candidate->partner->id !== $last->partner?->id) {
            return true;
        }

        return $candidate->categories !== [] && $last->categories !== [] && $candidate->categories[0] !== $last->categories[0];
    }

    private function isHappeningNow(DiscoveryOpportunity $item): bool
    {
        $weekFromNow = now()->addDays(7)->getTimestamp();
        if ($item->type === OpportunityType::EXPERIENCE) {
            return $item->startsAt !== null && $item->startsAt->getTimestamp() >= now()->getTimestamp() && $item->startsAt->getTimestamp() <= $weekFromNow;
        }
        if ($item->type === OpportunityType::BENEFIT) {
            return $item->endsAt !== null && $item->endsAt->getTimestamp() >= now()->getTimestamp() && $item->endsAt->getTimestamp() <= $weekFromNow;
        }

        $deadlineSoon = $item->endsAt !== null && $item->endsAt->getTimestamp() >= now()->getTimestamp() && $item->endsAt->getTimestamp() <= $weekFromNow;
        $target = (int) ($item->metadata['target'] ?? 0);
        $progress = (int) ($item->metadata['progress'] ?? 0);

        return $deadlineSoon || ($target > 0 && $progress * 2 >= $target);
    }

    private function challengesForCity(\App\Models\City $city): Builder {
        return Challenge::query()->where('review_status','APPROVED')->where('status','open')
            ->where(fn (Builder $q) => $q->whereNull('city_id')->orWhere('city_id',$city->id))
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at','<=',now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at','>',now()));
    }
}
