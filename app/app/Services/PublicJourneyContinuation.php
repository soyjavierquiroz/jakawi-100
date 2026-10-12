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
        'UNLOCK' => ['COMMIT'],
        'BENEFIT' => ['REDEEM'],
        'ACQUISITION' => ['APPLY'],
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

    /** @return array{journey: string, resource_id: int|string, action: string, landing_presentation_id?: int, context?: array{experience_session_id?: int, location_id?: int}}|null */
    public function get(): ?array
    {
        $intent = $this->session->get(self::KEY);
        if (! is_array($intent) || ! isset($intent['journey'], $intent['resource_id'], $intent['action'])
            || ! is_string($intent['journey']) || ! (is_int($intent['resource_id']) || is_string($intent['resource_id'])) || ! is_string($intent['action'])
            || ! $this->allowed($intent['journey'], $intent['resource_id'], $intent['action'])
            || array_diff(array_keys($intent), ['journey', 'resource_id', 'action', 'context', 'landing_presentation_id'])
            || ! $this->allowedContext($intent['journey'], $intent['action'], $intent['context'] ?? [])) {
            return null;
        }

        return $intent;
    }

    /** @return array{journey: string, resource_id: int|string, action: string, landing_presentation_id?: int, context?: array{experience_session_id?: int, location_id?: int}}|null */
    public function consume(): ?array
    {
        $intent = $this->get();
        $this->session->forget(self::KEY);

        return $intent;
    }

    /** @param array{journey: string, resource_id: int|string, action: string, landing_presentation_id?: int, context?: array{experience_session_id?: int, location_id?: int}} $intent */
    public function destination(array $intent): ?string
    {
        if (! $this->allowed($intent['journey'], $intent['resource_id'], $intent['action'])
            || array_diff(array_keys($intent), ['journey', 'resource_id', 'action', 'context', 'landing_presentation_id'])
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

            return route('landing-presentations.show', $presentation, false);
        }

        return match ($intent['journey']) {
            'EXPERIENCE' => $this->experienceDestination($intent),
            'BENEFIT' => ($benefit = Benefit::find($intent['resource_id'])) ? route('redemptions.intent', ['benefit' => $benefit->slug] + ($intent['context'] ?? []), false) : null,
            'UNLOCK' => ($unlock = Unlock::find($intent['resource_id'])) && in_array($unlock->status, [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED], true)
                ? route('unlocks.show', $unlock->slug, false) : null,
            'ACQUISITION' => route('programs.show', ['program' => match ($intent['resource_id']) {
                'AFFILIATE' => 'afiliados', 'CREATOR' => 'creadores', 'PROMOTER' => 'promotores',
            }], false).'#solicitud',
            'SOCIAL_CHALLENGE' => ($challenge = Challenge::find($intent['resource_id'])) && $challenge->isPublic() ? route('social-challenges.show', $challenge->slug, false) : null,
        };
    }

    public function setLanding(\App\Models\LandingPresentation $presentation): void
    {
        $subject = $presentation->subject;
        [$journey, $action] = match (app(LandingPresentationEditor::class)->type($subject)) {
            'BENEFIT' => ['BENEFIT', 'REDEEM'], 'EXPERIENCE' => ['EXPERIENCE', 'RESERVE'],
            'CHALLENGE' => ['SOCIAL_CHALLENGE', 'PARTICIPATE'], default => ['UNLOCK', 'COMMIT'],
        };
        $this->set($journey, $subject->id, $action);
        $this->session->put(self::KEY.'.landing_presentation_id', $presentation->id);
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

    private function allowed(string $journey, int|string $resourceId, string $action): bool
    {
        if ($journey === 'ACQUISITION') {
            return in_array($resourceId, ['AFFILIATE', 'CREATOR', 'PROMOTER'], true) && $action === 'APPLY';
        }
        return is_int($resourceId) && $resourceId > 0 && in_array($action, self::ACTIONS[$journey] ?? [], true);
    }
}
