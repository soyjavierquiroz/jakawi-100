<?php

namespace App\Services;

use App\Http\Middleware\EnsureVisitorId;
use App\Models\AppSetting;
use App\Models\AttributionTouch;
use App\Models\ReferralRelationship;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributionService
{
    public function anonymousId(Request $request): ?string
    {
        $id = $request->attributes->get(EnsureVisitorId::ATTRIBUTE);
        return is_string($id) && Str::isUuid($id) ? $id : null;
    }

    public function recordTouch(Request $request, ?User $referrer = null, ?string $code = null): AttributionTouch
    {
        $input = $request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']);
        return AttributionTouch::create([
            'anonymous_id' => $this->anonymousId($request), 'user_id' => $request->user()?->id,
            'referral_code' => $code, 'referrer_user_id' => $referrer?->id,
            ...array_map(fn ($value) => is_string($value) ? Str::limit(trim($value), 255, '') : null, $input),
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
        $candidate = $touches->first(fn (AttributionTouch $touch) => $touch->referrer_user_id !== null);
        if ($candidate) { $this->applyFirstValidReferrer($user, $candidate); }
    }

    public function applyFirstValidReferrer(User $referred, AttributionTouch $touch): ?ReferralRelationship
    {
        if ($touch->referrer_user_id === $referred->id) { return null; }
        $existing = ReferralRelationship::query()->where('referred_user_id', $referred->id)->where('status', ReferralRelationship::STATUS_ACTIVE)->latest('attributed_at')->first();
        if ($existing?->isValid()) { return $existing; }
        if ($existing) { $existing->update(['status' => 'expired']); }
        return ReferralRelationship::create(['referrer_user_id' => $touch->referrer_user_id, 'referred_user_id' => $referred->id, 'referral_code' => $touch->referral_code, 'attribution_touch_id' => $touch->id, 'attributed_at' => $touch->occurred_at, 'expires_at' => $touch->occurred_at->copy()->addDays($this->windowDays()), 'status' => ReferralRelationship::STATUS_ACTIVE]);
    }

    public function findReferrer(string $code): ?User { return User::query()->where('referral_code_normalized', $this->normalizeCode($code))->first(); }
    public function normalizeCode(string $code): string { return Str::upper(preg_replace('/[^A-Z0-9]/i', '', Str::ascii($code)) ?? ''); }
    public function windowDays(): int { return max(1, (int) (AppSetting::query()->where('key', 'attribution_window_days')->first()?->value['days'] ?? 30)); }
}
