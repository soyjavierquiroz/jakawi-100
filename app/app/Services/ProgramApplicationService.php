<?php

namespace App\Services;

use App\Models\AttributionTouch;
use App\Models\ProgramApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramApplicationService
{
    /** @return array{0: ProgramApplication, 1: bool} */
    public function record(User $user, string $program, Request $request, array $data): array
    {
        return DB::transaction(function () use ($user, $program, $request, $data): array {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = ProgramApplication::where('user_id', $user->id)->where('program_type', $program)
                ->whereIn('status', ProgramApplication::openStatuses())->latest()->first();
            if ($existing) return [$existing, false];

            $visitor = app(AttributionService::class)->anonymousId($request);
            $touches = AttributionTouch::where(fn ($q) => $q->where('user_id', $user->id)
                ->when($visitor, fn ($q) => $q->orWhere(fn ($visitorQuery) => $visitorQuery->whereNull('user_id')->where('anonymous_id', $visitor))))
                ->orderBy('occurred_at')->orderBy('id')->get();
            $first = $touches->first();
            $current = $touches->last();
            $fields = ['id', 'anonymous_id', 'user_id', 'referral_code', 'referrer_user_id', 'acquisition_partner_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'landing_page', 'occurred_at'];
            $application = ProgramApplication::create([
                'user_id' => $user->id, 'program_type' => $program, 'status' => ProgramApplication::SUBMITTED,
                'channel_url' => $data['channel_url'] ?? null, 'message' => $data['message'] ?? null,
                'attribution_touch_id' => $current?->id,
                'attribution_snapshot' => $current ? ['first' => $first?->only($fields), 'conversion' => $current->only($fields)] : null,
            ]);
            app(OwnershipAssignmentService::class)->inheritFromUser($application, $user);
            return [$application, true];
        });
    }
}
