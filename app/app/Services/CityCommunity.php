<?php

namespace App\Services;

use App\Models\City;
use App\Models\CommunityInteraction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CityCommunity
{
    /** @return array<string, mixed>|null */
    public function data(Request $request, string $surface): ?array
    {
        if (! $request->user()) {
            return null;
        }
        $city = app(SelectedCity::class)->resolve($request);
        if (! $city->hasCommunity()) {
            return null;
        }
        $state = CommunityInteraction::where('user_id', $request->user()->id)->where('city_id', $city->id)->first();
        $onboarding = $request->session()->get('community_onboarding') === $request->user()->id && (! $state || $state->exposure_count === 0);

        return [
            'city_slug' => $city->slug, 'city_name' => $city->name, 'name' => $city->community_name,
            'status' => $state ? $state->status : 'not_invited',
            // Floating access is independent from promotional invitation frequency.
            'access' => $state?->status !== 'declined',
            'automatic' => $surface !== 'floating' && ($surface === 'home' || $onboarding) && $this->eligible($state, $request),
            'source' => $surface === 'floating' ? 'floating' : ($onboarding ? 'onboarding' : $surface),
        ];
    }

    private function sessionHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    public function eligible(?CommunityInteraction $state, Request $request): bool
    {
        if ($request->session()->get('community_exposure_session_hash') === $this->sessionHash($request)
            || CommunityInteraction::where('user_id', $request->user()->id)->where('last_session_hash', $this->sessionHash($request))->exists()) {
            return false;
        }
        if (! $state) {
            return true;
        }
        if (in_array($state->status, ['declined', 'self_reported_joined'], true)
            || $state->exposure_count >= config('communities.max_exposures')) {
            return false;
        }

        return (! $state->last_invited_at || $state->last_invited_at->lte(now()->subDays(config('communities.reminder_days'))))
            && (! $state->last_snoozed_at || $state->last_snoozed_at->lte(now()->subDays(config('communities.reminder_days'))))
            && (! $state->last_opened_at || $state->last_opened_at->lte(now()->subDays(config('communities.opened_pause_days'))));
    }

    /** @return array{accepted:bool, status:string, url?:string} */
    public function interact(Request $request, City $city, string $action, string $source): array
    {
        return DB::transaction(function () use ($request, $city, $action, $source) {
            // Serialize even the first interaction, across cities and concurrent sessions.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $city = City::whereKey($city->id)->sharedLock()->firstOrFail();
            abort_unless($city->hasCommunity(), 404);
            $state = CommunityInteraction::firstOrCreate(['user_id' => $request->user()->id, 'city_id' => $city->id]);
            if ($action === 'presented' && ! $this->eligible($state, $request)) {
                return ['accepted' => false, 'status' => $state->status];
            }
            $changed = true;
            switch ($action) {
                case 'presented':
                    $state->status = 'invited';
                    $state->exposure_count++;
                    $state->last_invited_at = now();
                    $state->last_session_hash = $this->sessionHash($request);
                    break;
                case 'opened':
                    // Explicit opt-outs and self reports survive voluntary manual opens.
                    if (! in_array($state->status, ['declined', 'self_reported_joined'], true)) {
                        $state->status = 'opened';
                    }
                    $changed = ! $state->last_opened_at || $state->last_opened_at->lt(now()->subSeconds(5));
                    $state->last_opened_at = now();
                    break;
                case 'snoozed':
                    $changed = ! $state->last_snoozed_at || $state->last_snoozed_at->lt(now()->subDays(config('communities.reminder_days')));
                    $state->last_snoozed_at = now();
                    break;
                case 'declined':
                case 'self_reported_joined':
                    $changed = $state->status !== $action;
                    $state->status = $action;
                    break;
                case 'reset':
                    $changed = in_array($state->status, ['declined', 'self_reported_joined'], true);
                    if ($changed) {
                        $state->status = $state->last_opened_at ? 'opened' : ($state->exposure_count ? 'invited' : 'not_invited');
                    }
                    break;
            }
            $state->save();
            if ($action === 'presented') {
                $request->session()->put('community_exposure_session_hash', $this->sessionHash($request));
            }
            if ($changed) {
                // Separate savepoint keeps best-effort analytics failure out of domain state.
                DB::transaction(fn () => app(AnalyticsTracker::class)->record('community_'.$action, [], [
                    'city_id' => $city->id, 'city_slug' => $city->slug, 'source' => $source,
                ]));
            }

            return ['accepted' => true, 'status' => $state->status]
                + ($action === 'opened' ? ['url' => $city->community_invite_url] : []);
        }, 3);
    }
}
