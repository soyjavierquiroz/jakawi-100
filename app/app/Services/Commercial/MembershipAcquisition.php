<?php

namespace App\Services\Commercial;

use App\Models\AttributionTouch;
use App\Models\MembershipBankPayment;
use App\Models\Partner;
use App\Models\ReferralRelationship;
use App\Models\User;
use App\Services\AcquisitionProviderResolver;
use App\Services\AttributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Attribution facts only. Beneficiary validation and all affiliation economics belong to CLUB. */
class MembershipAcquisition
{
    public const SESSION = 'commercial.membership_candidate';

    public function capture(Request $request, ?Partner $partner = null): void
    {
        if (! $request->hasSession() || app(AcquisitionProviderResolver::class)->suppressed($request)
            || $request->routeIs('partner.*') || $request->is('internal/*')) {
            return;
        }
        $context = $this->pending($request);
        if ($partner?->isPublished()) {
            $context['partner_id'] = $partner->id;
        }
        $code = $request->query('club_ref');
        if (is_string($code) && preg_match('/\A[A-Z0-9]{12}\z/', $code) && ! isset($context['club_referral_code'])) {
            // Shape checked here; CLUB remains the authority for actual code ownership/validity.
            $context['club_referral_code'] = $code;
        }
        if ($context) {
            $context['captured_at'] ??= now()->timestamp;
            $context['user_id'] ??= $request->user()?->id;
            $request->session()->put(self::SESSION, $context);
        }
    }

    public function pending(Request $request): array
    {
        $context = $request->hasSession() ? $request->session()->get(self::SESSION, []) : [];
        if (! is_array($context) || ($context['captured_at'] ?? 0) < now()->subDays(app(AttributionService::class)->windowDays())->timestamp
            || (isset($context['user_id']) && $context['user_id'] !== $request->user()?->id)) {
            return [];
        }

        return $context;
    }

    public function bindLogin(Request $request, User $user): void
    {
        $context = $this->pending($request);
        if ($context) {
            $context['user_id'] = $user->id;
            $request->session()->put(self::SESSION, $context);
        }
    }

    public function forUser(User $user): ?array
    {
        $raw = DB::table('membership_acquisition_origins')->where('user_id', $user->id)->value('attribution');

        return $raw === null ? null : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    }

    public function original(User $user): ?array
    {
        if ($fixed = $this->forUser($user)) {
            return $fixed;
        }
        // An established original is not replaced because its old commercial window expired.
        $relationship = ReferralRelationship::where('referred_user_id', $user->id)->where('status', '!=', 'invalid')
            ->with('referrer')->orderBy('attributed_at')->orderBy('id')->first();
        if (! $relationship) {
            return null;
        }
        $data = ['source' => 'jakawi.com', 'com_referral_id' => (string) $relationship->id];

        return $relationship->acquisition_partner_id
            ? $data + ['kind' => 'partner', 'partner_id' => (int) $relationship->acquisition_partner_id]
            : $data + ['kind' => 'com_referral', 'referrer_global_subject_id' => $relationship->referrer?->global_subject_id];
    }

    /** A browsing candidate is not a lead, an established referral or an economic obligation. */
    public function candidate(Request $request, ?User $user = null, ?string $manualCode = null): ?array
    {
        if ($user && ($original = $this->original($user))) {
            return $original;
        }
        $context = $this->pending($request);
        $fallback = isset($context['partner_id']) ? Partner::published()->find($context['partner_id'])?->id : null;
        if (isset($context['club_referral_code'])) {
            return array_filter(['source' => 'jakawi.com', 'kind' => 'club_referral',
                'club_referral_code' => $context['club_referral_code'], 'fallback_partner_id' => $fallback,
                'beneficiary_resolution' => 'CLUB'], fn ($value) => $value !== null);
        }
        $legacy = app(AttributionService::class);
        if ($manualCode && ($referrer = $legacy->findReferrer($manualCode)) && (! $user || $referrer instanceof Partner || $referrer->id !== $user->id)) {
            return $referrer instanceof Partner
                ? ['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $referrer->id, 'legacy_explicit' => true]
                : ['source' => 'jakawi.com', 'kind' => 'com_referral', 'referrer_global_subject_id' => $referrer->global_subject_id, 'legacy_explicit' => true];
        }
        $ids = $request->hasSession() ? $request->session()->get('attribution_touch_ids', []) : [];
        if (is_array($ids) && $ids) {
            $touch = AttributionTouch::whereIn('id', $ids)->whereNotNull('referral_code')
                ->where('occurred_at', '>=', now()->subDays($legacy->windowDays()))
                ->where(fn ($q) => $q->whereNull('user_id')->when($user, fn ($q) => $q->orWhere('user_id', $user->id)))
                ->with('referrer', 'acquisitionPartner')->orderBy('occurred_at')->orderBy('id')->get()
                ->first(fn ($t) => $t->acquisitionPartner !== null || ($t->referrer && $t->referrer_user_id !== $user?->id));
            if ($touch) {
                return $touch->acquisition_partner_id
                    ? ['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => (int) $touch->acquisition_partner_id, 'legacy_explicit' => true]
                    : ['source' => 'jakawi.com', 'kind' => 'com_referral', 'referrer_global_subject_id' => $touch->referrer->global_subject_id, 'legacy_explicit' => true];
            }
        }

        return $fallback ? ['source' => 'jakawi.com', 'kind' => 'partner', 'partner_id' => $fallback] : null;
    }

    public function fixUser(User $user, ?Request $request, string $basis, ?string $manualCode = null, ?array $imported = null): ?array
    {
        return DB::transaction(function () use ($user, $request, $basis, $manualCode, $imported) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($fixed = $this->forUser($user)) {
                return $fixed;
            }
            $data = $this->original($user) ?? $imported ?? ($request ? $this->candidate($request, $user, $manualCode) : null);
            if (! $data && $basis === 'lead') {
                return null;
            }

            return $this->insert($data ?? ['source' => 'jakawi.com', 'kind' => 'none'], $basis, $user->id);
        });
    }

    public function insert(array $data, string $basis, ?int $userId = null): array
    {
        // Incoming proof never determines a buyer identity or reuses another account's row.
        unset($data['attribution_id'], $data['fixed_at'], $data['basis']);
        $data += ['attribution_id' => (string) Str::uuid(), 'fixed_at' => now()->toRfc3339String(), 'basis' => $basis];
        DB::table('membership_acquisition_origins')->insert(['id' => $data['attribution_id'], 'user_id' => $userId,
            'attribution' => json_encode($data, JSON_THROW_ON_ERROR), 'fixed_at' => $data['fixed_at']]);

        return $data;
    }

    public function lead(User $user, ?array $origin): void
    {
        if (! $origin || $origin['kind'] === 'none') {
            return;
        }
        $id = 'com:member:'.$user->global_subject_id.':registered';
        $this->enqueue($id, ['event_type' => 'member.registered', 'global_subject_id' => $user->global_subject_id,
            'com_reference' => ['type' => 'user', 'id' => (string) $user->id], 'acquisition' => $origin]);
    }

    public function purchase(MembershipBankPayment $payment, array $origin, ?User $buyer = null): void
    {
        $payment->update(['acquisition_origin_id' => $origin['attribution_id']]);
        if ($origin['kind'] === 'none') {
            return;
        }
        $this->enqueue('com:membership:'.$payment->reference.':attribution-fixed', [
            'event_type' => 'membership.attribution_fixed', 'payment_reference' => $payment->reference,
            'club_reference' => $payment->club_reference, 'guest_purchase_id' => $payment->guest_purchase_id,
            'club_user_uuid' => $payment->club_user_uuid, 'global_subject_id' => $buyer?->global_subject_id,
            'acquisition' => $origin]);
    }

    public function identityLinked(MembershipBankPayment $payment, User $user): void
    {
        if (! $payment->acquisition_origin_id) {
            return;
        }
        $raw = DB::table('membership_acquisition_origins')->where('id', $payment->acquisition_origin_id)->value('attribution');
        $purchase = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        $effective = $this->fixUser($user, null, 'identity_link', imported: $purchase);
        if ($purchase['kind'] === 'none' && $effective['kind'] === 'none') {
            return;
        }
        $this->enqueue('com:membership:'.$payment->reference.':attribution-identity-linked', [
            'event_type' => 'membership.attribution_identity_linked', 'payment_reference' => $payment->reference,
            'club_reference' => $payment->club_reference, 'global_subject_id' => $user->global_subject_id,
            'club_user_uuid' => $payment->club_user_uuid, 'guest_purchase_id' => $payment->guest_purchase_id,
            'purchase_acquisition' => $purchase, 'acquisition' => $effective]);
    }

    private function enqueue(string $id, array $facts): void
    {
        DB::table('commercial_outbox')->insertOrIgnore(['event_id' => $id,
            'raw_body' => json_encode(['schema_version' => 1, 'event_id' => $id, 'source' => 'jakawi.com',
                'occurred_at' => now()->toRfc3339String(), ...$facts], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'status' => 'PENDING', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }
}
