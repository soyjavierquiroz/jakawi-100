<?php

namespace App\Services\Commercial;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MembershipEvidence
{
    public const CONTRACT = 'jakawi-acquisition-evidence-v1';

    private Collection $journal;

    private CarbonImmutable $boundary;

    public function resolve(string $reference, ?string $affiliate): array
    {
        $this->boundary = CarbonImmutable::parse(DB::table('membership_evidence_boundary')->value('observed_from'));
        $this->journal = $this->loadJournal($reference, $affiliate);
        $purchases = collect(['membership_bank_payments', 'membership_purchases'])
            ->flatMap(fn ($table) => $this->latest($table)->filter(fn ($r) => $r->fact['reference'] === $reference)->values());
        $response = [
            'contract' => self::CONTRACT, 'purchase_reference' => $reference,
            'purchase_source' => null, 'membership_reference' => null, 'global_subject_id' => null,
            'identity_reference' => null, 'first_activated_at' => null,
            'first_paid_acquisition' => 'unknown', 'reason' => 'PURCHASE_NOT_FOUND',
            'evaluated_at' => null, 'affiliate' => $affiliate ? [
                'global_subject_id' => $affiliate, 'state' => 'UNKNOWN', 'reason' => 'ACTIVATION_UNCERTIFIED',
                'evaluated_at' => null, 'membership_references' => [],
            ] : null,
            'history_observed_from' => $this->boundary->toISOString(),
        ];
        if ($purchases->count() === 1) {
            $row = $purchases->first();
            $p = $row->fact;
            $response['purchase_source'] = $row->entity;
            $response['membership_reference'] = isset($p['membership_id']) ? (string) $p['membership_id'] : null;
            $membership = $this->latest('memberships')->get($p['membership_id'] ?? 0);
            $confirmed = $row->operation !== 'DELETE' && in_array($p['status'], ['approved', 'confirmed'], true) && (float) $p['amount'] > 0;
            $firstFact = $membership ? $this->rows('memberships')->firstWhere('row_id', $membership->row_id) : null;
            $at = $row->entity === 'membership_bank_payments' ? ($p['activated_at'] ?? null) : ($firstFact->fact['starts_at'] ?? null);
            if (! $confirmed || ! $membership || ! $at || ! $firstFact
                || (float) $firstFact->fact['amount_paid'] <= 0 || $firstFact->fact['payment_reference'] !== $reference
                || $this->rows('memberships')->where('row_id', $membership->row_id)->contains(fn ($m) => $m->fact['starts_at'] !== $firstFact->fact['starts_at'])
                || ! CarbonImmutable::parse($at)->equalTo(CarbonImmutable::parse($firstFact->fact['starts_at']))
                || CarbonImmutable::parse($at)->isFuture()) {
                $response['reason'] = 'PURCHASE_OR_ACTIVATION_UNCERTIFIED';
            } else {
                $at = CarbonImmutable::parse($at);
                $response['first_activated_at'] = $response['evaluated_at'] = $at->toISOString();
                [$user, $identityReason] = $this->buyer($row);
                $response['reason'] = $identityReason;
                if ($user) {
                    $response['global_subject_id'] = $user->fact['global_subject_id'];
                    $response['identity_reference'] = 'com:user:'.$user->row_id;
                    [$response['first_paid_acquisition'], $response['reason']] = $this->first($user, $membership->row_id, $at);
                }
                if ($affiliate) {
                    $response['affiliate'] = $this->affiliate($affiliate, $at);
                }
            }
        } elseif ($purchases->count() > 1) {
            $response['reason'] = 'PURCHASE_REFERENCE_CONFLICT';
        }
        // Durable source revision references plus a content digest; no economic state is written.
        $subjects = array_filter([$response['global_subject_id'], $affiliate]);
        $users = $this->rows('users')->filter(fn ($u) => in_array($u->fact['global_subject_id'], $subjects, true))->pluck('row_id')->unique()->all();
        $membershipIds = collect($users)->flatMap(fn ($id) => $this->ownedMembershipIds($id))->push((int) $response['membership_reference'])->all();
        $response['evidence_references'] = $this->journal->filter(fn ($r) => match ($r->entity) {
            'users' => in_array($r->row_id, $users, true),
            'memberships' => in_array($r->row_id, $membershipIds, true),
            'identity_links' => in_array($r->fact['user_id'], $users, true),
            default => ($r->fact['reference'] ?? null) === $reference
                || in_array($r->fact['user_id'] ?? $r->fact['beneficiary_user_id'] ?? 0, $users, true),
        })->map(fn ($r) => 'com:membership-evidence:'.$r->id)->values()->all();
        $response['evidence_reference'] = 'com:acquisition-evidence:sha256:'.hash('sha256', json_encode($response, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response;
    }

    private function loadJournal(string $reference, ?string $affiliate): Collection
    {
        $query = fn () => DB::table('membership_evidence_journal');
        $decode = fn ($rows) => $rows->map(function ($row) {
            $row->fact = json_decode($row->fact, true, 512, JSON_THROW_ON_ERROR);

            return $row;
        });
        $expand = function (Collection $rows) use ($query, $decode): Collection {
            if ($rows->isEmpty()) {
                return $rows;
            }

            return $decode($query()->where(function ($q) use ($rows) {
                foreach ($rows->groupBy('entity') as $entity => $group) {
                    $q->orWhere(fn ($q) => $q->where('entity', $entity)->whereIn('row_id', $group->pluck('row_id')->all()));
                }
            })->get());
        };
        $target = $expand($decode($query()->whereIn('entity', ['membership_bank_payments', 'membership_purchases'])
            ->where('fact->reference', $reference)->get()));
        $users = $target->flatMap(fn ($r) => [$r->fact['user_id'] ?? null, $r->fact['beneficiary_user_id'] ?? null, $r->fact['custody_user_id'] ?? null])->filter();
        if ($affiliate) {
            $users = $users->merge($query()->where('entity', 'users')->where('fact->global_subject_id', $affiliate)->pluck('row_id'));
        }
        $clubs = $target->pluck('fact.club_user_uuid')->filter()->all();
        $links = $expand($decode($query()->where('entity', 'identity_links')->where(function ($q) use ($users, $clubs) {
            $q->whereIn(DB::raw("(fact->>'user_id')::bigint"), $users->all())->orWhereIn('fact->club_user_id', $clubs);
        })->get()));
        $users = $users->merge($links->pluck('fact.user_id'))->filter()->unique()->values();
        $purchases = $expand($decode($query()->whereIn('entity', ['membership_bank_payments', 'membership_purchases'])
            ->where(function ($q) use ($users, $links, $reference) {
                $q->where('fact->reference', $reference)
                    ->orWhereIn(DB::raw("(fact->>'user_id')::bigint"), $users->all())
                    ->orWhereIn(DB::raw("(fact->>'beneficiary_user_id')::bigint"), $users->all())
                    ->orWhereIn('fact->club_user_uuid', $links->pluck('fact.club_user_id')->all());
            })->get()));
        $membershipIds = $query()->where('entity', 'memberships')->whereIn(DB::raw("(fact->>'user_id')::bigint"), $users->all())->pluck('row_id')
            ->merge($purchases->pluck('fact.membership_id'))->filter()->unique()->all();
        $memberships = $decode($query()->where('entity', 'memberships')->whereIn('row_id', $membershipIds)->get());
        $identities = $decode($query()->where('entity', 'users')->whereIn('row_id', $users->all())->get());

        return $purchases->concat($memberships)->concat($identities)->concat($links)->unique('id')->sortBy('id')->values();
    }

    private function rows(string $entity): Collection
    {
        return $this->journal->where('entity', $entity);
    }

    private function latest(string $entity): Collection
    {
        return $this->rows($entity)->keyBy('row_id');
    }

    private function buyer(object $purchase): array
    {
        $p = $purchase->fact;
        $id = $purchase->entity === 'membership_purchases' ? $p['beneficiary_user_id'] : ($p['user_id'] ?? null);
        if (($p['origin'] ?? null) === 'CLUB') {
            if (($p['identity_status'] ?? null) !== 'linked' || ! ($p['club_user_uuid'] ?? null) || $id === ($p['custody_user_id'] ?? null)) {
                return [null, 'BUYER_IDENTITY_UNRESOLVED'];
            }
            $link = $this->latest('identity_links')->first(fn ($r) => $r->operation !== 'DELETE' && $r->fact['source_app'] === 'club'
                && $r->fact['club_user_id'] === $p['club_user_uuid'] && $r->fact['status'] === 'LINKED'
                && $r->fact['revoked_at'] === null && $r->fact['user_id'] === $id);
            if (! $link) {
                return [null, 'BUYER_LINK_UNAUTHORIZED'];
            }
        }
        $user = $this->latest('users')->get($id);
        if (! $user || $user->operation === 'DELETE') {
            return [null, 'BUYER_IDENTITY_UNRESOLVED'];
        }
        if (isset($link) && $link->fact['global_subject_id'] !== $user->fact['global_subject_id']) {
            return [null, 'BUYER_LINK_UNAUTHORIZED'];
        }
        $m = $this->latest('memberships')->get($p['membership_id'] ?? 0);
        if (! $m || $m->fact['user_id'] !== $id) {
            return [null, 'MEMBERSHIP_OWNER_CONFLICT'];
        }

        $owners = $this->rows('memberships')->where('row_id', $m->row_id)->pluck('fact.user_id')->unique();
        if ($owners->count() > 1 && (($p['origin'] ?? null) !== 'CLUB'
            || $owners->diff([$id, $p['custody_user_id'] ?? null])->isNotEmpty())) {
            return [null, 'MEMBERSHIP_OWNER_HISTORY_UNCERTAIN'];
        }

        return [$user, null];
    }

    private function first(object $user, int $membershipId, CarbonImmutable $at): array
    {
        $ids = $this->ownedMembershipIds($user->row_id);
        $memberships = $this->rows('memberships')->whereIn('row_id', $ids);
        if ($memberships->contains(fn ($m) => $m->row_id !== $membershipId && (float) $m->fact['amount_paid'] > 0
            && CarbonImmutable::parse($m->fact['starts_at'])->lessThan($at))) {
            return [false, 'PRIOR_PAID_MEMBERSHIP'];
        }
        // Purchases lacking a surviving membership are still evidence, including refunds.
        foreach (['membership_bank_payments', 'membership_purchases'] as $entity) {
            foreach ($this->rows($entity) as $r) {
                $p = $r->fact;
                $owner = $entity === 'membership_purchases' ? ($p['beneficiary_user_id'] ?? null) : ($p['user_id'] ?? null);
                $relatedClub = ($p['origin'] ?? null) === 'CLUB' && $this->latest('identity_links')->contains(fn ($l) => $l->fact['user_id'] === $user->row_id && $l->fact['club_user_id'] === ($p['club_user_uuid'] ?? null));
                if (($owner !== $user->row_id && ! $relatedClub) || (float) $p['amount'] <= 0 || ($p['membership_id'] ?? null) === $membershipId) {
                    continue;
                }
                if ($owner !== $user->row_id && $relatedClub && in_array($p['status'], ['approved', 'confirmed', 'refunded'], true)) {
                    return ['unknown', 'RELATED_PURCHASE_IDENTITY_UNRESOLVED'];
                }
                $paid = $p['activated_at'] ?? $p['paid_at'] ?? null;
                if ($paid && in_array($p['status'], ['approved', 'confirmed', 'refunded'], true) && CarbonImmutable::parse($paid)->lessThan($at)) {
                    return [false, 'PRIOR_PAID_PURCHASE'];
                }
                if (in_array($p['status'], ['approved', 'confirmed', 'refunded'], true)) {
                    return ['unknown', 'OTHER_PAID_PURCHASE_UNDATED_OR_CONCURRENT'];
                }
            }
        }
        if ($memberships->contains(fn ($m) => $m->row_id !== $membershipId
            && CarbonImmutable::parse($m->fact['starts_at'])->lessThanOrEqualTo($at))) {
            return ['unknown', 'OTHER_MEMBERSHIP_PAYMENT_UNCERTAIN'];
        }
        $birth = $this->rows('users')->where('row_id', $user->row_id)->first();
        if (! $birth || $birth->operation !== 'INSERT' || $at->lessThan($this->boundary)) {
            return ['unknown', 'HISTORY_BEFORE_OBSERVATION_BOUNDARY'];
        }

        return [true, 'COMPLETE_COM_HISTORY_SINCE_IDENTITY_CREATION'];
    }

    private function ownedMembershipIds(int $user): array
    {
        // Authorized late guest claims carry the original membership, never a second activation.
        $ids = $this->rows('memberships')->filter(fn ($m) => $m->fact['user_id'] === $user)->pluck('row_id');
        foreach ($this->latest('membership_bank_payments') as $p) {
            if (($p->fact['origin'] ?? null) === 'CLUB' && ($p->fact['user_id'] ?? null) === $user) {
                [$buyer] = $this->buyer($p);
                if ($buyer && isset($p->fact['membership_id'])) {
                    $ids->push($p->fact['membership_id']);
                }
            }
        }

        return $ids->unique()->all();
    }

    private function affiliate(string $subject, CarbonImmutable $at): array
    {
        $result = ['global_subject_id' => $subject, 'state' => 'UNKNOWN', 'reason' => 'IDENTITY_UNKNOWN',
            'evaluated_at' => $at->toISOString(), 'membership_references' => []];
        $user = $this->latest('users')->first(fn ($u) => $u->operation !== 'DELETE' && $u->fact['global_subject_id'] === $subject);
        if (! $user) {
            return $result;
        }
        if ($at->lessThan($this->boundary) || $at->lessThan(CarbonImmutable::parse($user->fact['created_at']))) {
            $result['reason'] = 'HISTORICAL_STATE_NOT_OBSERVED';

            return $result;
        }
        $clubs = $this->latest('identity_links')->filter(fn ($l) => $l->fact['user_id'] === $user->row_id)->pluck('fact.club_user_id')->all();
        $uncertain = $this->latest('membership_bank_payments')->contains(function ($p) use ($clubs, $user, $at) {
            $f = $p->fact;
            if (($f['origin'] ?? null) !== 'CLUB' || ! in_array($f['club_user_uuid'] ?? null, $clubs, true)
                || $f['status'] !== 'approved' || ! ($f['activated_at'] ?? null)
                || CarbonImmutable::parse($f['activated_at'])->greaterThan($at)) {
                return false;
            }
            [$buyer] = $this->buyer($p);

            return ! $buyer || $buyer->row_id !== $user->row_id;
        });
        foreach ($this->ownedMembershipIds($user->row_id) as $id) {
            $rows = $this->rows('memberships')->where('row_id', $id);
            // Membership start is a domain effective time; observation time dates cancellations/revocations.
            $state = $rows->filter(fn ($r) => CarbonImmutable::parse($r->observed_at)->lessThanOrEqualTo($at))->last();
            if (! $state) {
                // Timestamp precision in existing activation paths is seconds. INSERT may be milliseconds later.
                $first = $rows->first();
                if ($first && $first->operation === 'INSERT' && CarbonImmutable::parse($first->fact['starts_at'])->lessThanOrEqualTo($at)) {
                    if (CarbonImmutable::parse($first->observed_at)->diffInSeconds($at, true) >= 1) {
                        $uncertain = true;

                        continue;
                    }
                    $state = $first;
                } else {
                    continue;
                }
            }
            $m = $state->fact;
            if ($m['user_id'] !== $user->row_id) {
                $authorizedCustody = $this->latest('membership_bank_payments')->contains(function ($p) use ($id, $m, $user) {
                    if (($p->fact['membership_id'] ?? null) !== $id || ($p->fact['custody_user_id'] ?? null) !== $m['user_id']) {
                        return false;
                    }
                    [$buyer] = $this->buyer($p);

                    return $buyer && $buyer->row_id === $user->row_id;
                });
                if (! $authorizedCustody) {
                    continue;
                }
            }
            if (CarbonImmutable::parse($m['starts_at'])->lessThanOrEqualTo($at) && CarbonImmutable::parse($m['ends_at'])->greaterThanOrEqualTo($at)) {
                if ($state->operation === 'DELETE' || $m['status'] === 'cancelled') {
                    continue;
                }
                if ($m['status'] !== 'active' || (float) $m['amount_paid'] <= 0) {
                    $uncertain = true;

                    continue;
                }
                $result['membership_references'][] = (string) $id;
            }
        }
        if ($result['membership_references'] !== [] && ! $uncertain) {
            $result['state'] = 'PAID';
            $result['reason'] = 'EFFECTIVE_PAID_MEMBERSHIP';
        } elseif ($uncertain) {
            $result['reason'] = 'MEMBERSHIP_STATE_UNCERTAIN';
        } else {
            $result['state'] = 'FREE';
            $result['reason'] = 'AUTHORITATIVE_COM_HISTORY_AT_INSTANT';
        }

        return $result;
    }
}
