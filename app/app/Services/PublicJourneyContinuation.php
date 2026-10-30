<?php

namespace App\Services;

use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Benefit;
use App\Models\Unlock;
use App\Models\Challenge;
use Illuminate\Session\Store;
use InvalidArgumentException;

class PublicJourneyContinuation
{
    private const KEY = 'public_journey.continuation';
    private const EXPERIENCE_SESSION_KEY = 'public_journey.experience_session';

    private const ACTIONS = [
        'EXPERIENCE' => ['RESERVE'],
        'UNLOCK' => ['COMMIT', 'INTEREST', 'CANCEL', 'CONFIRM'],
        'BENEFIT' => ['REDEEM'],
        'SOCIAL_CHALLENGE' => ['PARTICIPATE'],
    ];

    public function __construct(private readonly Store $session) {}

    public function set(string $journey, int|string $resourceId, string $action, array $context = []): void
    {
        if (! $this->allowed($journey, $resourceId, $action) || ! $this->allowedContext($journey, $action, $context)) {
            throw new InvalidArgumentException('Invalid public journey continuation.');
        }

        if ($journey === 'EXPERIENCE' && $context && ! ExperienceSession::query()->whereKey($context['experience_session_id'])
            ->where('experience_id', $resourceId)->upcoming()->exists()) {
            throw new InvalidArgumentException('Invalid experience session.');
        }

        if ($journey === 'BENEFIT' && $context && ! Benefit::find($resourceId)?->availableLocations()->whereKey($context['location_id'])->get()->contains(fn ($location) => $location->hasRedemptionPin())) {
            throw new InvalidArgumentException('Invalid benefit location.');
        }

        $intent = ['journey' => $journey, 'resource_id' => $resourceId, 'action' => $action];
        $this->session->put(self::KEY, $context ? $intent + ['context' => $context] : $intent);
    }

    /** @return array{journey: string, resource_id: int|string, action: string, landing_presentation_id?: int, intended_action?: string, context?: array{experience_session_id?: int, location_id?: int}}|null */
    public function get(): ?array
    {
        $intent = $this->session->get(self::KEY);
        if (! is_array($intent) || ! isset($intent['journey'], $intent['resource_id'], $intent['action'])
            || ! is_string($intent['journey']) || ! (is_int($intent['resource_id']) || is_string($intent['resource_id'])) || ! is_string($intent['action'])
            || ! $this->allowed($intent['journey'], $intent['resource_id'], $intent['action'])
            || array_diff(array_keys($intent), ['journey', 'resource_id', 'action', 'context', 'landing_presentation_id', 'intended_action'])
            || ! $this->validIntendedAction($intent)
            || ! $this->allowedContext($intent['journey'], $intent['action'], $intent['context'] ?? [])) {
            return null;
        }

        return $intent;
    }

    /** @return array{journey: string, resource_id: int|string, action: string, landing_presentation_id?: int, intended_action?: string, context?: array{experience_session_id?: int, location_id?: int}}|null */
    public function consume(): ?array
    {
        $intent = $this->get();
        $this->session->forget(self::KEY);

        return $intent;
    }

    /** @param array{journey: string, resource_id: int|string, action: string, landing_presentation_id?: int, intended_action?: string, context?: array{experience_session_id?: int, location_id?: int}} $intent */
    public function destination(array $intent): ?string
    {
        if (! $this->allowed($intent['journey'], $intent['resource_id'], $intent['action'])
            || array_diff(array_keys($intent), ['journey', 'resource_id', 'action', 'context', 'landing_presentation_id', 'intended_action'])
            || ! $this->validIntendedAction($intent)
            || ! $this->allowedContext($intent['journey'], $intent['action'], $intent['context'] ?? [])) {
            return null;
        }

        if (isset($intent['landing_presentation_id'])) {
            $presentation = is_int($intent['landing_presentation_id']) ? \App\Models\LandingPresentation::find($intent['landing_presentation_id']) : null;
            $expectedType = match ($intent['journey']) {
                'BENEFIT' => Benefit::class, 'EXPERIENCE' => Experience::class,
                'SOCIAL_CHALLENGE' => Challenge::class, 'UNLOCK' => Unlock::class, default => null,
            };
            if (! $presentation || $presentation->status !== 'PUBLISHED' || ! $expectedType
                || ! ($presentation->subject instanceof $expectedType) || $presentation->subject_id !== $intent['resource_id']) {
                return null;
            }
            $this->session->flash('landing_acquisition_resumed', $presentation->id);
            $this->session->flash('landing_continuation_context', $intent['context'] ?? []);

            if ($presentation->subject instanceof Challenge && !$presentation->subject->requiresParticipationChoice()) {
                return route($presentation->subject->participations()->where('user_id', auth()->id())->exists()
                    ? 'landing-presentations.result' : 'landing-presentations.show', $presentation, false);
            }
            return route('landing-presentations.activation', $presentation, false);
        }

        return match ($intent['journey']) {
            'EXPERIENCE' => $this->experienceDestination($intent),
            'BENEFIT' => ($benefit = Benefit::find($intent['resource_id'])) ? route('redemptions.intent', ['benefit' => $benefit->slug] + ($intent['context'] ?? []), false) : null,
            'UNLOCK' => ($unlock = Unlock::find($intent['resource_id'])) && in_array($unlock->status, [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED], true)
                ? route('unlocks.show', $unlock->slug, false) : null,
            'SOCIAL_CHALLENGE' => ($challenge = Challenge::find($intent['resource_id'])) && $challenge->isPublic() ? route('social-challenges.show', $challenge->slug, false) : null,
        };
    }

    /** Only the explicit acquisition submission may authorize a join after authentication. */
    public function afterAuthentication(\Illuminate\Http\Request $request, array $intent): ?string
    {
        $destination = $this->destination($intent);
        if ($destination === null || ($intent['intended_action'] ?? null) !== 'PARTICIPATE' || !$request->user()) {
            return $destination;
        }
        $presentation = \App\Models\LandingPresentation::find($intent['landing_presentation_id'] ?? null);
        $challenge = $presentation?->subject;
        if (!$challenge instanceof Challenge || $challenge->requiresParticipationChoice()
            || !$challenge->acceptsParticipation()
            || ($challenge->participation_eligibility === 'ACTIVE_MEMBERS' && !$request->user()->hasActiveMembership())) {
            return $destination;
        }
        app(ChallengeService::class)->join($challenge, $request->user());
        return route('landing-presentations.result', $presentation, false);
    }

    public function setLanding(\App\Models\LandingPresentation $presentation, ?string $intendedAction = null): void
    {
        $subject = $presentation->subject;
        [$journey, $action] = match (app(LandingPresentationEditor::class)->type($subject)) {
            'BENEFIT' => ['BENEFIT', 'REDEEM'], 'EXPERIENCE' => ['EXPERIENCE', 'RESERVE'],
            'CHALLENGE' => ['SOCIAL_CHALLENGE', 'PARTICIPATE'], default => ['UNLOCK', 'COMMIT'],
        };
        if ($intendedAction !== null && ($journey !== 'SOCIAL_CHALLENGE' || $intendedAction !== 'PARTICIPATE')) {
            throw new InvalidArgumentException('Invalid explicit acquisition action.');
        }
        $this->set($journey, $subject->id, $action);
        $this->session->put(self::KEY.'.landing_presentation_id', $presentation->id);
        if ($intendedAction !== null) $this->session->put(self::KEY.'.intended_action', $intendedAction);
    }

    public function attachLanding(int $id): void
    {
        $intent = $this->get();
        $presentation = \App\Models\LandingPresentation::find($id);
        $type = match ($intent['journey'] ?? null) {
            'BENEFIT' => Benefit::class, 'EXPERIENCE' => Experience::class,
            'SOCIAL_CHALLENGE' => Challenge::class, 'UNLOCK' => Unlock::class, default => null,
        };
        if (! $presentation || $presentation->status !== 'PUBLISHED' || ! $type
            || ! ($presentation->subject instanceof $type) || $presentation->subject_id !== $intent['resource_id']) {
            throw new InvalidArgumentException('Invalid Landing continuation.');
        }
        $this->session->put(self::KEY.'.landing_presentation_id', $id);
    }

    public function restoredExperienceSessionId(Experience $experience): ?int
    {
        $saved = $this->session->pull(self::EXPERIENCE_SESSION_KEY);
        if (! is_array($saved) || ($saved['experience_id'] ?? null) !== $experience->id
            || ! is_int($saved['session_id'] ?? null)) {
            return null;
        }

        return $experience->upcomingSessions()->whereKey($saved['session_id'])->exists() ? $saved['session_id'] : null;
    }

    private function experienceDestination(array $intent): ?string
    {
        $experience = Experience::find($intent['resource_id']);
        if (! $experience) {
            return null;
        }

        $sessionId = $intent['context']['experience_session_id'] ?? null;
        if ($sessionId && $experience->upcomingSessions()->whereKey($sessionId)->exists()) {
            $this->session->flash(self::EXPERIENCE_SESSION_KEY, ['experience_id' => $experience->id, 'session_id' => $sessionId]);
        }

        return route('experiences.show', $experience->slug, false);
    }

    private function allowedContext(string $journey, string $action, mixed $context): bool
    {
        if (! is_array($context)) {
            return false;
        }
        if ($context === []) {
            return true;
        }

        if ($journey === 'BENEFIT' && $action === 'REDEEM') {
            return array_keys($context) === ['location_id'] && is_int($context['location_id']) && $context['location_id'] > 0;
        }

        return $journey === 'EXPERIENCE' && $action === 'RESERVE'
            && array_keys($context) === ['experience_session_id']
            && is_int($context['experience_session_id']) && $context['experience_session_id'] > 0;
    }

    private function validIntendedAction(array $intent): bool
    {
        return !array_key_exists('intended_action', $intent)
            || (($intent['journey'] ?? null) === 'SOCIAL_CHALLENGE' && ($intent['action'] ?? null) === 'PARTICIPATE'
                && $intent['intended_action'] === 'PARTICIPATE' && isset($intent['landing_presentation_id']));
    }

    private function allowed(string $journey, int|string $resourceId, string $action): bool
    {
        return is_int($resourceId) && $resourceId > 0 && in_array($action, self::ACTIONS[$journey] ?? [], true);
    }
}
