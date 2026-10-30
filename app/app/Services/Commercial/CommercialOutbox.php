<?php

namespace App\Services\Commercial;

use App\Models\LandingPresentation;
use App\Models\Redemption;
use App\Models\ReferralRelationship;
use App\Services\AttributionService;
use App\Services\PublicJourneyContinuation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CommercialOutbox
{
    public function capture(Redemption $redemption): void
    {
        $pilot = LandingPresentation::where('slug', config('commercial.pilot_landing'))->first();
        $benefit = $redemption->benefit;
        if (! $pilot || $pilot->subject_type !== $benefit?->getMorphClass()
            || (int) $pilot->subject_id !== (int) $redemption->benefit_id
            || $redemption->partner?->slug !== config('commercial.pilot_partner')) {
            return;
        }

        $request = app(Request::class);
        $actorIsCustomer = $request->user()?->id === $redemption->user_id;
        $touch = app(AttributionService::class)->latestApplicableTouch($redemption->user_id, null);
        $matchesTouch = $touch && ($touch->metadata['landing_presentation_id'] ?? null) === $pilot->id
            && ($touch->metadata['subject_type'] ?? null) === $pilot->subject_type
            && ($touch->metadata['subject_id'] ?? null) === $redemption->benefit_id;
        $intent = $actorIsCustomer && $request->hasSession() ? app(PublicJourneyContinuation::class)->get() : null;
        $matchesIntent = ($intent['landing_presentation_id'] ?? null) === $pilot->id
            && ($intent['journey'] ?? null) === 'BENEFIT'
            && ($intent['resource_id'] ?? null) === $redemption->benefit_id;

        $attribution = ['partner' => ['id' => (string) $redemption->partner_id, 'name' => $redemption->partner_name]];
        if ($matchesIntent || $matchesTouch) {
            $attribution['origin'] = ['landing' => $matchesTouch ? ($touch->metadata['landing_slug'] ?? $pilot->slug) : $pilot->slug];
            $campaign = $matchesTouch ? ($touch->campaign_key ?: $touch->utm_campaign) : $pilot->campaign_key;
            if ($campaign !== null && $campaign !== '') {
                $attribution['origin']['campaign'] = $campaign;
            }
        }
        // An original relationship tied to this exact touch, never inferred from identity.
        if ($matchesTouch && ($relationship = ReferralRelationship::where('attribution_touch_id', $touch->id)
            ->where('referred_user_id', $redemption->user_id)->first())) {
            $attribution['referral'] = ['com_referral_id' => (string) $relationship->id];
        }
        if ($clubOrigin = app(ClubReferralOrigin::class)->forRedemption($request, $redemption)) {
            $attribution['origin'] = ['landing' => $clubOrigin['landing']];
            if ($clubOrigin['campaign'] !== null && $clubOrigin['campaign'] !== '') {
                $attribution['origin']['campaign'] = $clubOrigin['campaign'];
            }
            $attribution['referral']['club_referral_code'] = $clubOrigin['club_referral_code'];
        }
        if ($subject = $redemption->user?->global_subject_id) {
            $attribution['global_subject_id'] = $subject;
        }
        DB::table('commercial_conversion_origins')->insertOrIgnore([
            'redemption_id' => $redemption->id,
            'attribution' => json_encode($attribution, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'captured_at' => now(),
        ]);
    }

    public function collect(int $limit = 100): int
    {
        // Other connections see this only after the domain transaction commits. Recovery
        // after crashes needs neither a transient queue message nor the operator's session.
        $rows = DB::table('commercial_conversion_origins as o')
            ->join('redemptions as r', 'r.id', '=', 'o.redemption_id')
            ->where('r.status', Redemption::STATUS_CONFIRMED)->whereNotNull('r.confirmed_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('commercial_outbox as d')
                ->whereRaw("d.event_id = 'com:redemption:' || r.public_id || ':confirmed'"))
            ->orderBy('o.id')->limit(min(100, max(0, $limit)))
            ->get(['r.public_id', 'r.confirmed_at', 'o.attribution']);
        $count = 0;
        foreach ($rows as $row) {
            $eventId = 'com:redemption:'.$row->public_id.':confirmed';
            $event = [
                'schema_version' => 1, 'event_id' => $eventId, 'event_type' => 'redemption.confirmed',
                'occurred_at' => CarbonImmutable::parse($row->confirmed_at, config('app.timezone'))->toRfc3339String(),
                'com_reference' => ['type' => 'redemption', 'id' => $row->public_id],
                'conversion_status' => Redemption::STATUS_CONFIRMED,
                ...json_decode($row->attribution, true, 512, JSON_THROW_ON_ERROR),
            ];
            $count += DB::table('commercial_outbox')->insertOrIgnore([
                'event_id' => $eventId, 'raw_body' => json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status' => 'PENDING', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $count;
    }
}
