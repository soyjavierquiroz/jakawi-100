<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Unlock;
use App\Models\UnlockParticipation;
use App\Services\AnalyticsTracker;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicJourneyIntentController extends Controller
{
    public function store(Request $request, string $journey, string $slug, AnalyticsTracker $analytics): Response
    {
        $resource = match ($journey) {
            'experience' => Experience::where('slug', $slug)->first(),
            'unlock' => Unlock::where('slug', $slug)->first(),
            'benefit' => Benefit::where('slug', $slug)->first(),
            default => null,
        };
        $participation = $resource instanceof Unlock && $request->user()
            ? $request->user()->unlockParticipations()->where('unlock_id', $resource->id)->first()
            : null;

        $allowed = match (true) {
            $resource instanceof Experience => $resource->isPublished() && $resource->upcomingSessions()->exists()
                && ($resource->reservation_method === 'jakawi'
                    ? $request->user() && ! $request->user()->hasActiveMembership()
                    : (filled($resource->reservation_whatsapp) || filled($resource->reservation_url)
                        || (filled($resource->reservation_phone) && preg_match('/^\+?[1-9]\d{6,14}$/', $resource->reservation_phone)))),
            $resource instanceof Unlock => $request->user() && ! $request->user()->hasActiveMembership()
                && $resource->status === Unlock::ACTIVE && ! $resource->free_user_eligible && $resource->member_eligible
                && ($resource->commitment_deadline === null || $resource->commitment_deadline->isFuture())
                && ($resource->maximum_capacity === null || $resource->committedCount() < $resource->maximum_capacity)
                && ($participation === null || in_array($participation->status, [UnlockParticipation::INTERESTED, UnlockParticipation::WAITLISTED], true)),
            $resource instanceof Benefit => $request->user() && $resource->isAvailable()
                && $resource->availableLocations()->get()->contains(fn ($location) => $location->hasRedemptionPin()),
            default => false,
        };
        abort_unless($allowed, 404);

        $analytics->journeyIntentStarted($resource);

        return response()->noContent();
    }
}
