<?php

namespace App\Services;

use App\Models\ExperienceReservation;
use App\Models\Partner;
use App\Models\Redemption;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PartnerKpiService
{
    /** @return array<string, mixed> */
    public function forPartner(Partner $partner, int $days, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $start = $now->subDays($days);
        $end = $now;
        $id = $partner->id;

        // A value interaction is a confirmed redemption or an attendance check-in.
        $interactions = DB::query()->fromSub(
            DB::table('redemptions')->select('user_id', 'confirmed_at as happened_at')
                ->where('partner_id', $id)->where('status', Redemption::STATUS_CONFIRMED)->whereNotNull('confirmed_at')
                ->unionAll(DB::table('experience_reservations')->select('user_id', 'checked_in_at as happened_at')
                    ->where('partner_id', $id)->whereNotNull('checked_in_at')),
            'value_interactions'
        );
        $servedIds = (clone $interactions)->whereBetween('happened_at', [$start, $end])->distinct()->pluck('user_id');
        $served = $servedIds->count();
        $returning = $servedIds->isEmpty() ? 0 : (clone $interactions)->whereIn('user_id', $servedIds)->where('happened_at', '<', $start)->distinct()->count('user_id');

        $redemptions = DB::table('redemptions')->where('partner_id', $id)->where('status', Redemption::STATUS_CONFIRMED)->whereBetween('confirmed_at', [$start, $end]);
        $reservations = DB::table('experience_reservations')->where('partner_id', $id)->whereBetween('created_at', [$start, $end]);
        $eligible = DB::table('experience_reservations as r')->join('experience_sessions as s', 's.id', '=', 'r.experience_session_id')
            ->where('r.partner_id', $id)->where('r.status', ExperienceReservation::STATUS_CONFIRMED)
            ->whereBetween('s.starts_at', [$start, $end])->where('s.starts_at', '<=', $now);

        $eventCounts = DB::table('analytics_events')->where('partner_id', $id)->whereBetween('occurred_at', [$start, $end])
            ->whereIn('event_name', ['partner_view', 'benefit_view', 'experience_view', 'maps_click', 'whatsapp_click', 'experience_reserve_click'])
            ->selectRaw('event_name, count(*) as total')->groupBy('event_name')->pluck('total', 'event_name');
        $trend = $this->trend($id, $start, $end, $days);

        return [
            'period' => ['days' => $days, 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'value' => ['members_served' => $served, 'new_members' => $served - $returning, 'returning_members' => $returning, 'return_rate' => $served ? round($returning / $served * 100, 1) : 0],
            'benefits' => ['views' => (int) ($eventCounts['benefit_view'] ?? 0), 'redeem_started' => (int) (DB::table('analytics_events')->where('partner_id', $id)->where('event_name', 'redeem_started')->whereBetween('occurred_at', [$start, $end])->count()), 'confirmed' => (clone $redemptions)->count(), 'unique_members' => (clone $redemptions)->distinct()->count('user_id'), 'savings' => (float) (clone $redemptions)->sum('savings_amount'), 'average_savings' => (float) (clone $redemptions)->avg('savings_amount') ?: 0],
            'experiences' => ['views' => $this->experienceEventCount($id, 'experience_view', $start, $end), 'requests' => (clone $reservations)->count(), 'confirmed' => (clone $reservations)->where('status', ExperienceReservation::STATUS_CONFIRMED)->count(), 'checkins' => (clone $reservations)->whereNotNull('checked_in_at')->count(), 'people' => (int) (clone $reservations)->whereNotNull('checked_in_at')->sum('party_size'), 'attendance_rate' => ($denominator = (clone $eligible)->count()) ? round((clone $eligible)->whereNotNull('r.checked_in_at')->count() / $denominator * 100, 1) : 0],
            'topBenefits' => DB::table('benefits as b')->leftJoin('redemptions as r', function ($join) use ($start, $end) {
                $join->on('r.benefit_id', '=', 'b.id')->where('r.status', Redemption::STATUS_CONFIRMED)->whereBetween('r.confirmed_at', [$start, $end]);
            })->where('b.partner_id', $id)->selectRaw('b.title, count(r.id) as redemptions, count(distinct r.user_id) as members, coalesce(sum(r.savings_amount), 0) as savings')->groupBy('b.id', 'b.title')->orderByDesc('redemptions')->orderBy('b.title')->limit(5)->get(),
            'topExperiences' => DB::table('experiences as e')->join('experience_partner', 'experience_partner.experience_id', '=', 'e.id')->leftJoin('experience_reservations as r', function ($join) use ($id, $start, $end) {
                $join->on('r.experience_id', '=', 'e.id')->where('r.partner_id', $id)->whereBetween('r.created_at', [$start, $end]);
            })->where('experience_partner.partner_id', $id)->selectRaw('e.title, count(r.id) as reservations, sum(case when r.status = ? then 1 else 0 end) as confirmed, sum(case when r.checked_in_at is not null then 1 else 0 end) as checkins, coalesce(sum(case when r.checked_in_at is not null then r.party_size else 0 end), 0) as people', [ExperienceReservation::STATUS_CONFIRMED])->groupBy('e.id', 'e.title')->orderByDesc('people')->orderByDesc('checkins')->orderBy('e.title')->limit(5)->get(),
            'discovery' => ['partner_views' => (int) ($eventCounts['partner_view'] ?? 0), 'benefit_views' => (int) ($eventCounts['benefit_view'] ?? 0), 'experience_views' => $this->experienceEventCount($id, 'experience_view', $start, $end), 'maps_clicks' => (int) ($eventCounts['maps_click'] ?? 0), 'whatsapp_clicks' => (int) ($eventCounts['whatsapp_click'] ?? 0), 'reserve_clicks' => $this->experienceEventCount($id, 'experience_reserve_click', $start, $end)],
            'trend' => $trend,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function trend(int $partnerId, CarbonImmutable $start, CarbonImmutable $end, int $days): array
    {
        $format = $days === 90 ? 'IYYY-IW' : 'YYYY-MM-DD';
        $redemptions = DB::table('redemptions')->where('partner_id', $partnerId)->where('status', Redemption::STATUS_CONFIRMED)->whereBetween('confirmed_at', [$start, $end])->selectRaw("to_char(confirmed_at, '{$format}') as bucket, count(*) as total")->groupBy('bucket')->pluck('total', 'bucket');
        $checkins = DB::table('experience_reservations')->where('partner_id', $partnerId)->whereNotNull('checked_in_at')->whereBetween('checked_in_at', [$start, $end])->selectRaw("to_char(checked_in_at, '{$format}') as bucket, count(*) as total")->groupBy('bucket')->pluck('total', 'bucket');

        return collect(array_unique(array_merge($redemptions->keys()->all(), $checkins->keys()->all())))->sort()->map(fn ($bucket) => ['label' => $bucket, 'redemptions' => (int) ($redemptions[$bucket] ?? 0), 'checkins' => (int) ($checkins[$bucket] ?? 0)])->values()->all();
    }

    private function experienceEventCount(int $partnerId, string $event, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return DB::table('analytics_events')->where('event_name', $event)->whereBetween('occurred_at', [$start, $end])
            ->whereIn('experience_id', DB::table('experience_partner')->where('partner_id', $partnerId)->select('experience_id'))->count();
    }
}
