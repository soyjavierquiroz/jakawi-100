<?php

namespace App\Services;

use App\Enums\AcquisitionProvider;
use App\Http\Middleware\EnsureVisitorId;
use App\Models\AppSetting;
use App\Models\AttributionTouch;
use App\Models\Partner;
use App\Models\ReferralRelationship;
use App\Models\User;
use App\Models\Unlock;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributionService
{
    public function anonymousId(Request $request): ?string
    {
        $id = $request->attributes->get(EnsureVisitorId::ATTRIBUTE);
        return is_string($id) && Str::isUuid($id) ? $id : null;
    }

    /** Resolve measurement continuation without changing economic conversion attribution. */
    public function latestApplicableTouch(?int $userId, ?string $anonymousId, ?AttributionTouch $explicitTouch = null): ?AttributionTouch
    {
        $cutoff = now()->subDays($this->windowDays());
        if ($explicitTouch) return $explicitTouch->occurred_at->gte($cutoff) ? $explicitTouch : null;

        return AttributionTouch::query()->where('occurred_at', '>=', $cutoff)
            ->where(function ($query) use ($userId, $anonymousId) {
                if ($userId !== null) $query->where('user_id', $userId);
                elseif ($anonymousId) $query->where('anonymous_id', $anonymousId);
                else $query->whereRaw('1 = 0');
            })->orderByDesc('occurred_at')->orderByDesc('id')->first();
    }

    /** A landing render alone is not a new acquisition; preserve valid referral capture. */
    public function recordLandingTouch(Request $request, User|Partner|null $referrer = null, ?string $campaignKey = null): ?AttributionTouch
    {
        $resolver = app(AcquisitionProviderResolver::class);
        if ($resolver->suppressed($request)) return null;
        $hasSignal = $resolver->forNewTouch($request) !== AcquisitionProvider::NONE || is_string($campaignKey) && trim($campaignKey) !== '';
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'ttclid', 'gclid'] as $name) {
            $value = in_array($name, ['fbclid', 'ttclid', 'gclid'], true) ? $request->query($name) : $request->input($name);
            $hasSignal = $hasSignal || (is_string($value) && trim($value) !== '');
        }
        if (! $hasSignal && ! $referrer) return null;

        return $this->recordTouch($request, $referrer, $referrer?->referral_code_normalized, null, $campaignKey);
    }

    public function recordTouch(Request $request, User|Partner|null $referrer = null, ?string $code = null, ?Unlock $unlock = null, ?string $campaignKey = null): AttributionTouch
    {
        $input = $request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']);
        $clickIds = [];
        foreach (['fbclid', 'ttclid', 'gclid'] as $name) {
            $value = $request->query($name);
            $clickIds[$name] = is_string($value) ? Str::limit(trim($value), 255, '') : null;
        }
        return AttributionTouch::create([
            'acquisition_provider' => app(AcquisitionProviderResolver::class)->forNewTouch($request),
            'anonymous_id' => $this->anonymousId($request), 'user_id' => $request->user()?->id,
            'referral_code' => $code, 'referrer_user_id' => $referrer instanceof User ? $referrer->id : null,
            'acquisition_partner_id' => $referrer instanceof Partner ? $referrer->id : null, 'unlock_id' => $unlock?->id,
            ...array_map(fn ($value) => is_string($value) ? Str::limit(trim($value), 255, '') : null, $input),
            ...$clickIds, 'campaign_key' => $campaignKey,
            'landing_page' => Str::limit('/'.ltrim($request->path(), '/'), 2048, ''), 'occurred_at' => now(),
        ]);
    }

    public function associateRegisteredUser(User $user, Request $request, ?string $manualCode = null): void
    {
        $anonymousId = $this->anonymousId($request);
        $ids = $request->session()->get('attribution_touch_ids', []);
        $touches = AttributionTouch::query()->whereNull('user_id')->where(fn ($query) => $query->where('anonymous_id', $anonymousId)->orWhereIn('id', is_array($ids) ? $ids : []))->orderBy('occurred_at')->get();
        foreach ($touches as $touch) { $touch->update(['user_id' => $user->id]); }
        if ($manualCode !== null) {
            $referrer = $this->findReferrer($manualCode);
            $manualTouch = $this->recordTouch($request, $referrer, $referrer?->referral_code_normalized ?? $this->normalizeCode($manualCode));
            $manualTouch->update(['user_id' => $user->id]);
            $touches->push($manualTouch);
        }
        $candidate = $touches->first(fn (AttributionTouch $touch) => $touch->referrer_user_id !== null || $touch->acquisition_partner_id !== null);
        if ($candidate) { $this->applyFirstValidReferrer($user, $candidate); }
    }

    public function applyFirstValidReferrer(User $referred, AttributionTouch $touch): ?ReferralRelationship
    {
        if ($touch->referrer_user_id === $referred->id) { return null; }
        $existing = ReferralRelationship::query()->where('referred_user_id', $referred->id)->where('status', ReferralRelationship::STATUS_ACTIVE)->latest('attributed_at')->first();
        if ($existing?->isValid()) { return $existing; }
        if ($existing) { $existing->update(['status' => 'expired']); }
        return ReferralRelationship::create(['referrer_user_id' => $touch->referrer_user_id, 'acquisition_partner_id' => $touch->acquisition_partner_id, 'referred_user_id' => $referred->id, 'referral_code' => $touch->referral_code, 'attribution_touch_id' => $touch->id, 'attributed_at' => $touch->occurred_at, 'expires_at' => $touch->occurred_at->copy()->addDays($this->windowDays()), 'status' => ReferralRelationship::STATUS_ACTIVE]);
    }

    /** @return User|Partner|null */
    public function findReferrer(string $code): User|Partner|null { $normalized = $this->normalizeCode($code); return User::query()->where('referral_code_normalized', $normalized)->first() ?? Partner::query()->where('referral_code_normalized', $normalized)->first(); }
    public function normalizeCode(string $code): string { return Str::upper(preg_replace('/[^A-Z0-9]/i', '', Str::ascii($code)) ?? ''); }
    public function windowDays(): int { return max(1, (int) (AppSetting::query()->where('key', 'attribution_window_days')->first()?->value['days'] ?? 30)); }
}
