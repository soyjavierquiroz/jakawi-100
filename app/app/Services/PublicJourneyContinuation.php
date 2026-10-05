<?php

namespace App\Services;

use App\Models\Experience;
use App\Models\Unlock;
use Illuminate\Session\Store;
use InvalidArgumentException;

class PublicJourneyContinuation
{
    private const KEY = 'public_journey.continuation';

    private const ACTIONS = [
        'EXPERIENCE' => ['RESERVE'],
        'UNLOCK' => ['COMMIT'],
    ];

    public function __construct(private readonly Store $session) {}

    public function set(string $journey, int $resourceId, string $action): void
    {
        if (! $this->allowed($journey, $resourceId, $action)) {
            throw new InvalidArgumentException('Invalid public journey continuation.');
        }

        $this->session->put(self::KEY, ['journey' => $journey, 'resource_id' => $resourceId, 'action' => $action]);
    }

    /** @return array{journey: string, resource_id: int, action: string}|null */
    public function get(): ?array
    {
        $intent = $this->session->get(self::KEY);
        if (! is_array($intent) || ! isset($intent['journey'], $intent['resource_id'], $intent['action'])
            || ! is_string($intent['journey']) || ! is_int($intent['resource_id']) || ! is_string($intent['action'])
            || ! $this->allowed($intent['journey'], $intent['resource_id'], $intent['action'])) {
            return null;
        }

        return $intent;
    }

    /** @return array{journey: string, resource_id: int, action: string}|null */
    public function consume(): ?array
    {
        $intent = $this->get();
        $this->session->forget(self::KEY);

        return $intent;
    }

    /** @param array{journey: string, resource_id: int, action: string} $intent */
    public function destination(array $intent): ?string
    {
        if (! $this->allowed($intent['journey'], $intent['resource_id'], $intent['action'])) {
            return null;
        }

        return match ($intent['journey']) {
            'EXPERIENCE' => ($experience = Experience::find($intent['resource_id'])) ? route('experiences.show', $experience->slug, false) : null,
            'UNLOCK' => ($unlock = Unlock::find($intent['resource_id'])) && in_array($unlock->status, [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED], true)
                ? route('unlocks.show', $unlock->slug, false) : null,
        };
    }

    private function allowed(string $journey, int $resourceId, string $action): bool
    {
        return $resourceId > 0 && in_array($action, self::ACTIONS[$journey] ?? [], true);
    }
}
